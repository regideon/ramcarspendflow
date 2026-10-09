<?php

use App\Filament\Clusters\RfpSettings\Resources\DueTypes\Pages\CreateDueType;
use App\Filament\Clusters\RfpSettings\Resources\DueTypes\Pages\ListDueTypes;
use App\Models\Rfp\DueType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Rfp\DueTypeSeeder;
use Database\Seeders\RoleAndUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RoleAndUserSeeder::class, DueTypeSeeder::class]);
});

it('counts business days, skipping weekends', function (string $from, int $days, bool $businessDaysOnly, string $due) {
    $dueType = new DueType(['processing_days' => $days, 'business_days_only' => $businessDaysOnly]);

    expect($dueType->dueDateFrom(CarbonImmutable::parse($from))->toDateString())->toBe($due);
})->with([
    'urgent on a Tuesday' => ['2026-10-06', 1, true, '2026-10-07'],
    'urgent on a Friday' => ['2026-10-09', 1, true, '2026-10-12'],
    'standard on a Tuesday' => ['2026-10-06', 5, true, '2026-10-13'],
    'calendar days' => ['2026-10-09', 1, false, '2026-10-10'],
]);

it('seeds Urgent and Standard, with Standard as the default', function () {
    expect(DueType::query()->active()->pluck('name')->all())->toBe(['Urgent', 'Standard'])
        ->and(DueType::query()->where('is_default', true)->pluck('code')->all())->toBe(['standard']);
});

it('keeps a single default', function () {
    DueType::firstWhere('code', 'urgent')->update(['is_default' => true]);

    expect(DueType::query()->where('is_default', true)->pluck('code')->all())->toBe(['urgent']);
});

it('lets admins manage due types', function () {
    $this->actingAs(User::firstWhere('email', RoleAndUserSeeder::OWNER_EMAIL));

    Livewire::test(ListDueTypes::class)->assertCanSeeTableRecords(DueType::all());

    Livewire::test(CreateDueType::class)
        ->fillForm(['name' => 'Same day', 'code' => 'same-day', 'processing_days' => 0, 'business_days_only' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(DueType::firstWhere('code', 'same-day'))->processing_days->toBe(0);
});

it('keeps requestors out of the settings', function () {
    $this->actingAs(User::firstWhere('email', 'requestor@ramcar.com'))
        ->get('/hub/rfp-settings/due-types')
        ->assertForbidden();
});
