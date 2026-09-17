<?php

use App\Providers\AppServiceProvider;
use Modules\Identity\Providers\IdentityServiceProvider;
use Modules\MasterData\Providers\MasterDataServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    MasterDataServiceProvider::class,
];
