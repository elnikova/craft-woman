<?php

return [
    App\Providers\AppServiceProvider::class,
    Modules\Identity\Infrastructure\Providers\IdentityServiceProvider::class,
    Modules\Marketplace\Infrastructure\Providers\MarketplaceServiceProvider::class,
    Modules\Education\Infrastructure\Providers\EducationServiceProvider::class,
    Modules\Orders\Infrastructure\Providers\OrdersServiceProvider::class,
];
