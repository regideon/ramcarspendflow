<?php

namespace App\Filament\Clusters\MasterData\Resources\Vendors\Pages;

use App\Filament\Clusters\MasterData\Resources\Vendors\VendorResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVendor extends CreateRecord
{
    protected static string $resource = VendorResource::class;
}
