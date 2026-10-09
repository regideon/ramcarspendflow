<?php

namespace Database\Factories\Rfp;

use App\Enums\RfpRequestType;
use App\Models\Rfp\ApprovalRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalRule>
 */
class ApprovalRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_type' => RfpRequestType::Standard,
            'is_direct_to_vp' => false,
            'max_amount' => null,
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
