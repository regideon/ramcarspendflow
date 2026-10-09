<?php

namespace App\Filament\Clusters\RfpSettings\Resources\ApprovalRules\Pages;

use App\Filament\Clusters\RfpSettings\Resources\ApprovalRules\ApprovalRuleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateApprovalRule extends CreateRecord
{
    protected static string $resource = ApprovalRuleResource::class;
}
