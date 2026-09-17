<?php

use App\Providers\AppServiceProvider;
use Modules\Activities\Providers\ActivitiesServiceProvider;
use Modules\Evidence\Providers\EvidenceServiceProvider;
use Modules\Identity\Providers\IdentityServiceProvider;
use Modules\Integrity\Providers\IntegrityServiceProvider;
use Modules\MasterData\Providers\MasterDataServiceProvider;
use Modules\Planning\Providers\PlanningServiceProvider;
use Modules\WebAdmin\Providers\WebAdminServiceProvider;

return [
    AppServiceProvider::class,
    IdentityServiceProvider::class,
    MasterDataServiceProvider::class,
    PlanningServiceProvider::class,
    ActivitiesServiceProvider::class,
    EvidenceServiceProvider::class,
    IntegrityServiceProvider::class,
    WebAdminServiceProvider::class,
];
