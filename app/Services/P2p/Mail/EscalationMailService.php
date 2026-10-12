<?php

namespace App\Services\P2p\Mail;

use App\Enums\RoleName;
use App\Mail\P2p\EscalatedInvoiceNoticeMail;
use App\Mail\P2p\EscalateToProcurementMail;
use App\Models\P2p\Escalation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

/**
 * Queues the escalation emails: an action-required mail to the current hop's
 * handlers, and a receipt notice to the originator. Legacy sent these
 * synchronously; here everything is queued (docs/p2p/06-workflow-statuses.md §6).
 */
class EscalationMailService
{
    /**
     * Map an escalation hop role to the app's Spatie role.
     */
    private const array ROLE_ALIASES = [
        'procurement' => RoleName::Procurement,
        'warehouse' => RoleName::Warehouse,
        'processor' => RoleName::ApProcessor,
    ];

    public function sendForCurrentHop(Escalation $escalation): void
    {
        foreach ($this->recipientsFor($escalation) as $email) {
            Mail::to($email)->queue(new EscalateToProcurementMail($escalation));
        }
    }

    public function sendOriginNotice(Escalation $escalation): void
    {
        $email = $escalation->invoice?->vendor?->email;

        if ($email !== null) {
            Mail::to($email)->queue(new EscalatedInvoiceNoticeMail($escalation));
        }
    }

    /**
     * @return Collection<int, string>
     */
    protected function recipientsFor(Escalation $escalation): Collection
    {
        $role = $escalation->current_role;

        if ($role === null) {
            return collect();
        }

        if ($role === 'vendor') {
            return collect($escalation->invoice?->vendor?->email)->filter();
        }

        $roleName = self::ROLE_ALIASES[$role] ?? null;

        if ($roleName === null) {
            return collect();
        }

        // Use whereHas so a not-yet-seeded role does not throw.
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', $roleName->value))
            ->pluck('email');
    }
}
