<?php

namespace App\Filament\My\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sample notifications for the My panel's notification panel, pointing at SampleRfps.
 * Replaced by the user's database notifications once RFP events send them.
 */
class SampleNotifications
{
    /**
     * Filter chips: key => label.
     *
     * @var array<string, string>
     */
    public const array CATEGORIES = [
        'all' => 'All',
        'approvals' => 'Approvals',
        'requests' => 'My requests',
        'mentions' => 'Mentions',
    ];

    /**
     * Newest first. Approval notifications only for matrix approvers.
     *
     * @return Collection<int, array{id: string, type: string, category: string, title: string, body: string, number: string, at: Carbon, unread: bool, action: ?string}>
     */
    public static function for(User $user): Collection
    {
        $now = now();

        return collect([
            ['type' => 'waiting', 'category' => 'approvals', 'title' => 'Overdue: waiting for your approval', 'body' => 'Client visits – Cebu trip · Juan Dela Cruz · ₱38,450.00', 'number' => 'RFP-2026-001490', 'at' => $now->copy()->subMinutes(12), 'unread' => true, 'action' => 'Review'],
            ['type' => 'mention', 'category' => 'mentions', 'title' => 'Aileen Cruz mentioned you', 'body' => '“@Reg can you confirm the cost center for the Q4 fiber line before I route it?”', 'number' => 'RFP-2026-001475', 'at' => $now->copy()->subMinutes(48), 'unread' => true, 'action' => null],
            ['type' => 'returned', 'category' => 'requests', 'title' => 'Your RFP was returned', 'body' => 'Q4 Team Strategic Planning Venue · AP Review: Please attach the signed quotation.', 'number' => 'RFP-2026-001382', 'at' => $now->copy()->subHours(2), 'unread' => true, 'action' => 'Fix now'],
            ['type' => 'waiting', 'category' => 'approvals', 'title' => 'Due today: waiting for your approval', 'body' => 'Electricity – Pasig plant, October · Meralco · ₱245,000.00', 'number' => 'RFP-2026-001482', 'at' => $now->copy()->subDay()->setTime(16, 15), 'unread' => false, 'action' => 'Review'],
            ['type' => 'approved', 'category' => 'requests', 'title' => 'Line Manager approved your RFP', 'body' => 'Office supplies – October · Now with the Department Head.', 'number' => 'RFP-2026-001399', 'at' => $now->copy()->subDay()->setTime(10, 2), 'unread' => false, 'action' => null],
            ['type' => 'waiting', 'category' => 'approvals', 'title' => 'New RFP waiting for your approval', 'body' => 'Fiber internet – head office, Q4 · PLDT Enterprise · ₱412,800.00', 'number' => 'RFP-2026-001475', 'at' => $now->copy()->subDays(2)->setTime(9, 40), 'unread' => false, 'action' => null],
            ['type' => 'paid', 'category' => 'requests', 'title' => 'Your RFP was paid', 'body' => 'Annual AWS Cloud Reserved Instances · Treasury released the payment to Amazon Web Services.', 'number' => 'RFP-2026-001290', 'at' => $now->copy()->subDays(3)->setTime(15, 30), 'unread' => false, 'action' => null],
            ['type' => 'rejected', 'category' => 'requests', 'title' => 'Your RFP was rejected', 'body' => 'Team building transport · Division Head: Not in this year’s budget.', 'number' => 'RFP-2026-001188', 'at' => $now->copy()->subDays(5)->setTime(11, 5), 'unread' => false, 'action' => null],
        ])
            ->reject(fn (array $item): bool => $item['category'] === 'approvals' && ! $user->isMatrixApprover())
            ->values()
            ->map(fn (array $item, int $index): array => ['id' => 'n'.($index + 1), ...$item]);
    }

    /**
     * Grouped by day: "Today", "Yesterday", "Earlier".
     *
     * @return Collection<string, Collection<int, array<string, mixed>>>
     */
    public static function grouped(User $user): Collection
    {
        return static::for($user)->groupBy(fn (array $item): string => match (true) {
            $item['at']->isToday() => 'Today',
            $item['at']->isYesterday() => 'Yesterday',
            default => 'Earlier',
        });
    }
}
