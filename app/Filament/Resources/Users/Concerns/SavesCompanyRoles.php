<?php

namespace App\Filament\Resources\Users\Concerns;

use App\Models\User;

/**
 * Saves the "Approval roles by company" repeater, which is not a relationship field.
 */
trait SavesCompanyRoles
{
    /**
     * @var list<array{role: string, companies: list<int|string>}>
     */
    protected array $companyRoles = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function pullCompanyRoles(array $data): array
    {
        $this->companyRoles = array_values($data['company_roles'] ?? []);
        unset($data['company_roles']);

        return $data;
    }

    protected function saveCompanyRoles(): void
    {
        /** @var User $user */
        $user = $this->getRecord();
        $user->syncCompanyRoles($this->companyRoles);
    }
}
