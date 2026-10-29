<?php

namespace App\Filament\Resources\RfpApprovals\Actions;

use App\Enums\RfpStatus;
use App\Filament\Resources\RfpApprovals\RfpApprovalResource;
use App\Models\Rfp\Item;
use App\Models\Rfp\Rfp;
use App\Models\TaxCode;
use App\Models\WithholdingTaxCode;
use App\Services\Rfp\RfpTaxProcessing;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * Step 4, AP Processor processing (legacy "Process Tax Codes"): book the input tax and
 * withholding tax per line, optionally correct how the gross is split between PHP
 * lines, then send it to the AP Supervisor. The lines are one browser-side table
 * (filament.rfps.tax-entries-field) so editing many lines never waits on the server.
 * Return for revision and Cancel are StepActions.
 */
class ApProcessingActions
{
    /**
     * @return list<Action>
     */
    public static function make(): array
    {
        return [static::processTaxCodes()];
    }

    public static function processTaxCodes(): Action
    {
        return static::taxCodesAction('processTaxCodes', 'Process tax codes', 'Save and send to AP Supervisor', 'Tax codes processed')
            ->visible(fn (Rfp $record): bool => StepActions::builtStep($record)?->status === RfpStatus::ForApProcessing);
    }

    /**
     * The tax codes form, filled from the lines (and any codes already booked), saving
     * through RfpTaxProcessing and approving the user's step as $verb. $extraSchema adds
     * fields under the lines; $rfpAttributes maps the form data to RFP attributes saved
     * with it. With $lockInputTax the input tax is shown but cannot change.
     *
     * @param  list<Component>  $extraSchema
     * @param  (Closure(array<string, mixed>): array<string, mixed>)|null  $rfpAttributes
     */
    public static function taxCodesAction(string $name, string $label, string $submitLabel, string $verb, bool $lockInputTax = false, array $extraSchema = [], ?Closure $rfpAttributes = null): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon(Heroicon::OutlinedCalculator)
            ->color('success')
            ->slideOver()
            ->modalWidth('max-w-[97vw]') // nearly full width; the page still shows at the left, so it reads as a slide-over
            ->modalHeading(fn (Rfp $record): string => "{$label} · {$record->rfp_number}")
            ->modalDescription('Gross amounts include VAT. You may move amounts between lines of the same currency, but each currency must still total the same.')
            ->fillForm(fn (Rfp $record): array => static::linesFor($record))
            ->schema([
                ViewField::make('lines')
                    ->hiddenLabel()
                    ->view('filament.rfps.tax-entries-field')
                    ->viewData(fn (): array => [
                        'taxCodeOptions' => static::taxCodeOptions(),
                        'withholdingOptions' => static::withholdingOptions(),
                        'taxRates' => static::taxRates(),
                        'withholdingRates' => static::withholdingRates(),
                        'withholdingTypeLabel' => RfpTaxProcessing::WITHHOLDING_TAX_TYPE,
                        'lockInputTax' => $lockInputTax,
                    ]),
                ...$extraSchema,
                Textarea::make('remarks')
                    ->label('Remarks (optional)')
                    ->rows(2),
            ])
            ->modalSubmitActionLabel($submitLabel)
            ->action(function (Rfp $record, array $data, Action $action) use ($verb, $lockInputTax, $rfpAttributes): void {
                try {
                    app(RfpTaxProcessing::class)->process(
                        $record,
                        auth()->user(),
                        array_values($data['lines']),
                        $data['remarks'] ?? null,
                        $verb,
                        lockInputTax: $lockInputTax,
                        rfpAttributes: $rfpAttributes ? $rfpAttributes($data) : [],
                    );
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title('Not saved yet')
                        ->body(implode(' ', collect($exception->errors())->flatten()->all()))
                        ->danger()
                        ->send();

                    $action->halt();
                }

                Notification::make()
                    ->title("{$record->rfp_number}: ".strtolower($verb))
                    ->body("Now with {$record->refresh()->currentApproverLabel()}.")
                    ->success()
                    ->send();

                $action->redirect(RfpApprovalResource::getUrl());
            });
    }

    /**
     * The form state for the RFP's lines, with any codes already booked.
     *
     * @return array{lines: list<array<string, mixed>>}
     */
    public static function linesFor(Rfp $rfp): array
    {
        return [
            'lines' => $rfp->items()->with('currency')->orderBy('sort')->get()
                ->map(fn (Item $item): array => [
                    'id' => $item->id,
                    'invoice_number' => $item->invoice_number,
                    'description' => $item->description,
                    'currency' => $item->currency?->code,
                    'is_php' => $item->currency?->code === 'PHP',
                    'original_amount' => (string) $item->amount,
                    'amount' => (string) $item->amount,
                    'tax_code_id' => $item->tax_code_id,
                    'withholding_tax_code_id' => $item->withholding_tax_code_id,
                ])
                ->all(),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected static function taxCodeOptions(): array
    {
        return once(fn (): array => TaxCode::query()->where('is_active', true)->orderBy('code')->get()
            ->mapWithKeys(fn (TaxCode $code): array => [$code->id => "{$code->code} - {$code->description}"])
            ->all());
    }

    /**
     * @return array<int, string>
     */
    protected static function withholdingOptions(): array
    {
        return once(fn (): array => WithholdingTaxCode::query()->where('is_active', true)->orderBy('code')->get()
            ->mapWithKeys(fn (WithholdingTaxCode $code): array => [$code->id => "{$code->code} - {$code->description} (".rtrim(rtrim((string) $code->rate, '0'), '.').'%)'])
            ->all());
    }

    /**
     * Input tax rates (percent) by id, for the live calculation in the browser.
     *
     * @return array<int, float>
     */
    protected static function taxRates(): array
    {
        return once(fn (): array => TaxCode::query()->where('is_active', true)->pluck('rate', 'id')->map(fn ($rate): float => (float) $rate)->all());
    }

    /**
     * Withholding tax rates (percent) by id, for the live calculation in the browser.
     *
     * @return array<int, float>
     */
    protected static function withholdingRates(): array
    {
        return once(fn (): array => WithholdingTaxCode::query()->where('is_active', true)->pluck('rate', 'id')->map(fn ($rate): float => (float) $rate)->all());
    }
}
