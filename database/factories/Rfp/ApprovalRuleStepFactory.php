<?php

namespace Database\Factories\Rfp;

use App\Models\Rfp\ApprovalRule;
use App\Models\Rfp\ApprovalRuleStep;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<ApprovalRuleStep>
 */
class ApprovalRuleStepFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rfp_approval_rule_id' => ApprovalRule::factory(),
            'step_order' => 1,
            'role_id' => fn () => Role::findOrCreate(fake()->unique()->jobTitle())->id,
        ];
    }
}
