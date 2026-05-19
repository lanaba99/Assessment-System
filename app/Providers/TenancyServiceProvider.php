<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->booting(function () {
       $mainPath = database_path('migrations/tenant');
       if (file_exists($mainPath)) {
           $directories = glob($mainPath . '/*', GLOB_ONLYDIR);
           $paths = array_merge([$mainPath], $directories);
           config(['tenancy.migration_parameters.--path' => $paths]);
       }
   });
    }
}
