<?php

namespace App\Filament\Pages\P2p\Actions;

use App\Filament\Pages\P2p\BulkGoodsReceipt;
use App\Services\P2p\P2pInvoiceMatcher;
use App\Services\P2p\P2pInvoiceScanner;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\ViewField;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

/**
 * "Read invoice with AI" under the vendor's PDF upload on Process Goods Receipts: reads every
 * attached PDF (VendorInvoiceReader, saved reads reused), matches the invoice lines to the GR
 * lines on screen (P2pInvoiceMatcher), and shows the result for review before it is applied.
 */
class ReadInvoiceAction
{
    public static function make(): Action
    {
        return Action::make('readInvoice')
            ->label('Read invoice with AI')
            ->icon(Heroicon::Sparkles)
            ->button()
            ->color('primary')
            ->size('lg')
            // Hidden (not removed) without a PDF, so a button left on screen still explains itself
            ->extraAttributes(fn (FileUpload $component): array => [
                'style' => 'width: 100%; justify-content: center;'.(static::pdfs($component) === [] ? ' display: none;' : ''),
            ])
            ->visible(fn (BulkGoodsReceipt $livewire): bool => ! $livewire->isViewOnly
                && config('services.invoice_reader.enabled')
                && filled(config('ai.providers.openai.key')))
            ->modalHeading('Review what was read from your invoice')
            ->modalDescription('Each GR line is matched to a line on your invoice. Tick the lines to apply; red lines don\'t reconcile. You can still edit Inv Qty afterwards.')
            ->modalIcon(Heroicon::OutlinedDocumentMagnifyingGlass)
            ->modalWidth('7xl')
            ->modalSubmitActionLabel('Apply to the GR lines')
            ->mountUsing(fn (Action $action, ?Schema $schema, FileUpload $component, BulkGoodsReceipt $livewire) => static::read($action, $schema, $component, $livewire))
            ->schema([
                Hidden::make('match'),
                // The ticks: GR lines to apply, keyed by GR item id
                ViewField::make('apply')
                    ->hiddenLabel()
                    ->view('filament.p2p.invoice-match-review')
                    ->viewData(fn (Get $get, BulkGoodsReceipt $livewire): array => [
                        'match' => $get('match') ?? [],
                        'rows' => $livewire->rowLabels(),
                    ]),
            ])
            ->action(function (array $data, BulkGoodsReceipt $livewire): void {
                $selected = array_keys(array_filter((array) ($data['apply'] ?? [])));

                if ($selected === []) {
                    Notification::make()->title('No GR lines ticked')->body('Nothing was applied.')->warning()->send();

                    return;
                }

                $livewire->applyInvoiceMatch((array) ($data['match'] ?? []), $selected);

                Notification::make()
                    ->title('Invoice applied')
                    ->body('Check Inv Qty and the 3-way badges before you submit.')
                    ->success()
                    ->send();
            });
    }

    protected static function read(Action $action, ?Schema $schema, FileUpload $component, BulkGoodsReceipt $livewire): void
    {
        $files = static::pdfs($component);

        if ($files === []) {
            Notification::make()
                ->title('Upload your invoice first')
                ->body('Attach the invoice as a PDF, then click Read invoice with AI.')
                ->warning()
                ->send();

            $action->cancel();
        }

        $scanner = app(P2pInvoiceScanner::class);
        $key = 'invoice-reader:'.auth()->id();
        $invoices = [];
        $read = [];

        foreach ($files as $file) {
            $result = $scanner->savedRead($file['path']);
            $reused = $result !== null;

            if (! $reused) {
                if (RateLimiter::tooManyAttempts($key, (int) config('services.invoice_reader.per_hour'))) {
                    Notification::make()
                        ->title('Too many invoices read')
                        ->body('Try again in '.ceil(RateLimiter::availableIn($key) / 60).' minutes, or enter Inv Qty yourself.')
                        ->warning()
                        ->send();

                    $action->cancel();
                }

                RateLimiter::hit($key, 3600);

                try {
                    $result = $scanner->read($file['path']);
                } catch (Throwable $exception) {
                    report($exception);

                    Notification::make()
                        ->title('Couldn\'t read '.$file['name'])
                        ->body('The reader is unavailable or the PDF couldn\'t be read. Enter Inv Qty yourself, or try again later.')
                        ->danger()
                        ->send();

                    $action->cancel();
                }
            }

            array_push($invoices, ...($result['invoices'] ?? []));
            $read[] = [
                'name' => $file['name'],
                'reused' => $reused,
                'pages' => array_column($result['pages'] ?? [], 'type'),
            ];
        }

        $match = app(P2pInvoiceMatcher::class)->match($invoices, $livewire->itemsOnScreen(), $livewire->currentInvoice());

        $schema?->fill([
            'match' => [...$match, 'files' => $read],
            'apply' => array_fill_keys(array_keys($match['rows']), true), // every matched line ticked
        ]);
    }

    /**
     * The attached PDFs (new uploads or saved files), with a readable name.
     *
     * @return list<array{path: string, name: string}>
     */
    public static function pdfs(FileUpload $component): array
    {
        $disk = Storage::disk($component->getDiskName());

        return collect($component->getRawState() ?? [])
            ->map(function (mixed $file) use ($disk): ?array {
                try {
                    return match (true) {
                        $file instanceof TemporaryUploadedFile && $file->getMimeType() === 'application/pdf' => ['path' => $file->getRealPath(), 'name' => $file->getClientOriginalName()],
                        is_string($file) && $disk->exists($file) && $disk->mimeType($file) === 'application/pdf' => ['path' => $disk->path($file), 'name' => basename($file)],
                        default => null,
                    };
                } catch (Throwable) {
                    return null;
                }
            })
            ->filter()
            ->values()
            ->all();
    }
}
