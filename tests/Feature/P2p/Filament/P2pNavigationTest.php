<?php

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RoleAndUserSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

pest()->use(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndUserSeeder::class);
});

/**
 * The rendered navigation, flattened to [group label, item label] pairs in order.
 *
 * @return array<int, array{group: ?string, item: ?string}>
 */
function navigationShape(): array
{
    $shape = [];

    foreach (Filament::getNavigation() as $group) {
        foreach ($group->getItems() as $item) {
            $shape[] = [
                'group' => $group->getLabel(),
                'item' => method_exists($item, 'getLabel') ? $item->getLabel() : null,
            ];
        }
    }

    return $shape;
}

/**
 * The labelled groups in render order (the ungrouped bucket Filament sorts first
 * and leaves unlabelled is excluded).
 *
 * @return array<int, string>
 */
function navigationGroups(): array
{
    return collect(navigationShape())
        ->pluck('group')
        ->filter()
        ->unique()
        ->values()
        ->all();
}

it('keeps the configured navigation groups in order', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::Admin));

    expect(navigationGroups())->toBe(['RFP', 'P2P', '-', 'Settings']);
});

it('files the redesigned P2P Approvals under P2P and the old P2P pages under "-"', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::ApProcessor));

    $items = collect(navigationShape());

    expect($items->where('group', 'P2P')->pluck('item')->all())->toBe(['P2P Requests', 'P2P Approvals'])
        ->and($items->where('group', '-')->pluck('item')->all())->toContain('P2P Approvals');
});

it('files P2P Escalations with P2P Requests and P2P Approvals under P2P', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::Admin));

    $items = collect(navigationShape());

    expect($items->where('group', 'P2P')->pluck('item')->all())->toContain('P2P Requests', 'P2P Approvals', 'P2P Escalations')
        ->and($items->where('group', '-')->pluck('item')->all())->not->toContain('P2P Escalations');
});

it('does not create separate Approvals or Escalations groups', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::Admin));

    $groups = navigationGroups();

    expect($groups)->not->toContain('P2P Approvals')
        ->and($groups)->not->toContain('P2P Escalations');
});

it('shows the approvals destination to an AP processor and the supervisor', function (RoleName $role) {
    $this->actingAs(User::factory()->create()->assignRole($role));

    $items = collect(navigationShape())->where('group', 'P2P')->pluck('item')->all();

    expect($items)->toContain('P2P Approvals');
})->with([
    'ap processor' => RoleName::ApProcessor,
    'ap supervisor' => RoleName::ApSupervisor,
]);

it('hides the approvals destination from a vendor', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::Vendor));

    $items = collect(navigationShape())->where('group', 'P2P')->pluck('item')->all();

    expect($items)->not->toContain('P2P Approvals');
});
