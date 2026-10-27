<?php

namespace App\Services\P2p\Escalation;

use App\Contracts\P2p\SapPurchaseOrderSource;
use App\Contracts\P2p\SapReversalSource;
use App\Models\P2p\Escalation;
use App\Models\P2p\EscalationReasonStep;
use App\Services\P2p\P2pGoodReceiptReversalService;
use App\Support\GoodReceiptNumber;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Callout;
use Throwable;

/**
 * Warehouse hop action: reverse the goods receipt in SAP and post a replacement.
 *
 * SAP's detected reversal/replacement is shown read-only. When SAP has not
 * recorded one yet, the Warehouse can enter the reversal document and re-GR
 * manually. Recording moves the request onto the replacement (or leaves the
 * current GR reversed) and forwards the escalation to the next hop.
 */
class ReverseAndReGrAction implements HasStepActionForm, StepAction
{
    public function handle(StepActionContext $context): StepActionCompletion
    {
        $escalation = $context->escalation->loadMissing(['goodReceiptState', 'invoice']);
        $state = $escalation->goodReceiptState;

        if ($state === null || $escalation->invoice === null) {
            return StepActionCompletion::done('No goods receipt is attached to this escalation.');
        }

        [$detectedReversal, $detectedReplacement] = $this->detect($escalation);

        $reversalDocument = filled($context->data['reversal_document'] ?? null)
            ? (string) $context->data['reversal_document']
            : $detectedReversal;

        $replacement = filled($context->data['replacement_gr_number'] ?? null)
            ? (string) $context->data['replacement_gr_number']
            : $detectedReplacement;

        if ($reversalDocument === null && $replacement === null) {
            Notification::make()
                ->title('Forwarded without a reversal')
                ->body('No reversal document or replacement GR was recorded. The escalation will move to the next hop.')
                ->warning()
                ->send();

            return $this->completionForNextHop($escalation, 'No reversal recorded.');
        }

        $reversal = app(P2pGoodReceiptReversalService::class);
        $reversal->apply($state, $reversalDocument, $replacement, cancelEscalations: false);

        if ($replacement !== null) {
            $reversal->substituteReplacementGrs($escalation->invoice, $state->good_receipt_number, [$replacement]);
        }

        Notification::make()
            ->title('Reversal recorded')
            ->body('Reversed '.GoodReceiptNumber::base($state->good_receipt_number)
                .($reversalDocument ? ' (document '.$reversalDocument.')' : '')
                .($replacement ? '; replacement GR '.$replacement.'.' : '.'))
            ->success()
            ->send();

        return $this->completionForNextHop($escalation, 'Reverse & re-GR recorded.');
    }

    /**
     * Continue along the route; when the next hop is the terminal one, close the
     * cycle on the way in (the terminal team has nothing left to action).
     */
    protected function completionForNextHop(Escalation $escalation, string $message): StepActionCompletion
    {
        return $escalation->nextStep()?->override_header_status
            ? StepActionCompletion::resolved($message)
            : StepActionCompletion::done($message);
    }

    public function schema(Escalation $escalation): array
    {
        $state = $escalation->goodReceiptState;
        [$reversal, $replacement] = $this->detect($escalation);

        $components = [];

        if ($state !== null) {
            $components[] = TextEntry::make('gr_number')
                ->label('Goods Receipt')
                ->state($state->good_receipt_number);
        }

        if ($reversal !== null || $replacement !== null) {
            $components[] = Callout::make('Reversal detected from SAP')
                ->description(trim(implode(' ', array_filter([
                    $reversal !== null ? 'Reversal document: '.$reversal.'.' : null,
                    $replacement !== null ? 'Replacement GR: '.$replacement.'.' : null,
                ]))))
                ->icon('heroicon-o-check-circle')
                ->color('info')
                ->columnSpanFull();
        } else {
            $components[] = Callout::make('No reversal detected from SAP')
                ->description('SAP has not recorded a reversal or replacement GR yet. Enter them manually if you have them — both are optional.')
                ->icon('heroicon-o-information-circle')
                ->color('warning')
                ->columnSpanFull();

            $components[] = TextInput::make('reversal_document')
                ->label('Reversal document (optional)')
                ->placeholder('e.g. 5001828584');

            $components[] = TextInput::make('replacement_gr_number')
                ->label('Replacement GR / re-GR (optional)')
                ->placeholder('e.g. 5001828661');
        }

        $components[] = Textarea::make('remarks')
            ->label('Warehouse remarks')
            ->helperText('Internal only — never shown to the vendor.')
            ->required()
            ->columnSpanFull();

        return $components;
    }

    public function modalHeading(Escalation $escalation): string
    {
        return 'Reverse goods receipt & re-GR';
    }

    public function modalDescription(Escalation $escalation): string
    {
        return 'Reversals already recorded in SAP are shown below. Record them, or enter the fallback manually.';
    }

    public function submitLabel(Escalation $escalation): string
    {
        return 'Record reversal';
    }

    public function label(Escalation $escalation): string
    {
        return 'Reverse & re-GR';
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    protected function detect(Escalation $escalation): array
    {
        $state = $escalation->goodReceiptState;

        if ($state === null) {
            return [null, null];
        }

        $base = GoodReceiptNumber::base($state->good_receipt_number);

        try {
            $source = app(SapPurchaseOrderSource::class);

            if ($source instanceof SapReversalSource) {
                $detected = $source->reversalsForPurchaseOrder($state->purchase_order);

                return [
                    $detected['reversed'][$base] ?? null,
                    $detected['replacements'][$base] ?? null,
                ];
            }
        } catch (Throwable) {
            // SAP unavailable: manual fallback remains available in the modal.
        }

        return [null, null];
    }

    public static function key(): string
    {
        return EscalationReasonStep::ACTION_REVERSE_REGR;
    }
}
