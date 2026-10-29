<?php

namespace App\Filament\Resources\P2p\SapLogs\Pages;

use App\Filament\Resources\P2p\SapLogs\SapLogResource;
use Filament\Resources\Pages\ListRecords;

class ListSapLogs extends ListRecords
{
    protected static string $resource = SapLogResource::class;

    public function getSubheading(): ?string
    {
        return 'Every SAP OData read and SOAP write, newest first.';
    }
}
