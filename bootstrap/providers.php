<?php

use App\Providers\AppServiceProvider;
use Modules\Identity\Providers\IdentityServiceProvider;
use Modules\MasterData\Providers\MasterDataServiceProvider;
use Modules\Planning\Providers\PlanningServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    MasterDataServiceProvider::class,
    PlanningServiceProvider::class,
];
