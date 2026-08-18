<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Database\Seeders\DocsSandboxTenantSeeder;
use Database\Seeders\TenantMasterSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * SANDBOX-ONLY. Creates or updates the Docs Sandbox Tenant and populates it
 * with fake data so Web/Mobile developers have a deterministic, reproducible
 * tenant to build against (login -> exam -> result -> certificate).
 *
 * This command is intentionally hard-coded to the single tenant identified
 * by SCRIBE_TENANT_ID (config/scribe.php's own sandbox tenant) and refuses
 * to run anywhere near a real tenant or production:
 *   - it never accepts a tenant id argument, so it cannot be pointed at an
 *     arbitrary tenant from the command line;
 *   - before touching anything, it verifies any pre-existing row at that id
 *     is already marked organization_type=sandbox (the marker
 *     DocsSandboxTenantSeeder itself writes) and aborts otherwise, so
 *     accidentally repointing SCRIBE_TENANT_ID at a real tenant's id can
 *     never overwrite that tenant;
 *   - it refuses outright when APP_ENV is production, with no override flag.
 *
 * It never modifies DocsSandboxTenantSeeder or TenantMasterSeeder — it only
 * orchestrates them (landlord seed -> tenant migrate -> tenant seed) in the
 * order the README already documents for a normal tenant, scoped via
 * stancl/tenancy's --tenants= option so no other tenant's database is ever
 * touched.
 */
class SandboxSetupCommand extends Command
{
    protected $signature = 'sandbox:setup {--fresh : Wipe the sandbox tenant\'s database and reseed it from scratch}';

    protected $description = 'Sandbox-only: create/update the Docs Sandbox Tenant and seed it with fake demo data';

    public function handle(): int
    {
        if ($this->laravel->environment('production')) {
            $this->error('Refusing to run sandbox:setup in the production environment.');

            return self::FAILURE;
        }

        // Same env() reads DocsSandboxTenantSeeder and config/scribe.php already use —
        // kept identical on purpose so this command always targets the exact tenant
        // Scribe's own response-call strategy points at.
        $tenantId = (string) env('SCRIBE_TENANT_ID', '00000000-0000-4000-8000-000000000001');
        $subdomain = (string) env('SCRIBE_TENANT_SUBDOMAIN', 'docs-sandbox');

        if (! $this->guardTargetIsSafe($tenantId, $subdomain)) {
            return self::FAILURE;
        }

        $this->info("Seeding landlord row for sandbox tenant {$tenantId}...");
        Artisan::call('db:seed', [
            '--class' => DocsSandboxTenantSeeder::class,
            '--force' => true,
        ], $this->output);

        $tenant = Tenant::find($tenantId);

        if ($tenant === null) {
            $this->error('DocsSandboxTenantSeeder did not create the expected tenant row. Aborting.');

            return self::FAILURE;
        }

        $fresh = (bool) $this->option('fresh');

        $this->info($fresh
            ? "Dropping and re-migrating the sandbox tenant's database..."
            : "Migrating the sandbox tenant's database (pending migrations only)...");

        Artisan::call($fresh ? 'tenants:migrate-fresh' : 'tenants:migrate', [
            '--tenants' => [$tenantId],
            '--force' => true,
        ], $this->output);

        $alreadySeeded = $this->tenantAlreadyHasData($tenant);

        if ($alreadySeeded && ! $fresh) {
            $this->warn(
                "Sandbox tenant already has data and --fresh was not passed — skipping TenantMasterSeeder "
                . '(it is not idempotent and would fail on duplicate rows). '
                . 'Run `php artisan sandbox:reset` (or `sandbox:setup --fresh`) to wipe and reseed from scratch.',
            );

            $this->printSummary($tenantId, $subdomain, seeded: false);

            return self::SUCCESS;
        }

        $this->info('Seeding fake demo data (admin/proctor/evaluator/candidates, questions, exam)...');
        Artisan::call('tenants:seed', [
            '--tenants' => [$tenantId],
            '--class' => TenantMasterSeeder::class,
            '--force' => true,
        ], $this->output);

        $this->printSummary($tenantId, $subdomain, seeded: true);

        return self::SUCCESS;
    }

    /**
     * Refuses to proceed unless the target id is either brand new or is
     * already, verifiably, our own sandbox tenant — never a real one — and
     * unless the subdomain it needs isn't already claimed by a different
     * tenant.
     */
    private function guardTargetIsSafe(string $tenantId, string $subdomain): bool
    {
        $existing = Tenant::find($tenantId);

        if ($existing !== null && $existing->organization_type !== 'sandbox') {
            $this->error(
                "Refusing to run: tenant {$tenantId} already exists and is NOT marked organization_type=sandbox. "
                . 'This looks like a real tenant. Check the SCRIBE_TENANT_ID environment value before retrying — '
                . 'this command will never modify a tenant it did not create.',
            );

            return false;
        }

        $domainOwner = Domain::where('domain', $subdomain)->first();

        if ($domainOwner !== null && (string) $domainOwner->tenant_id !== $tenantId) {
            $this->error(
                "Refusing to run: the subdomain '{$subdomain}' is already assigned to a different tenant "
                . "({$domainOwner->tenant_id}). Check SCRIBE_TENANT_SUBDOMAIN before retrying.",
            );

            return false;
        }

        return true;
    }

    private function tenantAlreadyHasData(Tenant $tenant): bool
    {
        $hasData = false;

        tenancy()->initialize($tenant);

        try {
            $hasData = DB::table('users')->exists();
        } finally {
            tenancy()->end();
        }

        return $hasData;
    }

    private function printSummary(string $tenantId, string $subdomain, bool $seeded): void
    {
        $this->newLine();
        $this->info('Docs Sandbox Tenant is ready.');
        $this->line("  Tenant ID:  {$tenantId}");
        $this->line("  Base URL:   http://{$subdomain}.localhost/api/v1");
        $this->line('  Seeded:     ' . ($seeded ? 'yes (fresh demo data)' : 'no (existing data kept)'));
        $this->line('  Test users: see database/seeders/TenantMasterSeeder.php (all password "password")');
    }
}
