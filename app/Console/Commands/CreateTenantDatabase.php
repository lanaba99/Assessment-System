<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Stancl\Tenancy\Database\Models\Tenant;

class CreateTenantDatabase extends Command
{
    protected $signature = 'tenants:make:db {id}';
    protected $description = 'Create a tenant and its database automatically';

    public function handle()
    {
        $id = $this->argument('id');
        
        // when we create a tenant, the CreatingTenant event will be fired, and our listener will create the database 

        $tenant = Tenant::create(['id' => $id]);
        
        $this->info("Tenant '{$id}' created and database initialized successfully!");
    }
}