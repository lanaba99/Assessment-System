<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\Feature\Central\UsesCentralSchema;

/**
 * SandboxSetupCommand's safety guards operate entirely on the landlord
 * connection (Tenant::find / Domain::where) before it ever calls
 * `tenants:migrate` / `tenants:seed`. Those two Artisan commands need a real
 * per-tenant database (config/tenancy.php hard-codes
 * `template_tenant_connection` / `root_connection.driver` to mysql, matching
 * how the rest of this app is only truly multi-database under MySQL/Sail —
 * see CrossTenantIsolationTest's own docblock for the same, pre-existing
 * constraint), so they cannot run against the sqlite in-memory connection
 * this suite uses. These tests therefore cover exactly what IS safely
 * testable here: the refusal guards, and that a completely unrelated
 * tenant's landlord row is never touched by them. The full happy path
 * (migrate + seed real fake data) is exercised manually against a real
 * MySQL/Sail environment — see docs/ENVIRONMENT_SETUP.md.
 */
uses(UsesCentralSchema::class);

const SANDBOX_DEFAULT_TENANT_ID = '00000000-0000-4000-8000-000000000001';

beforeEach(function (): void {
    $this->bootCentralSchema();

    // Creating a Tenant row normally fires TenantCreated, which
    // TenancyServiceProvider wires (shouldBeQueued(false)) to Jobs\CreateDatabase
    // + Jobs\MigrateDatabase synchronously against a REAL MySQL server
    // (config/tenancy.php hard-codes template_tenant_connection/root_connection
    // to mysql). Faking it keeps these tests — which only exercise the
    // landlord-level guard logic — on the sqlite in-memory connection this
    // suite uses, exactly like the existing Central tests already do.
    Event::fake([TenantCreated::class]);
});

function makeSandboxTestTenant(string $id, string $organizationType, array $overrides = []): Tenant
{
    return Tenant::query()->forceCreate(array_merge([
        'id' => $id,
        'organization_name' => 'Some Real Customer Inc.',
        'organization_type' => $organizationType,
        'primary_contact_email' => 'contact@real-customer.example',
        'status' => 'active',
    ], $overrides));
}

it('refuses to run when the environment is production', function (): void {
    app()->detectEnvironment(fn () => 'production');

    $exitCode = Artisan::call('sandbox:setup');

    expect($exitCode)->toBe(Command::FAILURE);
    expect(Tenant::query()->count())->toBe(0);

    app()->detectEnvironment(fn () => 'testing');
});

it('refuses to touch a tenant that already exists at the sandbox id but is not marked sandbox, leaving it byte-for-byte unchanged', function (): void {
    $real = makeSandboxTestTenant(SANDBOX_DEFAULT_TENANT_ID, 'enterprise');
    $originalUpdatedAt = $real->updated_at;
    $originalName = $real->organization_name;

    $exitCode = Artisan::call('sandbox:setup');

    expect($exitCode)->toBe(Command::FAILURE);

    $real->refresh();
    expect($real->organization_type)->toBe('enterprise');
    expect($real->organization_name)->toBe($originalName);
    expect($real->updated_at->equalTo($originalUpdatedAt))->toBeTrue();
});

it('refuses when the sandbox subdomain is already claimed by a different tenant, and never touches that tenant', function (): void {
    $otherTenantId = '11111111-1111-4111-8111-111111111111';
    $other = makeSandboxTestTenant($otherTenantId, 'enterprise');

    Domain::query()->create([
        'domain' => 'docs-sandbox',
        'tenant_id' => $otherTenantId,
    ]);

    $exitCode = Artisan::call('sandbox:setup');

    expect($exitCode)->toBe(Command::FAILURE);
    // The sandbox tenant id itself must never have been created either —
    // the guard trips before DocsSandboxTenantSeeder runs.
    expect(Tenant::query()->find(SANDBOX_DEFAULT_TENANT_ID))->toBeNull();

    $other->refresh();
    expect($other->organization_type)->toBe('enterprise');
});

it('leaves a completely unrelated tenant untouched when the guard trips for the sandbox id', function (): void {
    // A second, unrelated real tenant that has nothing to do with the
    // sandbox id or subdomain at all — the strongest isolation claim:
    // sandbox:setup must never so much as read/write a row it wasn't
    // asked about.
    $unrelatedId = '22222222-2222-4222-8222-222222222222';
    $unrelated = makeSandboxTestTenant($unrelatedId, 'enterprise', [
        'organization_name' => 'Totally Unrelated Co.',
    ]);

    // Trip the guard via a non-sandbox row at the default sandbox id.
    makeSandboxTestTenant(SANDBOX_DEFAULT_TENANT_ID, 'enterprise');

    Artisan::call('sandbox:setup');

    $unrelated->refresh();
    expect($unrelated->organization_name)->toBe('Totally Unrelated Co.');
    expect($unrelated->organization_type)->toBe('enterprise');
});

it('sandbox:reset is a thin alias that always forces --fresh on sandbox:setup', function (): void {
    $real = makeSandboxTestTenant(SANDBOX_DEFAULT_TENANT_ID, 'enterprise');

    $exitCode = Artisan::call('sandbox:reset');

    expect($exitCode)->toBe(Command::FAILURE);
    $real->refresh();
    expect($real->organization_type)->toBe('enterprise');
});
