<?php

namespace App\Filament\Pages\P2p;

use App\Filament\Pages\P2p\Actions\ReadInvoiceAction;
use App\Services\Rfp\ReceiptScanner;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Redesigned vendor "Bulk Goods Receipt" (Google Stitch), step 3 of invoicing: attach the invoice,
 * let AI read it, check the lines and submit to AP. Same schema, data, permissions and actions as
 * the old page it extends ({@see BulkGoodsReceipt}, kept untouched at its own URL); this page only
 * adds the step indicator, makes the invoice upload the obvious AI drop zone (as on the RFP form)
 * and pins the actions to the bottom.
 */
class VendorBulkGoodsReceipt extends BulkGoodsReceipt
{
    protected static ?string $slug = 'p2p/vendor-bulk-goods-receipt';

    protected static ?string $title = 'Bulk Goods Receipt';

    public function getSubheading(): string
    {
        return $this->isViewOnly
            ? 'Your submitted invoice and the goods receipts it bills.'
            : 'Attach your invoice and DR: AI reads them, fills Inv Qty and checks each line against the PO and GR. Then submit to Ramcar AP.';
    }

    public function content(Schema $schema): Schema
    {
        // Build the old page's components on a scratch schema: reading them back from $schema and
        // setting them again leaves its component lookup stale, so actions on fields (Read invoice
        // with AI on the upload) could no longer be mounted.
        $components = parent::content(Schema::make($this))->getComponents(withHidden: true);

        foreach ($components as $component) {
            $this->restyle($component);
        }

        return $schema->components([
            View::make('filament.pages.p2p.vendor.steps')->viewData(['current' => 3]),
            ...$components,
            View::make('filament.pages.p2p.vendor.bulk-goods-receipt-guide')->viewData(['steps' => $this->guideSteps()]),
        ]);
    }

    /**
     * @return list<array{target: string, title: string, body: string}>
     */
    public function guideSteps(): array
    {
        return [
            ['target' => 'steps', 'title' => 'Step 3 of 3', 'body' => 'Attach your invoice and submit it to Ramcar AP for the goods receipts you picked.'],
            ['target' => 'ai-upload', 'title' => 'Upload your invoice and DR', 'body' => 'Drop your invoice PDF here, plus the delivery receipt (DR) or other papers if you have them. You can add several files. Then click Read invoice with AI: it reads every file, takes Inv Qty from the invoice only, and tells you which lines don\'t match the PO or goods receipt. Check what it read before applying.'],
            ['target' => 'total', 'title' => 'Invoice details', 'body' => 'Enter the invoice date and your reference number (press Enter after each). The Overall Invoice Total adds up the lines you are billing.'],
            ['target' => 'actions', 'title' => 'Submit', 'body' => 'Submit to AP sends the invoice for checking. If a line is not 3-way matched the button says so, and you can add a remark for AP. Escalate raises a problem with the PO or goods receipt instead.'],
        ];
    }

    /**
     * Same condition as the Read invoice with AI button ({@see ReadInvoiceAction}).
     */
    protected function aiReaderAvailable(): bool
    {
        return ! $this->isViewOnly
            && config('services.invoice_reader.enabled')
            && filled(config('ai.providers.openai.key'));
    }

    /**
     * Read invoice with AI, with the vendor's invoice beside what was read: a 96% wide dialog, the
     * PDF on the left (same viewer as the RFP Read receipt), the review on the right scrolling on
     * its own. Same reading, matching and Apply as {@see ReadInvoiceAction}; only the layout differs.
     */
    protected function readInvoiceSideBySide(): Action
    {
        return ReadInvoiceAction::make()
            ->modalWidth(Width::Full)
            ->extraModalWindowAttributes([
                // 96% wide; the height leaves the top bar clear
                'class' => 'sf-ai-invoice-review',
                'style' => 'width: 96vw; max-width: 96vw; height: calc(100vh - 12rem); max-height: calc(100vh - 12rem);',
            ])
            ->schema([
                Hidden::make('match'),
                Grid::make(['default' => 1, 'lg' => 12])->schema([
                    View::make('filament.rfps.receipt-document-viewer')
                        ->viewData(fn (): array => [
                            'documents' => $this->invoiceDocuments(),
                            'emptyMessage' => 'Your invoice can\'t be shown here. Open it from Upload Attachments to compare.',
                        ])
                        ->columnSpan(['default' => 1, 'lg' => 4]),

                    // Scrolls on its own, so the invoice stays in view
                    Group::make([
                        ViewField::make('apply')
                            ->hiddenLabel()
                            ->view('filament.p2p.invoice-match-review')
                            ->viewData(fn (Get $get): array => [
                                'match' => $get('match') ?? [],
                                'rows' => $this->rowLabels(),
                            ]),
                    ])
                        ->extraAttributes(['class' => 'sf-receipt-review-pane'])
                        ->columnSpan(['default' => 1, 'lg' => 8]),
                ]),
            ]);
    }

    /**
     * The attached invoice PDFs as viewer documents: new uploads through the signed preview link
     * ({@see ReceiptScanner::previewFiles()}), saved ones from the public disk.
     *
     * @return list<array{url: string, mime: string, name: string}>
     */
    protected function invoiceDocuments(): array
    {
        return collect($this->attachments)
            ->map(fn (mixed $file): ?array => match (true) {
                $file instanceof TemporaryUploadedFile => ReceiptScanner::previewFiles([$file])[0] ?? null,
                is_string($file) && filled($file) => ['url' => Storage::disk('public')->url($file), 'mime' => 'application/pdf', 'name' => basename($file)],
                default => null,
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Restyle the parent's components in place, walking into sections, grids and repeaters.
     */
    protected function restyle(mixed $component): void
    {
        if (! $component instanceof Component) {
            return;
        }

        if ($component instanceof FileUpload && $component->getName() === 'attachments') {
            $component
                ->extraFieldWrapperAttributes(['class' => 'sf-ai-upload', 'data-tour' => 'ai-upload'])
                ->placeholder(fn (): ?string => $this->aiReaderAvailable()
                    ? '<span class="sf-ai-drop-title">✨ Drop your invoice and DR here or <span class="filepond--label-action">browse</span></span>'
                        .'<span class="sf-ai-drop-sub">Add several PDFs if you need to. AI reads them all, fills Inv Qty from the invoice and checks each line against the PO and GR.</span>'
                    : null)
                ->helperText($this->isViewOnly ? null : 'PDF only, up to 5 MB each. Inv Qty comes from the invoice; a DR or PO copy is only read to label its pages.')
                ->belowContent($this->readInvoiceSideBySide());
        }

        if ($component instanceof TextInput && $component->getName() === 'overall_invoice_total') {
            $component->prefix('₱')->extraFieldWrapperAttributes(['class' => 'sf-total-field', 'data-tour' => 'total']);
        }

        if ($component instanceof TextInput && $component->getName() === 'quantity') {
            $component->extraFieldWrapperAttributes(['class' => 'sf-qty-input']);
        }

        if ($component instanceof Actions && $component->getKey() === 'formActions') {
            $component->extraAttributes(['class' => 'sf-ap-actionbar sf-vendor-actions', 'data-tour' => 'actions']);
        }

        $children = $component->getDefaultChildComponents();

        foreach (is_array($children) ? $children : [] as $child) {
            $this->restyle($child);
        }
    }
}
