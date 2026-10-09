<?php

use App\Enums\RfpRequestType;
use App\Enums\RoleName;
use App\Models\Rfp\ApprovalRule;
use Database\Seeders\Rfp\ApprovalMatrixSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RoleAndUserSeeder::class, ApprovalMatrixSeeder::class]);
});

/**
 * @return list<string>
 */
function approverChain(RfpRequestType $requestType, string|float $amount, bool $isDirectToVp = false): array
{
    return ApprovalRule::findFor($requestType, $amount, $isDirectToVp)
        ->steps
        ->map(fn ($step) => $step->role->name)
        ->all();
}

it('routes reimbursements by amount, with thresholds belonging to the lower tier', function (float $amount, array $expected) {
    expect(approverChain(RfpRequestType::Reimbursement, $amount))
        ->toBe(array_map(fn (RoleName $role) => $role->value, $expected));
})->with([
    'small' => [500, [RoleName::LineManager]],
    'exactly 10K' => [10_000, [RoleName::LineManager]],
    'just above 10K' => [10_000.01, [RoleName::LineManager, RoleName::DepartmentHead]],
    'exactly 100K' => [100_000, [RoleName::LineManager, RoleName::DepartmentHead]],
    'above 100K' => [100_000.01, [RoleName::LineManager, RoleName::DepartmentHead, RoleName::DivisionHead, RoleName::Controller]],
]);

it('routes standard requests by amount, with thresholds belonging to the lower tier', function (float $amount, array $expected) {
    expect(approverChain(RfpRequestType::Standard, $amount))
        ->toBe(array_map(fn (RoleName $role) => $role->value, $expected));
})->with([
    'exactly 1M' => [1_000_000, [RoleName::DepartmentHead, RoleName::BuFpa]],
    'above 1M' => [1_000_000.01, [RoleName::BuHead, RoleName::FpaHead]],
    'exactly 5M' => [5_000_000, [RoleName::BuHead, RoleName::FpaHead]],
    'above 5M' => [5_000_000.01, [RoleName::President, RoleName::EnterpriseController]],
    'exactly 25M' => [25_000_000, [RoleName::President, RoleName::EnterpriseController]],
    'above 25M' => [25_000_000.01, [RoleName::ExcomChairman]],
]);

it('routes direct-to-VP requests the same way for both request types', function (RfpRequestType $requestType) {
    expect(approverChain($requestType, 100_000, isDirectToVp: true))->toBe([RoleName::DivisionHead->value])
        ->and(approverChain($requestType, 100_000.01, isDirectToVp: true))->toBe([RoleName::DivisionHead->value, RoleName::EnterpriseController->value]);
})->with(RfpRequestType::cases());

it('prefers a rule for the specific request type over an any-type rule', function () {
    $specific = ApprovalRule::factory()->create([
        'request_type' => RfpRequestType::Standard,
        'is_direct_to_vp' => true,
        'max_amount' => 100_000,
    ]);

    expect(ApprovalRule::findFor(RfpRequestType::Standard, 50_000, true)->is($specific))->toBeTrue()
        ->and(ApprovalRule::findFor(RfpRequestType::Reimbursement, 50_000, true)->is($specific))->toBeFalse();
});

it('ignores inactive tiers', function () {
    ApprovalRule::query()
        ->where('request_type', RfpRequestType::Reimbursement)
        ->where('max_amount', 10_000)
        ->update(['is_active' => false]);

    expect(approverChain(RfpRequestType::Reimbursement, 500))
        ->toBe([RoleName::LineManager->value, RoleName::DepartmentHead->value]);
});
