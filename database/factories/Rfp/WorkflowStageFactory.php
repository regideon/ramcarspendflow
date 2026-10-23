<?php

namespace Database\Factories\Rfp;

use App\Enums\RfpStageKind;
use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Models\Rfp\WorkflowStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowStage>
 */
class WorkflowStageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'position' => fn () => (WorkflowStage::max('position') ?? 0) + 1,
            'kind' => RfpStageKind::Approval,
            'role' => RoleName::ApSupervisor,
            'status' => RfpStatus::ForAccountingApproval,
            'label' => 'AP Supervisor',
        ];
    }
}
