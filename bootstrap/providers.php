<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\HubPanelProvider;
use App\Providers\P2pStepActionServiceProvider;

return [
    AppServiceProvider::class,
    HubPanelProvider::class,
    P2pStepActionServiceProvider::class,
];
