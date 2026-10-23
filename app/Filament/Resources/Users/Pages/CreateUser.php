<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Concerns\SavesCompanyRoles;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use SavesCompanyRoles;

    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->pullCompanyRoles($data);
    }

    protected function afterCreate(): void
    {
        $this->saveCompanyRoles();
    }
}
