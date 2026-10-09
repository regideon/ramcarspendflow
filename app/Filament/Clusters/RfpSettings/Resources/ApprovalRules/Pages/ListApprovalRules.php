<?php

namespace App\Filament\Clusters\RfpSettings\Resources\ApprovalRules\Pages;

use App\Filament\Clusters\RfpSettings\Resources\ApprovalRules\ApprovalRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListApprovalRules extends ListRecords
{
    protected static string $resource = ApprovalRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
