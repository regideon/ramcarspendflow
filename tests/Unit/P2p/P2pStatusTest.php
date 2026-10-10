<?php

use App\Enums\P2pPurchaseOrderStatus;
use App\Enums\P2pStatus;

it('replicates the legacy goods-receipt status ids', function (int $id, P2pStatus $status) {
    expect(P2pStatus::tryFrom($id))->toBe($status)
        ->and($status->value)->toBe($id);
})->with([
    'pending' => [1, P2pStatus::Pending],
    'submitted to ap' => [2, P2pStatus::SubmittedToAp],
    'submitted to checker' => [4, P2pStatus::SubmittedToChecker],
    'completed' => [6, P2pStatus::Completed],
    'invoice parked' => [13, P2pStatus::InvoiceParked],
    'invoice posted' => [14, P2pStatus::InvoicePosted],
    'returned to vendor' => [15, P2pStatus::ReturnedToVendor],
    'escalated to warehouse' => [18, P2pStatus::EscalatedToWarehouse],
    'partially escalated' => [19, P2pStatus::PartiallyEscalated],
    'reversed' => [20, P2pStatus::Reversed],
    'pending post' => [21, P2pStatus::PendingPost],
]);

it('marks the reserved / legacy statuses', function () {
    expect(P2pStatus::ApReview->isReserved())->toBeTrue()
        ->and(P2pStatus::CheckerReview->isReserved())->toBeTrue()
        ->and(P2pStatus::PendingPost->isReserved())->toBeFalse();
});

it('only lets a vendor edit pending or returned records', function () {
    expect(P2pStatus::Pending->isEditableByVendor())->toBeTrue()
        ->and(P2pStatus::ReturnedToVendor->isEditableByVendor())->toBeTrue()
        ->and(P2pStatus::SubmittedToAp->isEditableByVendor())->toBeFalse();
});

it('identifies escalation-statuses', function () {
    expect(P2pStatus::EscalateToProcurement->isEscalated())->toBeTrue()
        ->and(P2pStatus::EscalatedToWarehouse->isEscalated())->toBeTrue()
        ->and(P2pStatus::SubmittedToAp->isEscalated())->toBeFalse();
});

it('replicates the legacy purchase-order status ids', function () {
    expect(P2pPurchaseOrderStatus::Pending->value)->toBe(0)
        ->and(P2pPurchaseOrderStatus::Completed->value)->toBe(1)
        ->and(P2pPurchaseOrderStatus::PartiallySubmitted->value)->toBe(2)
        ->and(P2pPurchaseOrderStatus::FullySubmitted->value)->toBe(3);
});
