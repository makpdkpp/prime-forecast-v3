<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PhpSecurityConfigurationTest extends TestCase
{
    public function test_web_ini_disables_upload_progress_and_error_disclosure(): void
    {
        $settings = parse_ini_file(__DIR__.'/../../public/.user.ini', false, INI_SCANNER_TYPED);
        $this->assertSame(0, $settings['session.upload_progress.enabled']);
        $this->assertFalse($settings['display_errors']);
        $this->assertFalse($settings['display_startup_errors']);
        $this->assertFalse($settings['allow_url_include']);
        $this->assertTrue($settings['log_errors']);
    }

    public function test_apache_denies_access_to_web_ini(): void
    {
        $this->assertMatchesRegularExpression('/<Files "\.user\.ini">\s+Require all denied\s+<\/Files>/',
            file_get_contents(__DIR__.'/../../public/.htaccess'));
    }
}
