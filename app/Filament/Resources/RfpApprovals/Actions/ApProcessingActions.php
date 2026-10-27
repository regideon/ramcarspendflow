<?php

namespace App\Filament\Resources\RfpApprovals\Actions;

use App\Enums\RfpStatus;
use App\Filament\Resources\RfpApprovals\RfpApprovalResource;
use App\Models\Rfp\Item;
use App\Models\Rfp\Rfp;
use App\Models\TaxCode;
use App\Models\WithholdingTaxCode;
use App\Services\Rfp\RfpTaxCalculator;
use App\Services\Rfp\RfpTaxProcessing;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/**
 * Step 4, AP Processor processing (legacy "Process Tax Codes"): book the input tax and
 * withholding tax per line (VAT and withholding worked out live), optionally correct
 * how the gross is split between lines of a currency, then send it to the AP Supervisor.
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
            ->modalWidth(Width::SevenExtraLarge)
            ->modalHeading(fn (Rfp $record): string => "{$label} · {$record->rfp_number}")
            ->modalDescription('Gross amounts include VAT. You may move amounts between lines of the same currency, but each currency must still total the same.')
            ->fillForm(fn (Rfp $record): array => static::linesFor($record))
            ->schema([
                Repeater::make('lines')
                    ->hiddenLabel()
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->table([
                        TableColumn::make('Invoice'),
                        TableColumn::make('Gross')->width('10rem'),
                        TableColumn::make('Input tax')->width('15rem'),
                        TableColumn::make('Withholding tax')->width('17rem'),
                        TableColumn::make('VAT')->alignment(Alignment::End),
                        TableColumn::make('Net of VAT')->alignment(Alignment::End),
                        TableColumn::make('Withholding')->alignment(Alignment::End),
                        TableColumn::make('Net of WHT')->alignment(Alignment::End),
                    ])
                    ->schema([
                        TextEntry::make('invoice')
                            ->state(fn (Get $get): string => $get('invoice_number') ?: '—')
                            ->belowContent(fn (Get $get): ?string => $get('description')),
                        TextInput::make('amount')
                            ->hiddenLabel()
                            ->numeric()
                            ->minValue(0.01)
                            ->required()
                            ->prefix(fn (Get $get): ?string => $get('currency'))
                            ->live(onBlur: true),
                        Select::make('tax_code_id')
                            ->hiddenLabel()
                            ->options(fn (): array => static::taxCodeOptions())
                            ->searchable()
                            ->required()
                            ->disabled($lockInputTax)
                            ->dehydrated()
                            ->live(),
                        Select::make('withholding_tax_code_id')
                            ->hiddenLabel()
                            ->options(fn (): array => static::withholdingOptions())
                            ->searchable()
                            ->required()
                            ->disabled(fn (Get $get): bool => blank($get('tax_code_id')))
                            ->hint(fn (Get $get): ?string => blank($get('tax_code_id')) ? 'Input tax first' : null)
                            ->live(),
                        TextEntry::make('vat')->state(fn (Get $get): string => static::computed($get, 'vat_amount'))->alignEnd(),
                        TextEntry::make('net_of_vat')->state(fn (Get $get): string => static::computed($get, 'net_of_vat_amount'))->alignEnd(),
                        TextEntry::make('withholding')->state(fn (Get $get): string => static::computed($get, 'withholding_tax_amount'))->alignEnd(),
                        TextEntry::make('net_of_withholding')
                            ->state(fn (Get $get): string => static::computed($get, 'net_of_withholding_tax_amount'))
                            ->weight('bold')
                            ->alignEnd(),
                        Hidden::make('id'),
                        Hidden::make('invoice_number'),
                        Hidden::make('description'),
                        Hidden::make('currency'),
                        Hidden::make('original_amount'),
                    ]),
                TextEntry::make('totals')
                    ->label('Totals per currency')
                    ->state(fn (Get $get): HtmlString => static::totals($get('lines') ?? [])),
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
     * One of the line's worked-out amounts, formatted, from its current form state.
     */
    protected static function computed(Get $get, string $field): string
    {
        $amount = is_numeric($get('amount')) ? bcadd((string) $get('amount'), '0', 2) : '0.00';

        if (blank($get('tax_code_id'))) {
            return '—';
        }

        $result = app(RfpTaxCalculator::class)->forLine(
            $amount,
            TaxCode::find($get('tax_code_id')),
            filled($get('withholding_tax_code_id')) ? WithholdingTaxCode::find($get('withholding_tax_code_id')) : null,
        );

        return number_format((float) $result[$field], 2);
    }

    /**
     * Per currency: submitted gross, current gross, variance, and the total net of withholding tax.
     *
     * @param  array<int|string, array<string, mixed>>  $lines
     */
    protected static function totals(array $lines): HtmlString
    {
        $rows = collect($lines)
            ->groupBy('currency')
            ->map(function ($group, string $currency): string {
                $original = $group->reduce(fn (string $sum, array $line): string => bcadd($sum, (string) ($line['original_amount'] ?? '0'), 2), '0.00');
                $current = $group->reduce(fn (string $sum, array $line): string => bcadd($sum, is_numeric($line['amount'] ?? null) ? (string) $line['amount'] : '0', 2), '0.00');
                $variance = bcsub($current, $original, 2);
                $net = $group->reduce(function (string $sum, array $line): string {
                    if (blank($line['tax_code_id'] ?? null) || ! is_numeric($line['amount'] ?? null)) {
                        return $sum;
                    }

                    $result = app(RfpTaxCalculator::class)->forLine(
                        bcadd((string) $line['amount'], '0', 2),
                        TaxCode::find($line['tax_code_id']),
                        filled($line['withholding_tax_code_id'] ?? null) ? WithholdingTaxCode::find($line['withholding_tax_code_id']) : null,
                    );

                    return bcadd($sum, $result['net_of_withholding_tax_amount'], 2);
                }, '0.00');

                $varianceClass = bccomp($variance, '0', 2) === 0 ? 'text-success-600 dark:text-success-400' : 'text-danger-600 dark:text-danger-400 font-semibold';

                return '<tr class="border-t border-gray-200 dark:border-white/10">'
                    .'<td class="py-1.5 pe-6 font-medium">'.e($currency).'</td>'
                    .'<td class="py-1.5 pe-6 text-end">'.number_format((float) $original, 2).'</td>'
                    .'<td class="py-1.5 pe-6 text-end">'.number_format((float) $current, 2).'</td>'
                    .'<td class="py-1.5 pe-6 text-end '.$varianceClass.'">'.number_format((float) $variance, 2).'</td>'
                    .'<td class="py-1.5 text-end font-semibold">'.number_format((float) $net, 2).'</td>'
                    .'</tr>';
            })
            ->implode('');

        return new HtmlString(
            '<table class="text-sm"><thead><tr class="text-xs text-gray-500 dark:text-gray-400">'
            .'<th class="pe-6 text-start font-medium">Currency</th><th class="pe-6 text-end font-medium">Submitted gross</th>'
            .'<th class="pe-6 text-end font-medium">Current gross</th><th class="pe-6 text-end font-medium">Variance (must be 0)</th>'
            .'<th class="text-end font-medium">Total net of WHT</th></tr></thead><tbody>'.$rows.'</tbody></table>'
        );
    }
}
