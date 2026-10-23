<?php

namespace App\Filament\Resources\RfpApprovals\Actions;

use App\Enums\RfpStatus;
use App\Filament\Resources\RfpApprovals\RfpApprovalResource;
use App\Models\Rfp\Rfp;
use App\Services\Rfp\ApprovalEngine;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

/**
 * Treasury, the last step (2026 flow): release the payment, which completes the RFP.
 * Treasury may correct the payment type first (legacy "Edit Payment Type"). Payment
 * date and reference no. are to come (decided 2026-10-23).
 */
class TreasuryActions
{
    /**
     * @return list<Action>
     */
    public static function make(): array
    {
        return [static::release()];
    }

    public static function release(): Action
    {
        return Action::make('releasePayment')
            ->label('Release payment')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->visible(fn (Rfp $record): bool => StepActions::builtStep($record)?->status === RfpStatus::ForTreasury)
            ->modalHeading(fn (Rfp $record): string => "Release payment for {$record->rfp_number}?")
            ->modalDescription(fn (Rfp $record): string => 'PHP '.number_format((float) $record->total_php_amount, 2).' to '.($record->vendor?->name ?? 'the payee').'. The request is then completed and the requestor is told.')
            ->fillForm(fn (Rfp $record): array => ['is_ids' => $record->is_ids])
            ->schema([
                Toggle::make('is_ids')
                    ->label('IDS')
                    ->helperText('Payment type. Off means Non-IDS (manual).'),
                Textarea::make('remarks')
                    ->label('Remarks (optional)')
                    ->rows(2),
            ])
            ->modalSubmitActionLabel('Release payment')
            ->action(function (Rfp $record, array $data, Action $action): void {
                DB::transaction(function () use ($record, $data): void {
                    $record->forceFill(['is_ids' => (bool) $data['is_ids']])->saveQuietly();

                    app(ApprovalEngine::class)->approve($record, auth()->user(), $data['remarks'] ?? null, verb: 'Payment released');
                });

                Notification::make()
                    ->title("Payment released for {$record->rfp_number}")
                    ->success()
                    ->send();

                $action->redirect(RfpApprovalResource::getUrl());
            });
    }
}
