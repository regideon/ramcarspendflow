<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\HubPanelProvider;
use App\Providers\Filament\MyPanelProvider;
use App\Providers\P2pSapServiceProvider;
use App\Providers\P2pStepActionServiceProvider;

return [
    AppServiceProvider::class,
    HubPanelProvider::class,
    MyPanelProvider::class,
    P2pSapServiceProvider::class,
    P2pStepActionServiceProvider::class,
];
