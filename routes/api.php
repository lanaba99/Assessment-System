<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central API routes
|--------------------------------------------------------------------------
|
| Routes registered here run in the CENTRAL (landlord) database context.
| They are reachable only from a central host (e.g. http://localhost, not
| http://acme.localhost) because tenancy resolution does not run on this file.
|
| Tenant-scoped routes live in routes/tenant.php and are loaded by
| App\Providers\TenancyServiceProvider::mapRoutes().
|
| Health check `/up` is registered separately via bootstrap/app.php.
|
| Central admin endpoints (e.g. POST /api/v1/admin/tenants) will be added
| here in a later layer once central-admin auth is wired.
*/

Route::get('ping', fn () => response()->json(['scope' => 'central', 'ok' => true]))
    ->name('api.central.ping');
