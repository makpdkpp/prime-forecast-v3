<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;
use Illuminate\Http\Request;

class TrustHosts extends Middleware
{
    protected function shouldSpecifyTrustedHosts(): bool
    {
        return true;
    }

    public function handle(Request $request, $next)
    {
        $host = $request->getHost();
        $trusted = collect(array_filter($this->hosts()))->contains(
            fn (string $pattern) => preg_match('#'.$pattern.'#iD', $host) === 1
        );

        abort_unless($trusted, 400, 'Invalid Host header.');

        return parent::handle($request, $next);
    }

    /**
     * Get the host patterns that should be trusted.
     *
     * @return array<int, string|null>
     */
    public function hosts(): array
    {
        $hosts = [
            $this->allSubdomainsOfApplicationUrl(),
        ];

        if (app()->environment(['local', 'testing'])) {
            $hosts[] = '^localhost$';
            $hosts[] = '^127\.0\.0\.1$';
        }

        return $hosts;
    }
}
