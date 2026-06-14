<?php

declare(strict_types=1);

use App\Http\Controllers\Central\AuthController;
use App\Http\Controllers\Central\TenantController;
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
*/

Route::get('ping', fn () => response()->json(['scope' => 'central', 'ok' => true]))
    ->name('api.central.ping');

Route::prefix('v1/admin')->group(function (): void {
    Route::post('auth/login', [AuthController::class, 'login'])
        ->name('api.central.admin.login');

    Route::middleware(['auth:sanctum', 'central.admin'])->group(function (): void {
        Route::get('tenants', [TenantController::class, 'index'])
            ->name('api.central.tenants.index');

        Route::post('tenants', [TenantController::class, 'store'])
            ->name('api.central.tenants.store');

        Route::get('tenants/{tenantId}', [TenantController::class, 'show'])
            ->name('api.central.tenants.show');

        Route::patch('tenants/{tenantId}', [TenantController::class, 'update'])
            ->name('api.central.tenants.update');
    });
});
