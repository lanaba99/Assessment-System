<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Config;

/**
 * The guard lives in AppServiceProvider::boot() (see its own docblock) and
 * runs on every application bootstrap. These tests call boot() directly on
 * a fresh provider instance under a manipulated environment/config rather
 * than spinning up a whole second Application, since boot()'s other side
 * effects (morph map, rate limiter, failed-job listener) are idempotent and
 * harmless to re-run.
 */
afterEach(function (): void {
    app()->detectEnvironment(fn () => 'testing');
});

it('refuses to boot with MAIL_MAILER=log outside local/testing environments', function (): void {
    Config::set('mail.default', 'log');
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => (new AppServiceProvider(app()))->boot())
        ->toThrow(RuntimeException::class, 'MAIL_MAILER=log is not allowed outside local/testing environments');
});

it('boots fine with MAIL_MAILER=log in the local environment', function (): void {
    Config::set('mail.default', 'log');
    app()->detectEnvironment(fn () => 'local');

    (new AppServiceProvider(app()))->boot();

    expect(true)->toBeTrue();
});

it('boots fine with MAIL_MAILER=log in the testing environment', function (): void {
    Config::set('mail.default', 'log');
    app()->detectEnvironment(fn () => 'testing');

    (new AppServiceProvider(app()))->boot();

    expect(true)->toBeTrue();
});

it('boots fine in production with a real mail transport configured', function (): void {
    Config::set('mail.default', 'smtp');
    app()->detectEnvironment(fn () => 'production');

    (new AppServiceProvider(app()))->boot();

    expect(true)->toBeTrue();
});
