<?php

namespace Database\Factories\Rfp;

use App\Enums\RfpStatus;
use App\Models\Rfp\History;
use App\Models\Rfp\Rfp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<History>
 */
class HistoryFactory extends Factory
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
            'from_status' => RfpStatus::Draft,
            'to_status' => RfpStatus::ForApReview,
        ];
    }
}
