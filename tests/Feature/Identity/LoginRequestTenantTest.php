<?php

declare(strict_types=1);

use App\Http\Requests\Identity\LoginRequest;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Guards the fail-closed contract: a LoginRequest that cannot resolve a
 * tenant — neither from explicit `tenant_id` payload nor from a bound
 * tenant context — must produce a 422 before any service code runs. The
 * pre-fix behaviour was a silent `tenant_id = ''` that turned every login
 * into a guaranteed `user_not_found`.
 */
it('aborts with 422 when no tenant context and no explicit tenant_id are provided', function (): void {
    $request = LoginRequest::create('/api/v1/auth/login', 'POST', [
        'email' => 'someone@example.test',
        'password' => 'secret',
    ]);
    $request->setContainer(app())->setRedirector(app(\Illuminate\Routing\Redirector::class));

    try {
        $request->validateResolved();
        $this->fail('Expected HttpException(422) before validation completes.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(422);
    }
});

it('accepts an explicit tenant_id when no tenant context is bound', function (): void {
    $explicit = '11111111-1111-1111-1111-111111111111';

    $request = LoginRequest::create('/api/v1/auth/login', 'POST', [
        'tenant_id' => $explicit,
        'email' => 'someone@example.test',
        'password' => 'secret',
    ]);
    $request->setContainer(app())->setRedirector(app(\Illuminate\Routing\Redirector::class));

    $request->validateResolved();

    expect($request->tenantId())->toBe($explicit);
});
