<?php

namespace App\Filament\Pages\Auth;

use App\Enums\RoleName;
use App\Models\CompanyRole;
use App\Models\User;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;

/**
 * Hub profile: Filament's profile form, plus the signed-in user's roles (read-only;
 * Admins assign roles under Users).
 */
class EditProfile extends BaseEditProfile
{
    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
            ...Arr::wrap($this->getMultiFactorAuthenticationContentComponent()),
            $this->rolesSection(),
        ]);
    }

    protected function rolesSection(): Section
    {
        /** @var User $user */
        $user = Filament::auth()->user();
        $user->loadMissing(['roles', 'companyRoles.company']);

        $companyScoped = array_map(fn (RoleName $role): string => $role->value, RoleName::companyScoped());

        // Approval roles, each with the companies the user holds it in
        $approvalRoles = $user->companyRoles
            ->sortBy(fn (CompanyRole $assignment): string => $assignment->role->value)
            ->groupBy(fn (CompanyRole $assignment): string => $assignment->role->value)
            ->map(fn ($assignments): array => $assignments
                ->map(fn (CompanyRole $assignment): string => $assignment->company?->short_name ?? $assignment->company?->name ?? '—')
                ->sort()
                ->values()
                ->all());

        return Section::make('Your roles')
            ->description('What you can do in Ramcar AP. Ask an Admin to change your roles.')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->schema([
                TextEntry::make('general_roles')
                    ->label('Roles')
                    ->state($user->getRoleNames()->diff($companyScoped)->sort()->values()->all())
                    ->badge()
                    ->color('primary')
                    ->placeholder('No roles yet'),
                TextEntry::make('approval_roles_empty')
                    ->label('Approval roles, per company')
                    ->state('None')
                    ->color('gray')
                    ->visible($approvalRoles->isEmpty()),
                Grid::make(['default' => 1, 'md' => 2])
                    ->visible($approvalRoles->isNotEmpty())
                    ->schema($approvalRoles
                        ->map(fn (array $companies, string $role): TextEntry => TextEntry::make('approval_role_'.md5($role))
                            ->label($role)
                            ->state($companies)
                            ->badge()
                            ->color('gray'))
                        ->values()
                        ->all()),
            ]);
    }
}
