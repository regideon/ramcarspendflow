<?php

namespace App\Services\Rfp;

use App\Models\Rfp\Item;
use App\Models\Rfp\Rfp;
use App\Models\TaxCode;
use App\Models\User;
use App\Models\WithholdingTaxCode;
use App\Models\WithholdingTaxType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Step 4, AP processing (legacy "Process Tax Codes"): books the input tax and
 * withholding tax of every line, lets AP correct how the gross is split between lines
 * as long as each currency's total is unchanged, then approves the step. The AP
 * Supervisor (Step 5) reviews through the same form and may adjust the codes.
 */
class RfpTaxProcessing
{
    /**
     * The only withholding tax type legacy offered at this step: Expanded Withholding Tax (Invoice).
     */
    public const string WITHHOLDING_TAX_TYPE = 'EI';

    public function __construct(
        protected ApprovalEngine $engine,
        protected RfpTaxCalculator $calculator,
    ) {}

    /**
     * @param  array<int|string, array{id: int|string, amount: int|float|string, tax_code_id: int|string|null, withholding_tax_code_id: int|string|null}>  $lines
     * @param  array<string, mixed>  $rfpAttributes  saved on the RFP with the tax entries (e.g. is_ids)
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function process(Rfp $rfp, User $by, array $lines, ?string $remarks = null, string $verb = 'Tax codes processed', bool $lockInputTax = false, array $rfpAttributes = []): void
    {
        $lines = collect($lines)->keyBy(fn (array $line): int => (int) $line['id']);
        $items = $rfp->items()->with('currency')->get()->keyBy('id');

        $this->validate($items, $lines);

        if ($lockInputTax && $items->contains(fn (Item $item): bool => (int) $lines->get($item->id)['tax_code_id'] !== (int) $item->tax_code_id)) {
            throw ValidationException::withMessages(['lines' => 'The input tax cannot be changed at this step. Return it to the AP Processor instead.']);
        }

        $taxCodes = TaxCode::query()->whereKey($lines->pluck('tax_code_id'))->get()->keyBy('id');
        $withholdingCodes = WithholdingTaxCode::query()->whereKey($lines->pluck('withholding_tax_code_id'))->get()->keyBy('id');
        $withholdingType = WithholdingTaxType::query()->where('code', self::WITHHOLDING_TAX_TYPE)->value('id');

        DB::transaction(function () use ($rfp, $by, $remarks, $verb, $rfpAttributes, $lines, $items, $taxCodes, $withholdingCodes, $withholdingType): void {
            foreach ($items as $item) {
                $line = $lines->get($item->id);
                $gross = $this->money($line['amount']);
                $taxCode = $taxCodes->get((int) $line['tax_code_id']);
                $withholdingCode = $withholdingCodes->get((int) $line['withholding_tax_code_id']);

                // The rate to PHP captured on submit stays; only the split of the gross changes.
                $item->forceFill([
                    'amount' => $gross,
                    'php_amount' => bcadd(bcmul($gross, (string) $item->exchange_rate, 6), '0.005', 2),
                    'tax_code_id' => $taxCode->id,
                    'withholding_tax_code_id' => $withholdingCode->id,
                    'withholding_tax_type_id' => $withholdingType,
                    ...$this->calculator->forLine($gross, $taxCode, $withholdingCode),
                ])->saveQuietly();
            }

            $rfp->forceFill([
                ...$rfpAttributes,
                'total_php_amount' => $items->reduce(fn (string $sum, Item $item): string => bcadd($sum, (string) $item->php_amount, 2), '0.00'),
            ])->saveQuietly();

            $this->engine->approve($rfp, $by, $remarks, verb: $verb);
        });
    }

    /**
     * Every line needs both codes, and each currency's gross total must not change.
     *
     * @param  Collection<int, Item>  $items
     * @param  Collection<int, array<string, mixed>>  $lines
     *
     * @throws ValidationException
     */
    protected function validate(Collection $items, Collection $lines): void
    {
        $problems = [];

        if ($lines->keys()->sort()->values()->all() !== $items->keys()->sort()->values()->all()) {
            throw ValidationException::withMessages(['lines' => 'The lines changed while you were working. Reload the request and try again.']);
        }

        foreach ($lines as $line) {
            if (blank($line['tax_code_id'] ?? null) || ! TaxCode::query()->whereKey($line['tax_code_id'])->where('is_active', true)->exists()) {
                $problems[] = 'Choose an input tax on every line.';
            }

            if (blank($line['withholding_tax_code_id'] ?? null) || ! WithholdingTaxCode::query()->whereKey($line['withholding_tax_code_id'])->where('is_active', true)->exists()) {
                $problems[] = 'Choose a withholding tax on every line.';
            }

            if (! is_numeric($line['amount'] ?? null) || bccomp($this->money($line['amount']), '0', 2) <= 0) {
                $problems[] = 'Every gross amount must be more than zero.';
            }
        }

        if ($problems === []) {
            foreach ($this->variances($items, $lines) as $currency => $variance) {
                $problems[] = "The {$currency} lines must still total the same (off by {$currency} ".number_format((float) $variance, 2).').';
            }
        }

        if ($problems !== []) {
            throw ValidationException::withMessages(['lines' => array_values(array_unique($problems))]);
        }
    }

    /**
     * Per currency code, how far the edited gross total is from the submitted one (non-zero only).
     *
     * @param  Collection<int, Item>  $items
     * @param  Collection<int, array<string, mixed>>  $lines
     * @return array<string, string>
     */
    public function variances(Collection $items, Collection $lines): array
    {
        return $items
            ->groupBy(fn (Item $item): string => (string) $item->currency?->code)
            ->map(fn (Collection $group): string => bcsub(
                $group->reduce(fn (string $sum, Item $item): string => bcadd($sum, $this->money($lines->get($item->id)['amount'] ?? '0'), 2), '0.00'),
                $group->reduce(fn (string $sum, Item $item): string => bcadd($sum, (string) $item->amount, 2), '0.00'),
                2,
            ))
            ->filter(fn (string $variance): bool => bccomp($variance, '0', 2) !== 0)
            ->all();
    }

    private function money(mixed $amount): string
    {
        return bcadd(is_numeric($amount) ? (string) $amount : '0', '0', 2);
    }
}
