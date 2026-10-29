<?php

namespace App\Filament\Pages\P2p;

use App\Enums\RoleName;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

/**
 * Redesigned AP review (Google Stitch), opened from the new P2P Approvals queue
 * ({@see ApprovalQueue}). Everything but the layout comes from {@see BulkAccountsPayable},
 * which stays as it is for the old queue: the same data, tax maths, RFP linking, footer
 * actions (Submit, Escalate, Return, Approve, escalation hops) and rules. Only the form
 * layout, the view and where Cancel goes are overridden.
 *
 *   [ Invoice viewer ]  [ Lines to check (Read invoice with AI) ]
 *    ^ sticky           [ Accounts Payable                       ]
 *                       [ Items                                  ]
 *                       [ RFP Down Payments                      ]
 *                       [ Tax ]            [ Summary              ]
 *                       [ Approval History                       ]
 *   [ Cancel                         Return · Escalate · Submit  ] sticky
 */
class ReviewAccountsPayable extends BulkAccountsPayable
{
    protected string $view = 'filament.pages.p2p.review-accounts-payable';

    protected static ?string $slug = 'p2p/approval-queue/review';

    public function getTitle(): string
    {
        return 'Bulk Accounts Payable';
    }

    public function mount(): void
    {
        if ($this->invoiceModel() === null) {
            $this->redirect(ApprovalQueue::getUrl());

            return;
        }

        parent::mount();
    }

