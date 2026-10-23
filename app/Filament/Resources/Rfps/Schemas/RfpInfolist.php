<?php

namespace App\Filament\Resources\Rfps\Schemas;

use App\Enums\RfpRequestType;
use App\Enums\RfpStatus;
use App\Filament\Resources\Rfps\Actions\AddCommentAction;
use App\Filament\Support\CommentMentions;
use App\Models\Comment;
use App\Models\Rfp\Item;
use App\Models\Rfp\Rfp;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class RfpInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Callout::make('Returned for revision')
                    ->description(fn (Rfp $record): string => static::revisionNote($record))
                    ->warning()
                    ->visible(fn (Rfp $record): bool => $record->status === RfpStatus::ForRevision),
                Section::make()
                    ->compact()
                    ->columns(['default' => 2, 'md' => 4])
                    ->schema([
                        TextEntry::make('status')->badge(),
                        TextEntry::make('dueType.name')
                            ->label('Due type')
                            ->badge()
                            ->color(fn (Rfp $record): string => $record->dueType?->code === 'urgent' ? 'danger' : 'gray'),
                        TextEntry::make('due_date')->date(),
                        TextEntry::make('request_type')->label('RFP type')->badge()->color('gray'),
                        TextEntry::make('is_ids')
                            ->label('Payment type')
                            ->state(fn (Rfp $record): string => $record->is_ids ? 'IDS' : 'Non-IDS (manual)')
                            ->badge()
                            ->color('gray')
                            ->visible(fn (Rfp $record): bool => in_array($record->status, [RfpStatus::ForTreasury, RfpStatus::Completed], true)),
                        TextEntry::make('company.name')->label('Paying company'),
                        TextEntry::make('accountGroup.description')->label('Account group')->placeholder('—'),
                        TextEntry::make('vendor.name')
                            ->label(fn (Rfp $record): string => $record->request_type === RfpRequestType::Reimbursement ? 'Final payee' : 'Payee'),
                        TextEntry::make('paymentMethod.description')->label('Payment method')->placeholder('—'),
                        IconEntry::make('is_direct_to_vp')->label('Direct to VP')->boolean(),
                        IconEntry::make('is_pre_approved')->label('Pre-approved')->boolean(),
                        TextEntry::make('total_php_amount')
                            ->label('Total amount')
                            ->numeric(decimalPlaces: 2)
                            ->prefix('PHP ')
                            ->weight('bold'),
                        TextEntry::make('foreign_totals')
                            ->label('Foreign currency')
                            ->state(fn (Rfp $record): array => collect($record->foreignTotals())
                                ->map(fn (string $amount, string $code): string => $code.' '.number_format((float) $amount, 2))
                                ->values()
                                ->all())
                            ->listWithLineBreaks()
                            ->placeholder('—'),
                        TextEntry::make('approval_route')
                            ->state(fn (Rfp $record): string => RfpForm::approvalRoute($record->request_type, $record->total_php_amount, $record->is_direct_to_vp, $record->is_pre_approved))
                            ->columnSpanFull(),
                        TextEntry::make('purpose')->columnSpanFull(),
                        TextEntry::make('pre_approval_documents')
                            ->label('Proof of approval')
                            ->visible(fn (Rfp $record): bool => $record->is_pre_approved)
                            ->listWithLineBreaks()
                            ->formatStateUsing(fn (string $state): HtmlString => static::fileLink($state))
                            ->placeholder('None')
                            ->columnSpanFull(),
                    ]),
                Section::make('Items')
                    ->compact()
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Item details'),
                                TableColumn::make('Cost center'),
                                TableColumn::make('PO no.'),
                                TableColumn::make('Sub category'),
                                TableColumn::make('Invoice'),
                                TableColumn::make('Paid to'),
                                TableColumn::make('Amount')->alignment(Alignment::End),
                                TableColumn::make('PHP')->alignment(Alignment::End),
                                TableColumn::make('Documents'),
                            ])
                            ->schema([
                                TextEntry::make('description'),
                                TextEntry::make('costCenter.code')->placeholder('—'),
                                TextEntry::make('po_number')->placeholder('—'),
                                TextEntry::make('subcategory.name')
                                    ->belowContent(fn (Item $record): ?string => $record->glAccount?->code),
                                TextEntry::make('invoice_number')
                                    ->belowContent(fn (Item $record): ?string => $record->invoice_date?->format('M j, Y'))
                                    ->placeholder('—'),
                                TextEntry::make('paid_to')
                                    ->state(fn (Item $record): ?string => $record->paidToName())
                                    ->placeholder('—'),
                                TextEntry::make('amount')
                                    ->state(fn (Item $record): string => $record->currency?->code.' '.number_format((float) $record->amount, 2))
                                    ->alignEnd(),
                                TextEntry::make('php_amount')->numeric(decimalPlaces: 2)->alignEnd(),
                                TextEntry::make('attachments')
                                    ->listWithLineBreaks()
                                    ->formatStateUsing(fn (string $state): HtmlString => static::fileLink($state))
                                    ->placeholder('None'),
                            ]),
                    ]),
                Section::make('Tax entries')
                    ->description('Booked by AP at processing. Amounts are in each line\'s currency.')
                    ->compact()
                    ->visible(fn (Rfp $record): bool => $record->items->contains(fn (Item $item): bool => $item->tax_code_id !== null))
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Invoice'),
                                TableColumn::make('Gross')->alignment(Alignment::End),
                                TableColumn::make('Input tax'),
                                TableColumn::make('Withholding tax'),
                                TableColumn::make('VAT')->alignment(Alignment::End),
                                TableColumn::make('Net of VAT')->alignment(Alignment::End),
                                TableColumn::make('Withholding')->alignment(Alignment::End),
                                TableColumn::make('Net of WHT')->alignment(Alignment::End),
                            ])
                            ->schema([
                                TextEntry::make('invoice_number')->placeholder('—'),
                                TextEntry::make('amount')
                                    ->state(fn (Item $record): string => $record->currency?->code.' '.number_format((float) $record->amount, 2))
                                    ->alignEnd(),
                                TextEntry::make('taxCode.code')
                                    ->belowContent(fn (Item $record): ?string => $record->taxCode?->description)
                                    ->placeholder('—'),
                                TextEntry::make('withholdingTaxCode.code')
                                    ->belowContent(fn (Item $record): ?string => $record->withholdingTaxCode
                                        ? "{$record->withholdingTaxCode->description} · {$record->withholdingTaxType?->code}"
                                        : null)
                                    ->placeholder('—'),
                                TextEntry::make('vat_amount')->numeric(decimalPlaces: 2)->alignEnd()->placeholder('—'),
                                TextEntry::make('net_of_vat_amount')->numeric(decimalPlaces: 2)->alignEnd()->placeholder('—'),
                                TextEntry::make('withholding_tax_amount')->numeric(decimalPlaces: 2)->alignEnd()->placeholder('—'),
                                TextEntry::make('net_of_withholding_tax_amount')->numeric(decimalPlaces: 2)->weight(FontWeight::Bold)->alignEnd()->placeholder('—'),
                            ]),
                        TextEntry::make('net_of_withholding_totals')
                            ->label('Total net of withholding tax')
                            ->state(fn (Rfp $record): array => $record->items
                                ->groupBy(fn (Item $item): string => (string) $item->currency?->code)
                                ->map(fn ($items, string $code): string => $code.' '.number_format((float) $items->reduce(fn (string $sum, Item $item): string => bcadd($sum, (string) ($item->net_of_withholding_tax_amount ?? '0'), 2), '0.00'), 2))
                                ->values()
                                ->all())
                            ->listWithLineBreaks()
                            ->weight(FontWeight::Bold),
                    ]),
                // History and Comments side by side (stacked on small screens)
                Grid::make(['default' => 1, 'lg' => 2])
                    ->schema([
                        Section::make('History')
                            ->compact()
                            ->schema([
                                RepeatableEntry::make('histories')
                                    ->hiddenLabel()
                                    ->table([
                                        TableColumn::make('When'),
                                        TableColumn::make('By'),
                                        TableColumn::make('Action'),
                                        TableColumn::make('Status'),
                                        TableColumn::make('Remarks'),
                                    ])
                                    ->schema([
                                        TextEntry::make('created_at')->dateTime('M j, Y g:i A'),
                                        TextEntry::make('user.name')->placeholder('System'),
                                        TextEntry::make('action')->placeholder('—'),
                                        TextEntry::make('to_status')->badge()->placeholder('—'),
                                        TextEntry::make('remarks')->placeholder('—'),
                                    ])
                                    ->placeholder('No activity yet'),
                            ]),
                        Section::make('Comments')
                            ->key('comments')
                            ->compact()
                            ->description('Questions and answers on this RFP. Posting a comment does not change the status.')
                            ->headerActions([AddCommentAction::make()])
                            ->schema([
                                RepeatableEntry::make('comments')
                                    ->hiddenLabel()
                                    ->schema([
                                        TextEntry::make('user.name')
                                            ->hiddenLabel()
                                            ->weight(FontWeight::SemiBold)
                                            ->belowContent(fn (Comment $record): string => $record->created_at->format('M j, Y g:i A')),
                                        TextEntry::make('body')
                                            ->hiddenLabel()
                                            ->formatStateUsing(fn (string $state): string => RichContentRenderer::make($state)
                                                ->mentions([CommentMentions::provider()])
                                                ->toHtml())
                                            ->html()
                                            ->prose(),
                                        TextEntry::make('attachments')
                                            ->hiddenLabel()
                                            ->listWithLineBreaks()
                                            ->formatStateUsing(fn (string $state): HtmlString => static::fileLink($state))
                                            ->visible(fn (Comment $record): bool => filled($record->attachments)),
                                    ])
                                    ->placeholder('No comments yet'),
                            ]),
                    ]),
            ]);
    }

    protected static function fileLink(string $path): HtmlString
    {
        return new HtmlString(sprintf(
            '<a href="%s" target="_blank" class="text-primary-600 hover:underline">%s</a>',
            e(Storage::disk('local')->temporaryUrl($path, now()->addMinutes(30))),
            e(basename($path)),
        ));
    }

    /**
     * Who returned the RFP and why, from the latest return in its history.
     */
    protected static function revisionNote(Rfp $rfp): string
    {
        $return = $rfp->histories()->where('to_status', RfpStatus::ForRevision)->latest('id')->with('user')->first();

        return trim(($return?->user?->name ? "{$return->user->name}: " : '').($return?->remarks ?? ''))
            .' Fix the request, then resubmit it.';
    }
}
