<?php

namespace Tests\Feature;

use App\Http\Middleware\TrustHosts;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_security_headers_are_added_to_web_responses(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy');
    }

    public function test_logout_get_redirects_to_login_after_signing_out(): void
    {
        $this->get('/logout')
            ->assertRedirect('/login');
    }

    public function test_untrusted_host_is_rejected(): void
    {
        $request = Request::create('http://attacker.invalid/login');

        try {
            app(TrustHosts::class)->handle($request, fn () => new Response('ok'));
            $this->fail('The untrusted host was accepted.');
        } catch (\Throwable $exception) {
            if (method_exists($exception, 'getStatusCode')) {
                $this->assertSame(400, $exception->getStatusCode());
                return;
            }

            $this->assertInstanceOf(
                \Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException::class,
                $exception
            );
        }
    }
}