    /**
     * Same fields and state as the old screen, laid out as the Stitch design. The Items
     * repeater keeps the Report ticks that Escalate reads, so it is the same definition.
     */
    public function form(Schema $schema): Schema
    {
        $showReportColumn = ! $this->isSupervisor() && ! $this->isViewOnly && $this->userHasRole(RoleName::ApProcessor->value);
        $showEscalationColumn = $this->showEscalationColumn;

        return $schema
            ->schema([
                View::make('filament.pages.p2p.review-accounts-payable.match-alert')
                    ->viewData(fn (): array => ['groups' => $this->matchGroups(), 'count' => (int) ($this->invoiceModel()?->match_flag_count ?? 0)])
                    ->visible(fn (): bool => $this->matchGroups() !== []),

                View::make('filament.pages.p2p.review-accounts-payable.accounts-payable')
                    ->viewData(fn (): array => ['data' => $this->data, 'currency' => $this->currencySymbol]),

                Section::make('Items')
                    ->description('The items selected for purchase')
                    ->extraAttributes(['class' => 'sf-ap-card', 'data-tour' => 'items'])
                    ->schema([
                        Repeater::make('goods_receipts')
                            ->hiddenLabel()
                            ->default($this->data['goods_receipts'] ?? [])
                            ->schema([
                                Hidden::make('p2p_good_receipt_state_id'),
                                Hidden::make('purchase_order'),
                                Hidden::make('good_receipt_number'),

                                View::make('filament.pages.p2p.review-accounts-payable.gr-chips')
                                    ->viewData(fn (Get $get): array => ['gr' => $get('good_receipt_number'), 'po' => $get('purchase_order')]),

                                Repeater::make('goodReceiptItems')
                                    ->compact()
                                    ->hiddenLabel()
                                    ->table([
                                        ...($showReportColumn ? [TableColumn::make('Report')] : []),
                                        ...($showEscalationColumn ? [TableColumn::make('Scope')] : []),
                                        TableColumn::make('Short Text'),
                                        TableColumn::make('Item No.'),
                                        TableColumn::make('PO Qty'),
                                        TableColumn::make('Uom'),
                                        TableColumn::make('Invoice Qty'),
                                        TableColumn::make('UC'),
                                        TableColumn::make('Amount'),
                                        TableColumn::make('3-Way'),
                                    ])
                                    ->schema([
                                        ...($showReportColumn ? [
                                            Checkbox::make('escalate')
                                                ->hiddenLabel()
                                                ->default(false)
                                                ->extraFieldWrapperAttributes(['style' => 'justify-items: center;']),
                                        ] : []),

                                        ...($showEscalationColumn ? [
                                            TextEntry::make('escalation_status')
                                                ->hiddenLabel()
                                                ->badge()
                                                ->color(fn ($state): string => $state === 'Escalated' ? 'warning' : 'gray'),
                                        ] : []),

                                        TextEntry::make('item_description')->hiddenLabel()->weight('medium')->wrap(),
                                        TextEntry::make('purchase_order_item')->hiddenLabel()->fontFamily('mono')->color('gray'),
                                        TextEntry::make('quantity_po')->hiddenLabel()->fontFamily('mono'),
                                        TextEntry::make('uom')->hiddenLabel()->color('gray'),
                                        TextEntry::make('quantity_served')->hiddenLabel()->fontFamily('mono'),
                                        TextEntry::make('unit_cost')->hiddenLabel()->fontFamily('mono'),
                                        TextEntry::make('amount')->hiddenLabel()->fontFamily('mono')->weight('semibold'),

                                        ViewField::make('match_detail')
                                            ->hiddenLabel()
                                            ->view('filament.components.three-way-match-badge')
                                            ->viewData(fn ($state): array => is_array($state) ? $state : []),
                                    ])
                                    ->addable(false)
                                    ->deletable(false)
                                    ->reorderable(false),

                                View::make('filament.pages.p2p.review-accounts-payable.gr-totals')
                                    ->viewData(fn (Get $get): array => [
                                        'gr' => $get('good_receipt_number'),
                                        'items' => (array) ($get('goodReceiptItems') ?? []),
                                        'currency' => $this->currencySymbol,
                                    ]),
                            ])
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ]),

                // Reference only: the vendor's paid RFPs (usually down payments) linked to this invoice
                Section::make('RFP Down Payments')
                    ->description('Link the vendor\'s paid RFPs, usually supplier down payments, to this invoice. For reference only: no amounts or entries change.')
                    ->extraAttributes(['class' => 'sf-ap-card'])
                    ->schema([
                        Select::make('linked_rfp_ids')
                            ->label('Link RFPs')
                            ->multiple()
                            ->searchable()
                            ->options(fn (): array => $this->linkableRfpOptions())
                            ->helperText(fn (): string => 'Only RFPs paid to '.($this->invoiceModel()?->vendor?->name ?? 'this vendor').' (Completed). Saved as you choose.')
                            ->visible(fn (): bool => $this->canLinkRfps())
                            ->live()
                            ->afterStateUpdated(fn (?array $state) => $this->saveLinkedRfps($state ?? []))
                            ->dehydrated(false),
                        TextEntry::make('linked_rfps_list')
                            ->hiddenLabel()
                            ->state(fn (): HtmlString => $this->linkedRfpsHtml())
                            ->html(),
                    ]),

                Grid::make(['default' => 1, 'md' => 2])->schema([
                    Section::make('Tax')
                        ->extraAttributes(['class' => 'sf-ap-card', 'data-tour' => 'tax'])
                        ->schema([
                            Select::make('withholding_tax_code')
                                ->label('Withholding Tax Code')
                                ->options(fn (): array => self::withholdingTaxOptions())
                                ->searchable()
                                ->preload()
                                ->disabled($this->isTaxSectionReadonly())
                                // Disabled fields are not dehydrated by default; keep the processor's values
                                ->dehydrated()
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn (Set $set) => $this->recalculateTaxSummary($set)),

                            TextInput::make('withholding_tax_base')
                                ->label('Withholding Tax Base')
                                ->numeric()
                                ->prefix($this->currencySymbol)
                                ->disabled($this->isTaxSectionReadonly())
                                ->dehydrated()
                                ->live(debounce: 800)
                                ->afterStateUpdated(fn (Set $set) => $this->recalculateTaxSummary($set)),

                            Select::make('tax_code')
                                ->label('Tax Code')
                                ->options(fn (): array => self::taxCodeOptions())
                                ->searchable()
                                ->disabled($this->isTaxSectionReadonly())
                                ->dehydrated()
                                ->markAsRequired(! $this->isTaxSectionReadonly())
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $this->recalculateTaxSummary($set)),

                            Textarea::make('remarks_ap')
                                ->label('AP Notes')
                                ->rows(3)
                                ->disabled($this->isTaxSectionReadonly())
                                ->dehydrated(),
                        ]),

                    // Live from the tax selection (recalculateTaxSummary writes these into $data)
                    View::make('filament.pages.p2p.review-accounts-payable.summary')
                        ->viewData(fn (): array => ['data' => $this->data, 'currency' => $this->currencySymbol]),
                ]),

                Section::make('Approval History')
                    ->description('All actions and remarks related to this goods receipt')
                    ->extraAttributes(['class' => 'sf-ap-card'])
                    ->collapsible()
                    ->schema([
                        ViewField::make('approval_history_timeline')
                            ->hiddenLabel()
                            ->view('filament.components.approval-history-timeline')
                            ->viewData(fn (): array => ['record' => $this->invoiceModel()])
                            ->dehydrated(false),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * The attached invoice(s) for the viewer: public-disk files, PDFs and images.
     *
     * @return list<array{url: string, mime: string, name: string}>
     */
    public function documents(): array
    {
        return collect($this->data['attachments'] ?? [])
            ->map(fn ($attachment) => is_array($attachment) ? ($attachment['path'] ?? null) : $attachment)
            ->filter(fn ($path): bool => is_string($path) && $path !== '')
            ->map(fn (string $path): array => [
                'url' => Storage::disk('public')->url($path),
                'mime' => match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    default => 'application/pdf',
                },
                'name' => basename($path),
            ])
            ->values()
            ->all();
    }

    /**
     * Read invoice with AI, as titled groups for the alert card (same content as the old
     * screen's callout).
     *
     * @return array<string, list<string>>
     */
    public function matchGroups(): array
    {
        $match = $this->invoiceModel()?->invoice_match ?? [];

        return array_filter([
            'Lines' => collect($match['flags'] ?? [])->map(fn (array $flag): string => sprintf(
                'GR %s · %s (PO %s/%s): %s',
                $flag['gr_number'] ?? '—',
                $flag['item_name'] ?? '—',
                $flag['purchase_order'] ?? '—',
                $flag['purchase_order_item'] ?? '—',
                implode(' ', $flag['reasons'] ?? []),
            ))->all(),
            'Vendor\'s remarks' => filled($match['vendor_remarks'] ?? null) ? [(string) $match['vendor_remarks']] : [],
            'Invoice' => array_values((array) ($match['warnings'] ?? [])),
            'On the invoice but not billed here' => collect($match['unmatched_lines'] ?? [])->map(fn (array $line): string => sprintf(
                '%s: %s · qty %s @ %s',
                $line['invoice_number'] ?? '—',
                $line['description'] ?? '—',
                $line['quantity'] ?? '—',
                is_numeric($line['unit_price'] ?? null) ? number_format((float) $line['unit_price'], 2) : '—',
            ))->all(),
            'Charges not on any PO' => collect($match['charges'] ?? [])->map(fn (array $charge): string => sprintf(
                '%s: %s · %s',
                $charge['invoice_number'] ?? '—',
                $charge['description'] ?? 'Charge',
                number_format((float) ($charge['amount'] ?? 0), 2),
            ))->all(),
        ]);
    }

    /**
     * User Guide stops (see resources/views/components/user-guide.blade.php).
     *
     * @return list<array{target: string, title: string, body: string}>
     */
    public function guideSteps(): array
    {
        return [
            ['target' => 'viewer', 'title' => 'The vendor\'s invoice', 'body' => 'The attached invoice stays on the left while you check the values on the right. Switch files, zoom photos, or open it in a new tab.'],
            ['target' => 'match', 'title' => 'Lines to check', 'body' => 'What Read invoice with AI found: lines that don\'t match the invoice, the vendor\'s remarks, and charges not on any PO. Check these first.'],
            ['target' => 'items', 'title' => 'Items and the 3-way match', 'body' => 'Each goods receipt with its lines: PO quantity, invoice quantity, unit cost and amount, and whether the PO, goods receipt and invoice match. Tick Report on a line to include it when you escalate.'],
            ['target' => 'tax', 'title' => 'Tax and summary', 'body' => 'Choose the withholding tax and tax code. The summary, down to the net payable, updates as you choose. A tax code is needed to submit.'],
            ['target' => 'actions', 'title' => 'Your decision', 'body' => 'Submit sends it to the AP Checker. Escalate sends it to Procurement or the Warehouse. Return to Vendor sends it back for correction. Cancel goes back to the queue without changes.'],
        ];
    }

    protected function redirectBack(): void
    {
        $this->redirect(ApprovalQueue::getUrl());
    }
}
