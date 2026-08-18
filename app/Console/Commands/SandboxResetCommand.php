<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * SANDBOX-ONLY. Thin, discoverable alias for `sandbox:setup --fresh` — wipes
 * the Docs Sandbox Tenant's database and reseeds it from scratch. All of the
 * safety guards (production refusal, non-sandbox-tenant refusal) live in
 * SandboxSetupCommand; this command adds none of its own logic on purpose.
 */
class SandboxResetCommand extends Command
{
    protected $signature = 'sandbox:reset';

    protected $description = 'Sandbox-only: wipe and reseed the Docs Sandbox Tenant from scratch (alias for sandbox:setup --fresh)';

    public function handle(): int
    {
        return $this->call('sandbox:setup', ['--fresh' => true]);
    }
}
