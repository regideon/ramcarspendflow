<?php

namespace Database\Factories\Rfp;

use App\Enums\RfpRequestType;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\Rfp\DueType;
use App\Models\Rfp\Rfp;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rfp>
 */
class RfpFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'requestor_id' => User::factory(),
            'request_type' => RfpRequestType::Standard,
            'rfp_due_type_id' => fn () => DueType::query()->where('code', 'standard')->value('id') ?? DueType::factory()->create(['code' => 'standard'])->id,
            'vendor_id' => Vendor::factory(),
            'account_group_id' => fn (array $attributes) => Vendor::find($attributes['vendor_id'])?->account_group_id,
            'payment_method_id' => PaymentMethod::factory(),
            'purpose' => fake()->sentence(),
            'is_direct_to_vp' => false,
            'is_pre_approved' => false,
        ];
    }
}
