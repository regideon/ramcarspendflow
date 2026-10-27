<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Concerns\SavesCompanyRoles;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use SavesCompanyRoles;

    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->pullCompanyRoles($data);
    }

    protected function afterCreate(): void
    {
        $this->syncPrimaryVendor();
        $this->saveCompanyRoles();
    }

    /**
     * Mirror the first assigned vendor into the legacy `users.vendor_id` column
     * so single-vendor lookups keep working while the pivot rolls out.
     */
    private function syncPrimaryVendor(): void
    {
        $firstVendorId = $this->record->vendors()
            ->orderBy('vendor_user.id')
            ->value('vendors.id');

        if ((int) $this->record->vendor_id === (int) $firstVendorId) {
            return;
        }

        $this->record->forceFill(['vendor_id' => $firstVendorId])->saveQuietly();
    }
}
