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

    expect(navigationGroups())->toBe(['RFP', 'P2P', 'Settings']);
});

it('files Approvals and Escalations under the P2P group', function () {
    $this->actingAs(User::factory()->create()->assignRole(RoleName::ApProcessor));

    $p2pItems = collect(navigationShape())
        ->where('group', 'P2P')
        ->pluck('item')
        ->all();

    expect($p2pItems)->toContain('P2P Approvals');
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

    expect($items)->not->toContain('Approvals');
});
