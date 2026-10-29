<?php

namespace App\Filament\Pages\P2p;

use App\Enums\RoleName;
use App\Models\P2p\Invoice;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Redesigned "Bulk Accounts Payable" (Google Stitch, docs/stitch/p2p-bulk-accounts-payable/):
 * the vendor's invoice on the left, the review on the right, a sticky action bar.
 * Same data, permissions and actions as the old page it extends; only the layout differs.
 * Read-only parts are drawn in Blade; the Tax fields and Link RFPs stay Filament forms.
 */
class ProcessInvoice extends BulkAccountsPayable
{
    protected string $view = 'filament.pages.p2p.process-invoice';

    protected static ?string $slug = 'p2p/process-invoice';

    public function getTitle(): string
    {
        return 'Bulk Accounts Payable';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('oldLayout')
                ->label('Old layout')
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('gray')
                ->url(fn (): string => BulkAccountsPayable::getUrl(['invoice' => $this->invoice])),
        ];
    }

    /**
     * Tax card: the same fields as the old page; each change recomputes the Summary.
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(12)
            ->schema($this->taxFields())
            ->statePath('data');
    }

    /**
     * RFP Down Payments card: the Link RFPs select.
     */
    public function rfpForm(Schema $schema): Schema
    {
        return $schema
            ->schema([$this->linkRfpsField()])
            ->statePath('data');
    }

    /**
     * Same field as the old page's RFP Down Payments section (copied: that page is left untouched).
     */
    protected function linkRfpsField(): Select
    {
        return Select::make('linked_rfp_ids')
            ->label('Link RFPs')
            ->multiple()
            ->searchable()
            ->options(fn (): array => $this->linkableRfpOptions())
            ->helperText(fn (): string => 'Only RFPs paid to '.($this->invoiceModel()?->vendor?->name ?? 'this vendor').' (Completed). Saved as you choose.')
            ->visible(fn (): bool => $this->canLinkRfps())
            ->live()
            ->afterStateUpdated(fn (?array $state) => $this->saveLinkedRfps($state ?? []))
            ->dehydrated(false);
    }

    /**
     * Same fields as the old page's Tax section (copied: that page is left untouched).
     *
     * @return array<int, mixed>
     */
    protected function taxFields(): array
    {
        return [
            Select::make('withholding_tax_code')
                ->label('Withholding Tax Code')
                ->options(fn (): array => self::withholdingTaxOptions())
                ->searchable()
                ->preload()
                ->disabled($this->isTaxSectionReadonly())
                // Disabled fields are not dehydrated by default, so without this a
                // supervisor's action would post null and wipe the processor's tax values.
                ->dehydrated()
                ->live(onBlur: true)
                ->afterStateUpdated(fn (Set $set) => $this->recalculateTaxSummary($set))
                ->columnSpan(['default' => 12, 'md' => 6]),

            TextInput::make('withholding_tax_base')
                ->label('Withholding Tax Base')
                ->numeric()
                ->prefix($this->currencySymbol)
                ->disabled($this->isTaxSectionReadonly())
                ->dehydrated()
                ->live(debounce: 800)
                ->afterStateUpdated(fn (Set $set) => $this->recalculateTaxSummary($set))
                ->columnSpan(['default' => 12, 'md' => 6]),

            Select::make('tax_code')
                ->label('Tax Code')
                ->options(fn (): array => self::taxCodeOptions())
                ->searchable()
                ->disabled($this->isTaxSectionReadonly())
                ->dehydrated()
                ->markAsRequired(! $this->isTaxSectionReadonly())
                ->live()
                ->afterStateUpdated(fn (Set $set) => $this->recalculateTaxSummary($set))
                ->columnSpan(['default' => 12]),

            Textarea::make('remarks_ap')
                ->label('AP Notes')
                ->disabled($this->isTaxSectionReadonly())
                ->dehydrated()
                ->columnSpan(['default' => 12]),
        ];
    }

    public function invoiceRecord(): ?Invoice
    {
        return $this->invoiceModel();
    }

    public function matchSummary(): string
    {
        return $this->invoiceMatchSummary()->toHtml();
    }

    public function linkedRfpsTable(): string
    {
        return $this->linkedRfpsHtml()->toHtml();
    }

    /**
     * The Report tick (escalate a line) shows only to an AP Processor who can act on the invoice.
     */
    public function showsReportColumn(): bool
    {
        return ! $this->isSupervisor() && ! $this->isViewOnly && $this->userHasRole(RoleName::ApProcessor->value);
    }

    /**
     * User Guide stops (see resources/views/components/user-guide.blade.php).
     *
     * @return list<array{target: string, title: string, body: string}>
     */
    public function guideSteps(): array
    {
        return [
            ['target' => 'invoice', 'title' => 'Attached Invoice', 'body' => 'The vendor\'s invoice. Keep it open while you check the numbers on the right. Use File 1 / File 2 when the vendor attached more than one file, or open it in a new tab to zoom.'],
            ['target' => 'match-alert', 'title' => 'Lines to check', 'body' => 'What the AI found when it read the vendor\'s invoice: lines that don\'t match, the vendor\'s remarks, and charges that are not on any PO. Check these first.'],
            ['target' => 'accounts-payable', 'title' => 'Accounts Payable', 'body' => 'The vendor, company, invoice date, reference and total. The Invoice No. is filled in by SAP when the invoice is posted.'],
            ['target' => 'items', 'title' => 'Items', 'body' => 'Each goods receipt with its lines. Compare PO Qty with Invoice Qty and the unit cost (UC). The 3-Way badge shows if PO, goods receipt and invoice agree; click it for the breakdown. Tick Report on a line to escalate it.'],
            ['target' => 'rfp-down-payments', 'title' => 'RFP Down Payments', 'body' => 'Link the vendor\'s paid down-payment RFPs to this invoice, for reference. Nothing is deducted.'],
            ['target' => 'tax', 'title' => 'Tax and Summary', 'body' => 'Pick the Tax Code (required) and the Withholding Tax Code. The Summary updates right away and shows the NET amount to pay.'],
            ['target' => 'actions', 'title' => 'Actions', 'body' => 'Submit sends the invoice to the checker. Escalate sends the reported lines to Procurement or the Warehouse. Return to Vendor sends it back with your remarks.'],
        ];
    }
}
