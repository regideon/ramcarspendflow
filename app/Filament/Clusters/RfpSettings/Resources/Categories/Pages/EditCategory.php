<?php

namespace App\Filament\Clusters\RfpSettings\Resources\Categories\Pages;

use App\Filament\Clusters\RfpSettings\Resources\Categories\CategoryResource;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;
}
