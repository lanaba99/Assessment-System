<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class LandlordSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $tenant = Tenant::create([
            'organization_name' => 'Alpha Global Assessment Corp',
            'organization_type' => 'enterprise',
            'primary_contact_email' => 'contact@alpha-engine.example',
            'primary_contact_phone' => '+1-555-0100',
            'deployment_config' => [
                'region' => 'us-east-1',
                'tier' => 'premium',
            ],
            'deployment_mode' => 'multi_database',
            'data_residency_location' => 'US',
            'max_concurrent_users' => 1000,
            'max_storage_quota_mb' => 102400,
            'feature_flags' => [
                'adaptive_exams' => true,
                'live_proctoring' => true,
                'ai_recommendations' => true,
                'white_labeling' => true,
            ],
            'status' => 'active',
            'security_policies' => [
                'mfa_required' => true,
                'session_timeout_minutes' => 60,
            ],
            'contract_start_date' => $now,
            'contract_end_date' => $now->copy()->addYear(),
        ]);

        // InitializeTenancyBySubdomain extracts the leftmost label off the request
        // host and looks THAT up in the `domains` table, so we store just the label —
        // not the full `alpha-engine.localhost` hostname.
        $tenant->domains()->create([
            'domain' => 'alpha-engine',
        ]);

        DB::table('central_admin_users')->insert([
            'admin_user_id' => (string) Str::uuid(),
            'email' => 'superadmin@alpha-engine.example',
            'password_hash' => Hash::make('ChangeMe123!'),
            'first_name' => 'Alpha',
            'last_name' => 'Administrator',
            'admin_permissions' => json_encode(['*']),
            'is_super_admin' => true,
            'mfa_enabled' => false,
            'mfa_settings' => null,
            'status' => 'active',
            'created_at' => $now,
        ]);

        $this->command?->info("Landlord seeded. Tenant id: {$tenant->id} (alpha-engine.localhost)");
    }
}
