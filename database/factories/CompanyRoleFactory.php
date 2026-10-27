<?php

namespace Database\Factories;

use App\Enums\RoleName;
use App\Models\Company;
use App\Models\CompanyRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyRole>
 */
class CompanyRoleFactory extends Factory
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
            'user_id' => User::factory(),
            'role' => RoleName::ApProcessor,
        ];
    }
}
