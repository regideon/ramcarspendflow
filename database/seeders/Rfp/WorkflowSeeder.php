<?php

namespace Database\Seeders\Rfp;

use App\Enums\RfpStageKind;
use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Models\Rfp\WorkflowStage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorkflowSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * RFP 2026 flow: Requestor → AP Processor → matrix approvers → AP Processor →
     * AP Supervisor → AP Manager → Treasury. Replaces any existing workflow.
     */
    public function run(): void
    {
        $stages = [
            ['kind' => RfpStageKind::Approval, 'role' => RoleName::ApProcessor, 'status' => RfpStatus::ForApReview, 'label' => 'AP Processor',
                'can_return_to_requestor' => true],
            ['kind' => RfpStageKind::Matrix, 'role' => null, 'status' => RfpStatus::ForApproval, 'label' => 'Approval matrix',
                'can_reject' => true, 'can_cancel' => true],
            ['kind' => RfpStageKind::ApProcessing, 'role' => RoleName::ApProcessor, 'status' => RfpStatus::ForApProcessing, 'label' => 'AP Processor',
                'can_return_to_requestor' => true, 'can_cancel' => true],
            ['kind' => RfpStageKind::Approval, 'role' => RoleName::ApSupervisor, 'status' => RfpStatus::ForAccountingApproval, 'label' => 'AP Supervisor',
                'return_to_position' => 3],
            ['kind' => RfpStageKind::Approval, 'role' => RoleName::ApManager, 'status' => RfpStatus::ForAccountingApproval, 'label' => 'AP Manager',
                'return_to_position' => 3, 'can_cancel' => true],
            ['kind' => RfpStageKind::Payment, 'role' => RoleName::Treasury, 'status' => RfpStatus::ForTreasury, 'label' => 'Treasury'],
        ];

        DB::transaction(function () use ($stages): void {
            WorkflowStage::query()->delete();

            foreach ($stages as $index => $stage) {
                WorkflowStage::create(['position' => $index + 1, ...$stage]);
            }
        });
    }
}
