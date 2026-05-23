<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $domainsPath = app_path('Domains');

        if (! File::exists($domainsPath)) {
            return;
        }

        foreach (File::directories($domainsPath) as $domain) {
            $domainName = basename($domain);

            if ($domainName === 'Shared') {
                continue;
            }

            $providersPath = $domain . '/Providers';

            if (! File::exists($providersPath)) {
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
}
