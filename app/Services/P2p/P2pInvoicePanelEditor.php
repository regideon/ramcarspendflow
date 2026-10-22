<?php

namespace App\Services\P2p;

use App\Enums\P2pStatus;
use App\Enums\RoleName;
use App\Models\P2p\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Editable fields for the P2P request split view.
 *
 * The pane only edits fields the portal owns:
 * - Vendor: invoice date and reference numbers (on a pending/returned request).
 * - AP Processor/Admin: tax code, WHT code, WHT base and AP notes (in the
 *   processor queue).
 *
 * Lines, attachments and every SAP-owned field stay on their dedicated screens.
 */
class P2pInvoicePanelEditor
{
    public function __construct(private readonly P2pTaxSummaryService $taxSummary) {}

    public function canEdit(Invoice $invoice, ?User $user): bool
    {
        return $this->canEditVendorFields($invoice, $user)
            || $this->canEditProcessorFields($invoice, $user);
    }

    public function canEditVendorFields(Invoice $invoice, ?User $user): bool
    {
        if ($user === null || ! $this->isVendorEditable($invoice)) {
            return false;
        }

        return $user->hasRole(RoleName::Admin)
            || ($user->hasRole(RoleName::Vendor) && $invoice->user_id === $user->id);
    }

    public function canEditProcessorFields(Invoice $invoice, ?User $user): bool
    {
        if ($user === null || ! $this->isProcessorEditable($invoice)) {
            return false;
        }

        if ($user->hasRole(RoleName::Admin)) {
            return true;
        }

        // The supervisor reviews and parks; they never edit the request fields.
        return $user->hasRole(RoleName::ApProcessor) && ! $user->hasRole(RoleName::ApSupervisor);
    }

    /**
     * @return array<string, mixed>
     */
    public function dataFor(Invoice $invoice): array
    {
        return [
            'invoice_at' => $invoice->invoice_at?->toDateString(),
            'reference_number' => implode(', ', $invoice->reference_number ?? []),
            'tax_code' => $invoice->tax_code,
            'withholding_tax_code' => $invoice->withholding_tax_code,
            'withholding_tax_base' => $invoice->withholding_tax_base,
            'remarks_ap' => $invoice->remarks_ap,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Invoice $invoice, array $data): Invoice
    {
        $user = auth()->user();

        return DB::transaction(function () use ($invoice, $data, $user): Invoice {
            /** @var Invoice $invoice */
            $invoice = Invoice::query()->whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();

            if (! $this->canEdit($invoice, $user)) {
                throw ValidationException::withMessages([
                    'record' => 'This request can no longer be edited by your account.',
                ]);
            }

            $canEditVendor = $this->canEditVendorFields($invoice, $user);
            $canEditProcessor = $this->canEditProcessorFields($invoice, $user);

            if ($canEditVendor) {
                $invoice->forceFill([
                    'invoice_at' => $data['invoice_at'] ?? null,
                    'reference_number' => $this->parseReferences($data['reference_number'] ?? null),
                    'updated_by' => $user?->id,
                ])->save();
            }

            if ($canEditProcessor) {
                $invoice->forceFill([
                    'tax_code' => $this->nullIfBlank($data['tax_code'] ?? null),
                    'withholding_tax_code' => $this->nullIfBlank($data['withholding_tax_code'] ?? null),
                    'withholding_tax_base' => $this->nullIfBlank($data['withholding_tax_base'] ?? null),
                    'remarks_ap' => $this->nullIfBlank($data['remarks_ap'] ?? null),
                    'updated_by' => $user?->id,
                ])->save();

                $this->taxSummary->recalculate($invoice);
            }

            return $invoice->fresh();
        });
    }

    protected function isVendorEditable(Invoice $invoice): bool
    {
        return in_array($invoice->status, [P2pStatus::Pending, P2pStatus::ReturnedToVendor], true);
    }

    protected function isProcessorEditable(Invoice $invoice): bool
    {
        return in_array($invoice->status, [
            P2pStatus::SubmittedToAp,
            P2pStatus::ApReview,
            P2pStatus::ReturnedToApProcessor,
        ], true);
    }

    /**
     * @return list<string>
     */
    protected function parseReferences(?string $references): array
    {
        return collect(explode(',', (string) $references))
            ->map(fn (string $reference): string => trim($reference))
            ->filter()
            ->values()
            ->all();
    }

    protected function nullIfBlank(mixed $value): mixed
    {
        return $value === '' || $value === null ? null : $value;
    }
}
