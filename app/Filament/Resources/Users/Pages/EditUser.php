<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $this->syncPrimaryVendor();
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
