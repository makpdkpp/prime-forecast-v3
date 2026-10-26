<?php

namespace Tests\Feature;

use Tests\TestCase;

class PhpSecurityCheckTest extends TestCase
{
    public function test_diagnostic_fails_when_laravel_debug_is_enabled(): void
    {
        config()->set('app.debug', true);

        $this->artisan('security:php-check')
            ->expectsOutputToContain('CLI does not load public/.user.ini')
            ->expectsOutputToContain('Laravel debug disabled')
            ->assertFailed();
    }
}
