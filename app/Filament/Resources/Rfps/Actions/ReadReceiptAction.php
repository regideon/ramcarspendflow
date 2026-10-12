<?php

namespace App\Filament\Resources\Rfps\Actions;

use App\Enums\RfpRequestType;
use App\Enums\RfpVendorType;
use App\Filament\Resources\Rfps\Schemas\RfpForm;
use App\Models\Rfp\Subcategory;
use App\Services\Rfp\ReceiptScanner;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Throwable;

/**
 * "Read receipt" on a line's Supporting Documents: the AI reads the PDFs/images, the requestor
 * reviews and corrects the result in a modal, and only then is it applied to the line.
 */
class ReadReceiptAction
{
    public static function make(): Action
    {
        return Action::make('readReceipt')
            ->label('Read receipt with AI')
            ->icon(Heroicon::Sparkles)
            ->button()
            ->color('primary')
            ->size('lg')
            // Full width under the upload, easy to spot. Hidden (not removed) without a PDF/image, so a
            // button left on screen after the file is removed still answers "Upload the document first".
            ->extraAttributes(fn (FileUpload $component): array => [
                'style' => 'width: 100%; justify-content: center;'
                    .(ReceiptScanner::readableFiles($component->getRawState(), $component->getDiskName()) === [] ? ' display: none;' : ''),
            ])
            ->visible(fn (): bool => config('services.receipt_reader.enabled') && filled(config('ai.providers.openai.key')))
            ->modalHeading('Review what was read')
            ->modalDescription('Check each value against the document and correct it if needed. Applying replaces the item\'s current values.')
            ->modalIcon(Heroicon::OutlinedDocumentMagnifyingGlass)
            ->modalWidth('3xl')
            ->modalSubmitActionLabel('Apply to item')
            ->mountUsing(fn (Action $action, ?Schema $schema, FileUpload $component, Get $get, Component $livewire) => static::read($action, $schema, $component, $get, $livewire))
            ->schema(fn (Get $get): array => static::reviewSchema($get))
            ->action(fn (array $data, Set $set, Get $get) => static::apply($data, $set, $get));
    }

    /**
     * Read the documents and fill the review. Closes the modal with a message when reading fails.
     */
    protected static function read(Action $action, ?Schema $schema, FileUpload $component, Get $get, Component $livewire): void
    {
        // The button can still be on screen after the file was removed
        $files = ReceiptScanner::readableFiles($component->getRawState(), $component->getDiskName());

        if ($files === []) {
            Notification::make()
                ->title('Upload the document first')
                ->body('Attach a PDF or a photo (JPG/PNG) of the receipt, invoice or bill, then click Read receipt with AI.')
                ->warning()
                ->send();

            $action->cancel();
        }

        $scanner = app(ReceiptScanner::class);
        $subcategories = RfpForm::subcategoryOptions($get('cost_center_id'), $get('po_number'));

        // Read before (e.g. the modal was closed): reuse it, no AI call and no cost
        $read = $scanner->savedRead($files);
        $reused = $read !== null;

        if (! $reused) {
            $key = 'receipt-reader:'.auth()->id();

            if (RateLimiter::tooManyAttempts($key, (int) config('services.receipt_reader.per_hour'))) {
                Notification::make()
                    ->title('Too many receipts read')
                    ->body('Try again in '.ceil(RateLimiter::availableIn($key) / 60).' minutes, or fill in the item yourself.')
                    ->warning()
                    ->send();

                $action->cancel();
            }

            RateLimiter::hit($key, 3600);

            try {
                $read = $scanner->read(
                    $files,
                    Subcategory::query()->whereKey(array_keys($subcategories))->pluck('name')->all(),
                );
            } catch (Throwable $exception) {
                report($exception);

                Notification::make()
                    ->title('Couldn\'t read the document')
                    ->body('The reader is unavailable or the file couldn\'t be read. Fill in the item yourself, or try again later.')
                    ->danger()
                    ->send();

                $action->cancel();
            }
        }

        $review = $scanner->review($read, $subcategories, [
            'reimbursement' => static::isReimbursement($get),
            'rfp_id' => $livewire instanceof EditRecord ? $livewire->getRecord()->getKey() : null,
            'payee_id' => $get('../../vendor_id'),
        ]);

        // Purpose is for the whole RFP: keep what the requestor (or an earlier item) already wrote
        $currentPurpose = $get('../../purpose');

        $schema?->fill([
            ...$review['fields'],
            'purpose' => filled($currentPurpose) ? $currentPurpose : $review['fields']['purpose'],
            'suggested_purpose' => $review['fields']['purpose'],
            'warnings' => $review['warnings'],
            'confidence' => $review['confidence'],
            'reused' => $reused,
            'scan' => [
                'read_at' => now()->toIso8601String(),
                'read_by' => auth()->id(),
                'model' => config('services.receipt_reader.model') ?: 'openai default',
                'reused' => $reused,
                'result' => $read,
            ],
        ]);
    }

