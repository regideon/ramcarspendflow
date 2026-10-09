<?php

namespace App\Filament\Clusters\RfpSettings\Resources\Categories\Pages;

use App\Filament\Clusters\RfpSettings\Resources\Categories\CategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCategory extends CreateRecord
{
    protected static string $resource = CategoryResource::class;
}
