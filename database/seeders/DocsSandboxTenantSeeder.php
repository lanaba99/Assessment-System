<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Seeds a stable, well-known Tenant used only for local API-documentation
 * generation (Scribe response calls). Safe to run repeatedly — keyed on
 * SCRIBE_TENANT_ID so re-seeding updates the same row instead of creating
 * duplicates.
 *
 * This tenant carries only fake/placeholder data. It is not a customer
 * tenant and must never be seeded into a production landlord database.
 *
 * Run manually (landlord context) with:
 *   php artisan db:seed --class=Database\\Seeders\\DocsSandboxTenantSeeder
 */
class DocsSandboxTenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = (string) env('SCRIBE_TENANT_ID', '00000000-0000-4000-8000-000000000001');
        $now = now();

        $tenant = Tenant::updateOrCreate(
            ['id' => $tenantId],
            [
                'organization_name' => 'Docs Sandbox Org',
                'organization_type' => 'sandbox',
                'primary_contact_email' => 'docs-sandbox@example.test',
                'primary_contact_phone' => '+1-555-0199',
                'deployment_config' => [
                    'region' => 'sandbox',
                    'tier' => 'sandbox',
                ],
                'deployment_mode' => 'multi_database',
                'data_residency_location' => 'US',
                'max_concurrent_users' => 10,
                'max_storage_quota_mb' => 1024,
                'feature_flags' => [
                    'adaptive_exams' => true,
                    'live_proctoring' => false,
                    'ai_recommendations' => false,
                    'white_labeling' => false,
                ],
                'status' => 'active',
                'security_policies' => [
                    'mfa_required' => false,
                    'session_timeout_minutes' => 60,
                ],
                'contract_start_date' => $now,
                'contract_end_date' => $now->copy()->addYears(10),
            ],
        );

        // InitializeTenancyBySubdomain reads only the leftmost host label,
        // matching LandlordSeeder's convention for the customer tenant.
        $tenant->domains()->firstOrCreate([
            'domain' => env('SCRIBE_TENANT_SUBDOMAIN', 'docs-sandbox'),
        ]);

        $this->command?->info("Docs Sandbox Tenant seeded. id: {$tenant->id} (docs-sandbox.localhost)");
    }
}
