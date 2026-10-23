<?php

namespace Database\Factories\Rfp;

use App\Enums\RfpApprovalStepState;
use App\Enums\RfpStageKind;
use App\Enums\RfpStatus;
use App\Enums\RoleName;
use App\Models\Rfp\ApprovalStep;
use App\Models\Rfp\Rfp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalStep>
 */
class ApprovalStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rfp_id' => Rfp::factory(),
            'round' => 1,
            'sequence' => 1,
            'stage_position' => 1,
            'kind' => RfpStageKind::Approval,
            'role' => RoleName::ApProcessor,
            'status' => RfpStatus::ForApReview,
            'label' => 'AP Processor',
            'state' => RfpApprovalStepState::Pending,
        ];
    }
}
