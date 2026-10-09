<?php

namespace App\Filament\Resources\Rfps\Schemas;

use App\Enums\RfpRequestType;
use App\Models\Rfp\Item;
use App\Models\Rfp\Rfp;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

class RfpInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
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
                Section::make('History')
                    ->compact()
                    ->schema([
                        RepeatableEntry::make('histories')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('When'),
                                TableColumn::make('By'),
                                TableColumn::make('Status'),
                                TableColumn::make('Remarks'),
                            ])
                            ->schema([
                                TextEntry::make('created_at')->dateTime('M j, Y g:i A'),
                                TextEntry::make('user.name')->placeholder('System'),
                                TextEntry::make('to_status')->badge(),
                                TextEntry::make('remarks')->placeholder('—'),
                            ])
                            ->placeholder('No activity yet'),
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
}
