<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CheckPhpSecurity extends Command
{
    protected $signature = 'security:php-check';

    protected $description = 'Read-only PHP 8.4 production baseline check (CLI only; verify web PHP separately)';

    public function handle(): int
    {
        $checks = [
            'Production baseline PHP 8.4.27+ on 8.4 branch' => PHP_MAJOR_VERSION === 8
                && PHP_MINOR_VERSION === 4 && version_compare(PHP_VERSION, '8.4.27', '>='),
            'session.upload_progress.enabled disabled' => ini_get('session.upload_progress.enabled') !== false
                && ! filter_var(ini_get('session.upload_progress.enabled'), FILTER_VALIDATE_BOOLEAN),
            'display_errors disabled' => in_array(strtolower((string) ini_get('display_errors')), ['', '0', 'off'], true),
            'display_startup_errors disabled' => ! filter_var(ini_get('display_startup_errors'), FILTER_VALIDATE_BOOLEAN),
            'allow_url_include disabled' => ! filter_var(ini_get('allow_url_include'), FILTER_VALIDATE_BOOLEAN),
            'Laravel debug disabled' => ! config('app.debug'),
        ];

        $this->line('PHP '.PHP_VERSION.' / '.PHP_SAPI);
        $this->warn('Checks this process only. CLI does not load public/.user.ini. Verify Plesk web/FPM values separately.');
        $this->table(['Check', 'Result'], array_map(
            static fn ($name, $passed) => [$name, $passed ? 'PASS' : 'FAIL'],
            array_keys($checks), array_values($checks)
        ));

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
