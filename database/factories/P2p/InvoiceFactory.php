<?php

namespace Database\Factories\P2p;

use App\Enums\P2pStatus;
use App\Models\Company;
use App\Models\P2p\Invoice;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => P2pStatus::Pending,
            'user_id' => User::factory(),
            'vendor_id' => Vendor::factory(),
            'company_id' => Company::factory(),
            'gr_numbers' => [],
            'reference_number' => [fake()->bothify('DR-####')],
            'invoice_at' => now()->subDay(),
            'invoice_number' => fake()->numerify('INV-######'),
        ];
    }
}