    /**
     * @return array<\Filament\Schemas\Components\Component>
     */
    protected static function reviewSchema(Get $itemGet): array
    {
        $confidence = fn (string $field) => fn (Get $get): ?string => match ($get("confidence.{$field}")) {
            'low' => 'Hard to read: check this',
            'medium' => 'Check this',
            default => null,
        };

        return [
            Hidden::make('scan'),
            Hidden::make('confidence'),
            Hidden::make('warnings'),
            Hidden::make('suggested_purpose'),
            Hidden::make('reused'),

            Callout::make('Read earlier')
                ->description('This document was already read, so the saved result is shown. No new AI call was made.')
                ->info()
                ->visible(fn (Get $get): bool => (bool) $get('reused')),

            Callout::make('Check before applying')
                ->description(fn (Get $get): string => implode(' ', (array) $get('warnings')))
                ->warning()
                ->visible(fn (Get $get): bool => filled($get('warnings'))),

            Grid::make(['default' => 1, 'sm' => 2])->schema([
                TextInput::make('invoice_number')
                    ->label('Invoice No.')
                    ->maxLength(50)
                    ->hint($confidence('invoice_number'))
                    ->hintColor('warning'),
                DatePicker::make('invoice_date')
                    ->label('Invoice Date')
                    ->hint($confidence('invoice_date'))
                    ->hintColor('warning'),
                TextInput::make('description')
                    ->label('Item Details')
                    ->maxLength(150)
                    ->columnSpanFull(),
                Select::make('currency_id')
                    ->label('Currency')
                    ->options(fn (): array => RfpForm::currencyOptions())
                    ->searchable(),
                TextInput::make('amount')
                    ->label('Amount')
                    ->numeric()
                    ->minValue(0.01)
                    ->step(0.01)
                    ->hint($confidence('amount'))
                    ->hintColor('warning'),
                Select::make('rfp_subcategory_id')
                    ->label('Sub Category (suggested)')
                    ->helperText('Only sub categories that fit the item\'s cost center or PO. Leave empty to keep the item\'s.')
                    ->options(fn (): array => RfpForm::subcategoryOptions($itemGet('cost_center_id'), $itemGet('po_number')))
                    ->searchable()
                    ->columnSpanFull(),
                Textarea::make('purpose')
                    ->label('Purpose (for the whole RFP)')
                    ->rows(2)
                    ->autosize()
                    ->maxLength(1000)
                    ->helperText(fn (Get $get): ?string => filled($get('suggested_purpose')) && $get('suggested_purpose') !== $get('purpose')
                        ? "AI suggestion: {$get('suggested_purpose')}"
                        : null)
                    ->columnSpanFull(),
            ]),

            // Reimbursement lines record who was paid; match by TIN or use a one-time vendor
            Section::make('Paid to')
                ->compact()
                ->visible(fn (): bool => static::isReimbursement($itemGet))
                ->columns(['default' => 1, 'sm' => 2])
                ->schema([
                    TextInput::make('vendor_name')
                        ->label('Vendor name')
                        ->maxLength(150)
                        ->hint($confidence('vendor'))
                        ->hintColor('warning'),
                    TextInput::make('vendor_tin')
                        ->label('TIN')
                        ->maxLength(20)
                        ->live(onBlur: true),
                ])
                ->footer(fn (Get $get): string => ($vendor = app(ReceiptScanner::class)->vendorByTin($get('vendor_tin')))
                    ? "Matches vendor {$vendor->code} · {$vendor->name}."
                    : (filled($get('vendor_name')) && filled($get('vendor_tin'))
                        ? 'No vendor has this TIN: it will be used as a one-time vendor.'
                        : 'Not applied: enter the name and TIN to set who was paid.')),
        ];
    }

    /**
     * Copy the reviewed values onto the line, and keep what was read for audit.
     *
     * @param  array<string, mixed>  $data
     */
    protected static function apply(array $data, Set $set, Get $get): void
    {
        foreach (['invoice_number', 'invoice_date', 'description', 'currency_id', 'amount'] as $field) {
            if (filled($data[$field] ?? null)) {
                $set($field, $data[$field]);
            }
        }

        if (filled($data['purpose'] ?? null)) {
            $set('../../purpose', $data['purpose']);
        }

        if (filled($data['rfp_subcategory_id'] ?? null)) {
            $set('rfp_category_id', Subcategory::query()->whereKey($data['rfp_subcategory_id'])->value('rfp_category_id'));
            $set('rfp_subcategory_id', $data['rfp_subcategory_id']);
        }

        if (static::isReimbursement($get) && filled($data['vendor_name'] ?? null) && filled($data['vendor_tin'] ?? null)) {
            $scanner = app(ReceiptScanner::class);

            if ($vendor = $scanner->vendorByTin($data['vendor_tin'])) {
                $set('vendor_type', RfpVendorType::Vendor);
                $set('vendor_id', $vendor->id);
                $set('rfp_one_time_vendor_id', null);
            } else {
                $set('vendor_type', RfpVendorType::OneTimeVendor);
                $set('vendor_id', null);
                $set('rfp_one_time_vendor_id', $scanner->oneTimeVendorFor($data['vendor_name'], $data['vendor_tin'])->id);
            }
        }

        $set('receipt_scan', [
            ...(array) ($data['scan'] ?? []),
            'applied_at' => now()->toIso8601String(),
        ]);

        Notification::make()
            ->title('Applied to the item')
            ->body('Check the item before you submit.')
            ->success()
            ->send();
    }

    protected static function isReimbursement(Get $get): bool
    {
        $type = $get('../../request_type');

        return ($type instanceof RfpRequestType ? $type->value : $type) === RfpRequestType::Reimbursement->value;
    }
}
