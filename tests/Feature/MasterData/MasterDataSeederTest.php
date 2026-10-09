<?php

use App\Enums\RoleName;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Rfp\ApprovalRule;
use App\Models\Rfp\ApprovalRuleStep;
use App\Models\TaxCode;
use App\Models\WithholdingTaxCode;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

pest()->use(RefreshDatabase::class);

it('seeds every role from the RoleName enum', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Role::query()->pluck('name')->sort()->values()->all())
        ->toBe(collect(RoleName::cases())->map->value->sort()->values()->all());
});

it('can be re-run without duplicating data', function () {
    $this->seed(DatabaseSeeder::class);

    $counts = fn (): array => [
        Role::count(),
        Company::count(),
        Currency::count(),
        TaxCode::count(),
        WithholdingTaxCode::count(),
        ApprovalRule::count(),
        ApprovalRuleStep::count(),
    ];

    $firstRun = $counts();

    $this->seed(DatabaseSeeder::class);

    expect($counts())->toBe($firstRun)
        ->and($firstRun)->toBe([count(RoleName::cases()), 4, 185, 15, 70, 9, 17]);
});
