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
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;
use Throwable;

/**
 * "Read receipt" on a line's Supporting Documents: the AI reads the PDFs/images, the requestor
 * reviews and corrects the result in a modal beside the document, and only then is it applied.
 * Several receipts (several files, or several in one PDF) become one item each.
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
            ->visible(fn (): bool => static::available())
            ->modalHeading('Review what was read')
            ->modalDescription('Compare each value with the document on the left and correct it if needed. Applying replaces the item\'s current values; any further receipts are added as new items.')
            ->modalIcon(Heroicon::OutlinedDocumentMagnifyingGlass)
            // Near full screen so the document and the values fit side by side
            ->modalWidth(Width::Full)
            ->extraModalWindowAttributes(['style' => 'width: 94vw; max-width: 94vw; height: 94vh; max-height: 94vh;'])
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalSubmitActionLabel(fn (Action $action): string => ($count = count((array) ($action->getRawData()['receipts'] ?? []))) > 1
                ? "Apply {$count} receipts"
                : 'Apply to item')
            ->mountUsing(fn (Action $action, ?Schema $schema, FileUpload $component, Get $get, Component $livewire) => static::read($action, $schema, $component, $get, $livewire))
            ->schema(fn (Get $get): array => static::reviewSchema($get))
            ->action(fn (array $data, Set $set, Get $get, FileUpload $component) => static::apply($data, $set, $get, $component));
    }

    /**
     * Whether the AI reader is set up, so Read receipt (and the AI look of the upload) shows.
     */
    public static function available(): bool
    {
        return config('services.receipt_reader.enabled') && filled(config('ai.providers.openai.key'));
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

        $documents = ReceiptScanner::previewFiles($component->getRawState(), $component->getDiskName());
        $context = [
            'reimbursement' => static::isReimbursement($get),
            'rfp_id' => $livewire instanceof EditRecord ? $livewire->getRecord()->getKey() : null,
            'payee_id' => $get('../../vendor_id'),
        ];

        // Invoice no. + amount already on the form's other items, or on an earlier receipt here
        $seen = collect((array) $get('../../items'))
            ->except(static::itemKey($component))
            ->filter(fn (mixed $item): bool => is_array($item) && filled($item['invoice_number'] ?? null))
            ->mapWithKeys(fn (array $item): array => [static::duplicateKey($item['invoice_number'], $item['amount'] ?? null) => 'another item on this RFP'])
            ->all();

        $receipts = [];

        foreach (ReceiptScanner::receipts($read, count($files)) as $index => $receipt) {
            $review = $scanner->review($receipt, $subcategories, $context);
            $duplicateKey = static::duplicateKey($review['fields']['invoice_number'], $review['fields']['amount']);

            if (filled($review['fields']['invoice_number']) && isset($seen[$duplicateKey])) {
                $review['warnings'][] = "Invoice no. {$review['fields']['invoice_number']} for this amount is also on {$seen[$duplicateKey]}. Make sure it isn't billed twice.";
            }

            $seen[$duplicateKey] ??= 'receipt '.($index + 1).' here';

            $receipts[] = [
                ...$review['fields'],
                'warnings' => $review['warnings'],
                'confidence' => $review['confidence'],
                'file' => $receipt['file'],
                'page' => $receipt['page'],
                'file_name' => $documents[$receipt['file']]['name'] ?? null,
                'result' => $receipt,
            ];
        }

        // Purpose is for the whole RFP: keep what the requestor (or an earlier item) already wrote
        $currentPurpose = $get('../../purpose');
        $suggestedPurpose = $receipts[0]['purpose'] ?? null;

        $schema?->fill([
            'receipts' => $receipts,
            'documents' => $documents,
            'purpose' => filled($currentPurpose) ? $currentPurpose : $suggestedPurpose,
            'suggested_purpose' => $suggestedPurpose,
            'reused' => $reused,
            'scan' => [
                'read_at' => now()->toIso8601String(),
                'read_by' => auth()->id(),
                'model' => config('services.receipt_reader.model') ?: 'openai default',
                'reused' => $reused,
            ],
        ]);
    }

    /**
     * @return array<\Filament\Schemas\Components\Component>
     */
    protected static function reviewSchema(Get $itemGet): array
    {
        return [
            Hidden::make('scan'),
            Hidden::make('suggested_purpose'),
            Hidden::make('reused'),
            Hidden::make('documents'),

            Grid::make(['default' => 1, 'lg' => 5])->schema([
                // The document, to compare with what was read
                View::make('filament.rfps.receipt-document-viewer')
                    ->viewData(fn (Get $get): array => ['documents' => (array) $get('documents')])
                    ->columnSpan(['default' => 1, 'lg' => 3]),

                // Scrolls on its own, so the document stays in view
                Group::make([
                    Callout::make('Read earlier')
                        ->description('This document was already read, so the saved result is shown. No new AI call was made.')
                        ->info()
                        ->visible(fn (Get $get): bool => (bool) $get('reused')),

                    Callout::make(fn (Get $get): string => count((array) $get('receipts')).' receipts found')
                        ->description('Each receipt becomes its own item, with the same charging as this one. Remove any receipt you don\'t want to add.')
                        ->icon(Heroicon::OutlinedSquare2Stack)
                        ->color('primary')
                        ->visible(fn (Get $get): bool => count((array) $get('receipts')) > 1),

                    Repeater::make('receipts')
                        ->hiddenLabel()
                        ->schema(static::receiptFields($itemGet))
                        ->addable(false)
                        ->reorderable(false)
                        ->deletable(fn (Get $get): bool => count((array) $get('receipts')) > 1)
                        ->deleteAction(fn (Action $action): Action => $action->tooltip('Don\'t add this receipt'))
                        ->minItems(1)
                        ->itemLabel(fn (array $state, Get $get): ?string => count((array) $get('receipts')) > 1
                            ? collect([$state['invoice_number'] ?? 'No invoice no.', filled($state['amount'] ?? null) ? number_format((float) $state['amount'], 2) : null])->filter()->implode(' · ')
                            : null),

                    Textarea::make('purpose')
                        ->label('Purpose (for the whole RFP)')
                        ->rows(2)
                        ->autosize()
                        ->maxLength(1000)
                        ->helperText(fn (Get $get): ?string => filled($get('suggested_purpose')) && $get('suggested_purpose') !== $get('purpose')
                            ? "AI suggestion: {$get('suggested_purpose')}"
                            : null),
                ])
                    ->extraAttributes(['class' => 'sf-receipt-review-pane'])
                    ->columnSpan(['default' => 1, 'lg' => 2]),
            ]),
        ];
    }

    /**
     * What was read from one receipt, for the requestor to check and correct.
     *
     * @return array<\Filament\Schemas\Components\Component>
     */
    protected static function receiptFields(Get $itemGet): array
    {
        $confidence = fn (string $field) => fn (Get $get): ?string => match ($get("confidence.{$field}")) {
            'low' => 'Hard to read: check this',
            'medium' => 'Check this',
            default => null,
        };

        return [
            Hidden::make('confidence'),
            Hidden::make('warnings'),
            Hidden::make('file'),
            Hidden::make('page'),
            Hidden::make('file_name'),
            Hidden::make('result'),

            // Where the receipt is, with a button that shows it on the left
            View::make('filament.rfps.receipt-locate')
                ->viewData(fn (Get $get): array => ['file' => $get('file'), 'page' => $get('page'), 'name' => $get('file_name')])
                ->visible(fn (Get $get): bool => $get('file') !== null && (count((array) $get('../../documents')) > 1 || (int) $get('page') > 1 || count((array) $get('../../receipts')) > 1)),

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
     * Copy the reviewed values onto the line, and keep what was read for audit. Each further
     * receipt becomes a new item right after it, with the same charging and its own document.
     *
     * @param  array<string, mixed>  $data
     */
    protected static function apply(array $data, Set $set, Get $get, FileUpload $component): void
    {
        $receipts = array_values((array) ($data['receipts'] ?? []));

        if ($receipts === []) {
            return;
        }

        if (filled($data['purpose'] ?? null)) {
            $set('../../purpose', $data['purpose']);
        }

        $scan = (array) ($data['scan'] ?? []);
        $itemKey = static::itemKey($component);
        $items = (array) $get('../../items');
        $attachments = static::attachmentsByReceipt($receipts, (array) $component->getRawState(), $component->getDiskName());

        $isReimbursement = static::isReimbursement($get);
        $applied = fn (array $item, int $index): array => static::applyReceipt(
            $item,
            $receipts[$index],
            $attachments[$index],
            [...$scan, 'receipt' => $index + 1, 'receipts' => count($receipts), 'result' => $receipts[$index]['result'] ?? null],
            $isReimbursement,
        );

        // Further receipts: same charging as this item, but nothing from the first receipt
        $base = [...($items[$itemKey] ?? []), 'invoice_number' => null, 'invoice_date' => null, 'description' => null, 'amount' => null];

        // New items go right after this one, keyed as the items repeater keys them (list keys when faked in tests)
        $repeater = $component->getContainer()->getParentComponent();
        $uuids = ! ($repeater instanceof Repeater) || $repeater->generateUuid() !== null;
        $ordered = [];

        foreach ($items as $key => $item) {
            if ((string) $key !== $itemKey) {
                $ordered[$key] = $item;

                continue;
            }

            $ordered[$key] = $applied($item, 0);

            foreach (array_keys(array_slice($receipts, 1, preserve_keys: true)) as $index) {
                $ordered[($repeater instanceof Repeater ? $repeater->generateUuid() : null) ?? (string) Str::uuid()] = $applied($base, $index);
            }
        }

        $set('../../items', $uuids ? $ordered : array_values($ordered));

        Notification::make()
            ->title(count($receipts) > 1 ? count($receipts).' receipts applied' : 'Applied to the item')
            ->body(count($receipts) > 1
                ? 'Each receipt is now its own item, with its document. Check the items before you submit.'
                : 'Check the item before you submit.')
            ->success()
            ->send();
    }

    /**
     * One item's state with a reviewed receipt applied.
     *
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $receipt
     * @param  array<int|string, mixed>  $attachments
     * @param  array<string, mixed>  $scan
     * @return array<string, mixed>
     */
    protected static function applyReceipt(array $item, array $receipt, array $attachments, array $scan, bool $reimbursement): array
    {
        foreach (['invoice_number', 'invoice_date', 'description', 'currency_id', 'amount'] as $field) {
            if (filled($receipt[$field] ?? null)) {
                $item[$field] = $receipt[$field];
            }
        }

        if (filled($receipt['rfp_subcategory_id'] ?? null)) {
            $item['rfp_category_id'] = Subcategory::query()->whereKey($receipt['rfp_subcategory_id'])->value('rfp_category_id');
            $item['rfp_subcategory_id'] = $receipt['rfp_subcategory_id'];
        }

        if ($reimbursement && filled($receipt['vendor_name'] ?? null) && filled($receipt['vendor_tin'] ?? null)) {
            $scanner = app(ReceiptScanner::class);

            if ($vendor = $scanner->vendorByTin($receipt['vendor_tin'])) {
                $item['vendor_type'] = RfpVendorType::Vendor;
                $item['vendor_id'] = $vendor->id;
                $item['rfp_one_time_vendor_id'] = null;
            } else {
                $item['vendor_type'] = RfpVendorType::OneTimeVendor;
                $item['vendor_id'] = null;
                $item['rfp_one_time_vendor_id'] = $scanner->oneTimeVendorFor($receipt['vendor_name'], $receipt['vendor_tin'])->id;
            }
        }

        $item['attachments'] = $attachments;
        $item['receipt_scan'] = [...$scan, 'applied_at' => now()->toIso8601String()];

        return $item;
    }

    /**
     * The upload's files for each receipt: its own file, or every file when it isn't known. The
     * first receipt also keeps the files no receipt is on (e.g. an import permit). One receipt
     * keeps the upload as it is.
     *
     * @param  list<array<string, mixed>>  $receipts
     * @param  array<int|string, mixed>  $state
     * @return list<array<int|string, mixed>>
     */
    protected static function attachmentsByReceipt(array $receipts, array $state, string $disk): array
    {
        if (count($receipts) === 1) {
            return [$state];
        }

        $keys = array_column(ReceiptScanner::readableFiles($state, $disk), 'key');
        $fileKey = fn (array $receipt): int|string|null => is_int($receipt['file'] ?? null) ? ($keys[$receipt['file']] ?? null) : null;
        $used = array_filter(array_map($fileKey, $receipts), fn ($key): bool => $key !== null);

        return array_map(function (array $receipt, int $index) use ($state, $fileKey, $used): array {
            $key = $fileKey($receipt);

            if ($key === null) {
                return $state;
            }

            return array_filter(
                $state,
                fn (int|string $stateKey): bool => $stateKey === $key || ($index === 0 && ! in_array($stateKey, $used, true)),
                ARRAY_FILTER_USE_KEY,
            );
        }, $receipts, array_keys($receipts));
    }

    /**
     * The item's key in the items repeater (the last part of its state path).
     */
    protected static function itemKey(FileUpload $component): string
    {
        return Str::afterLast((string) $component->getContainer()->getStatePath(), '.');
    }

    protected static function duplicateKey(mixed $invoiceNumber, mixed $amount): string
    {
        return mb_strtolower(trim((string) $invoiceNumber)).'|'.(is_numeric($amount) ? number_format((float) $amount, 2, '.', '') : '');
    }

    protected static function isReimbursement(Get $get): bool
    {
        $type = $get('../../request_type');

        return ($type instanceof RfpRequestType ? $type->value : $type) === RfpRequestType::Reimbursement->value;
    }
}
