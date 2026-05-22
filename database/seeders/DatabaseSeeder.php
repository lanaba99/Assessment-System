<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Entry point for both `db:seed` (landlord context) and
     * `tenants:seed` (tenant context, configured in config/tenancy.php
     * via seeder_parameters.--class).
     *
     * Child seeders are responsible for detecting their own context
     * (e.g. IdentityPermissionsSeeder no-ops if no tenant is bound).
     */
    public function run(): void
    {
        $this->call([
            IdentityPermissionsSeeder::class,
        ]);
    }
}
