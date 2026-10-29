<?php

namespace App\Filament\Resources\RfpApprovals\Pages;

use App\Filament\Pages\Rfp\Approvals;
use App\Filament\Resources\RfpApprovals\RfpApprovalReviewResource;
use App\Filament\Resources\Rfps\Actions\AddCommentAction;
use App\Filament\Resources\Rfps\Schemas\RfpForm;
use App\Filament\Resources\Rfps\Schemas\RfpInfolist;
use App\Filament\Resources\Rfps\Widgets\RfpProgress;
use App\Models\Rfp\Item;
use Filament\Actions\Action;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Redesigned RFP approval screen (Google Stitch), opened from the new RFP Approvals queue
 * ({@see Approvals}). The record, header actions (Endorse, Process tax codes, Review and
 * approve, Final review, Release payment, Return, Cancel) and rules are ViewRfpApproval's;
 * only the layout differs, with the same actions repeated in a sticky decision bar.
 * Served at /hub/rfp/approvals/{record} (RfpApprovalReviewResource); the old screen stays at
 * /hub/rfp-approvals/{record} for the old list.
 */
class ReviewRfpApproval extends ViewRfpApproval
{
    protected static string $resource = RfpApprovalReviewResource::class;

    protected string $view = 'filament.pages.rfp.review-approval';

    /** Return / Cancel: the left of the decision bar; everything else is the decision itself. */
    public const array SECONDARY_ACTIONS = ['returnToStage', 'returnForRevision', 'cancel'];

    public function getHeading(): string|Htmlable
    {
        return new HtmlString(view('filament.pages.rfp.review-approval.heading', ['rfp' => $this->getRecord()])->render());
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [Approvals::getUrl() => 'RFP approvals', $this->getRecord()->rfp_number];
    }

    /**
     * The tracker is part of the page view instead of a widget.
     */
    protected function getHeaderWidgets(): array
    {
        return [];
    }

    public function addCommentAction(): Action
    {
        return AddCommentAction::make()->record($this->getRecord());
    }

    /**
     * @return list<array{label: string, who: string, state: string, note: ?string, icon: mixed}>
     */
    public function steps(): array
    {
        $widget = new RfpProgress;
        $widget->record = $this->getRecord();

        return $widget->getSteps();
    }

    public function approvalRoute(): string
    {
        $rfp = $this->getRecord();

        return RfpForm::approvalRoute($rfp->request_type, $rfp->total_php_amount, $rfp->is_direct_to_vp, $rfp->is_pre_approved);
    }

    public function hasTaxEntries(): bool
    {
        return $this->getRecord()->items->contains(fn (Item $item): bool => $item->tax_code_id !== null);
    }

    /**
     * Net of withholding tax per currency, e.g. ['PHP' => '2,271.40'].
     *
     * @return array<string, string>
     */
    public function netOfWithholdingTotals(): array
    {
        return $this->getRecord()->items
            ->groupBy(fn (Item $item): string => (string) $item->currency?->code)
            ->map(fn ($items): string => number_format((float) $items->reduce(fn (string $sum, Item $item): string => bcadd($sum, (string) ($item->net_of_withholding_tax_amount ?? '0'), 2), '0.00'), 2))
            ->all();
    }

    /**
     * User Guide stops (see resources/views/components/user-guide.blade.php).
     *
     * @return list<array{target: string, title: string, body: string}>
     */
    public function guideSteps(): array
    {
        return [
            ['target' => 'progress', 'title' => 'Where the RFP is', 'body' => 'The route from New request to Completed. Green steps are done, the indigo one is where the RFP is now, and the label under it says who holds it.'],
            ['target' => 'summary', 'title' => 'The request', 'body' => 'Status, due date, payee, paying company, total amount, the approval route and the purpose, as the requestor submitted them.'],
            ['target' => 'items', 'title' => 'Items and documents', 'body' => 'Each line with its cost center, invoice, who was paid and the amount. Click a document to open it. Tax entries appear once AP has processed the tax codes.'],
            ['target' => 'history', 'title' => 'History and comments', 'body' => 'Who did what and when, with their remarks. Add comment asks a question without changing the status; type @ to mention someone.'],
            ['target' => 'actions', 'title' => 'Your decision', 'body' => 'The buttons for your step stay at the bottom of the screen: the main action on the right, Return and Cancel on the left. Each asks for confirmation and remarks.'],
        ];
    }

    /**
     * @return array{primary: list<Action>, secondary: list<Action>}
     */
    public function decisionActions(): array
    {
        $actions = collect($this->getCachedHeaderActions())
            ->filter(fn ($action): bool => $action instanceof Action && $action->isVisible());

        return [
            'primary' => $actions->reject(fn (Action $action): bool => in_array($action->getName(), self::SECONDARY_ACTIONS, true))->values()->all(),
            'secondary' => $actions->filter(fn (Action $action): bool => in_array($action->getName(), self::SECONDARY_ACTIONS, true))->values()->all(),
        ];
    }

    public function revisionNote(): string
    {
        return RfpInfolist::revisionNote($this->getRecord());
    }

    /**
     * @return array<string, string>
     */
    public function foreignTotals(): array
    {
        return collect($this->getRecord()->foreignTotals())
            ->map(fn (string $amount, string $code): string => $code.' '.number_format((float) $amount, 2))
            ->all();
    }
}
