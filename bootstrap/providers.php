<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\DomainServiceProvider::class,
    App\Providers\TenancyServiceProvider::class,
    // Only active during `artisan scribe:generate` — see class docblock.
    App\Docs\ScribeResponseCallSafetyServiceProvider::class,
];
