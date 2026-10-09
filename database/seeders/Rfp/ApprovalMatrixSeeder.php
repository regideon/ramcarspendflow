<?php

namespace Database\Seeders\Rfp;

use App\Enums\RfpRequestType;
use App\Enums\RoleName;
use App\Models\Rfp\ApprovalRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class ApprovalMatrixSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * RFP 2026 approval matrix (Step 3). Amounts are in PHP; an amount exactly on a
     * threshold belongs to the lower tier. Roles listed together are approved in order.
     * Requires RoleAndUserSeeder (roles).
     */
    public function run(): void
    {
        $tiers = [
            // Reimbursement
            [RfpRequestType::Reimbursement, false, 10_000, [RoleName::LineManager]],
            [RfpRequestType::Reimbursement, false, 100_000, [RoleName::LineManager, RoleName::DepartmentHead]],
            [RfpRequestType::Reimbursement, false, null, [RoleName::LineManager, RoleName::DepartmentHead, RoleName::DivisionHead, RoleName::Controller]],

            // Standard
            [RfpRequestType::Standard, false, 1_000_000, [RoleName::DepartmentHead, RoleName::BuFpa]],
            [RfpRequestType::Standard, false, 5_000_000, [RoleName::BuHead, RoleName::FpaHead]],
            [RfpRequestType::Standard, false, 25_000_000, [RoleName::President, RoleName::EnterpriseController]],
            [RfpRequestType::Standard, false, null, [RoleName::ExcomChairman]],

            // Direct to VP toggle (any request type)
            [null, true, 100_000, [RoleName::DivisionHead]],
            [null, true, null, [RoleName::DivisionHead, RoleName::EnterpriseController]],
        ];

        DB::transaction(function () use ($tiers): void {
            foreach ($tiers as [$requestType, $isDirectToVp, $maxAmount, $roles]) {
                $rule = ApprovalRule::updateOrCreate(
                    ['request_type' => $requestType, 'is_direct_to_vp' => $isDirectToVp, 'max_amount' => $maxAmount],
                    ['description' => $this->describe($requestType, $isDirectToVp, $maxAmount)],
                );

                $rule->steps()->delete();

                foreach ($roles as $index => $roleName) {
                    $rule->steps()->create([
                        'step_order' => $index + 1,
                        'role_id' => Role::findByName($roleName->value, 'web')->id,
                    ]);
                }
            }
        });
    }

    private function describe(?RfpRequestType $requestType, bool $isDirectToVp, ?int $maxAmount): string
    {
        $scope = $isDirectToVp ? 'Direct to VP' : $requestType->getLabel();
        $range = $maxAmount === null ? 'no limit' : 'up to PHP '.number_format($maxAmount);

        return "{$scope}, {$range}";
    }
}
