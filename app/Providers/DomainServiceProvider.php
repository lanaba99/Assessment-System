<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

class DomainServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $domainsPath = app_path('Domains');

        if (!File::exists($domainsPath)) {
            return;
        }

        foreach (File::directories($domainsPath) as $domain) {
            $domainName = basename($domain);

            if ($domainName === 'Shared') {
                continue;
            }

            $providersPath = $domain . '/Providers';

            if (!File::exists($providersPath)) {
                continue;
            }

            foreach (File::files($providersPath) as $providerFile) {
                $providerClass = 'App\\Domains\\' . $domainName . '\\Providers\\' . $providerFile->getFilenameWithoutExtension();

                if (class_exists($providerClass)) {
                    $this->app->register($providerClass);
                }
            }
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $domainsPath = app_path('Domains');

        if (!File::exists($domainsPath)) {
            return;
        }

        // جلب كل المجلدات داخل مجلد Domains
        $domains = File::directories($domainsPath);

        foreach ($domains as $domain) {
            $domainName = basename($domain);

            if ($domainName === 'Shared') {
                continue;
            }

            // تفعيل الروابط لكل دومين تلقائياً
            $this->registerDomainRoutes($domain, $domainName);

            // ⚠️ السطر السحري الجديد: تحميل الماغريشنز لكل دومين تلقائياً
            $domainMigrationsPath = $domain . '/Database/Migrations';
            if (File::exists($domainMigrationsPath)) {
                $this->loadMigrationsFrom($domainMigrationsPath);
            }
        }
    }

    /**
     * دالة مخصصة لقراءة ملفات الـ Routes من كل دومين
     */
    protected function registerDomainRoutes(string $domainPath, string $domainName): void
    {
        $routePath = $domainPath . '/Routes';

        // تفعيل روابط الـ Web
        if (File::exists($routePath . '/web.php')) {
            Route::middleware('web')
                ->group($routePath . '/web.php');
        }

        // تفعيل روابط الـ API
        if (File::exists($routePath . '/api.php')) {
            Route::prefix('api/' . strtolower($domainName)) // مثال: api/identity/...
                ->middleware('api')
                ->group($routePath . '/api.php');
        }
    }
}