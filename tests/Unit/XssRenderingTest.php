<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class XssRenderingTest extends TestCase
{
    public function test_privileged_data_tables_render_untrusted_columns_as_text(): void
    {
        $root = dirname(__DIR__, 2);
        $views = [
            'resources/views/admin/dashboard_table.blade.php',
            'resources/views/teamadmin/dashboard_table.blade.php',
            'resources/views/admin/reports/bidding.blade.php',
            'resources/views/admin/reports/contract.blade.php',
            'resources/views/admin/reports/windate.blade.php',
            'resources/views/teamadmin/reports/bidding.blade.php',
            'resources/views/teamadmin/reports/contract.blade.php',
            'resources/views/teamadmin/reports/windate.blade.php',
        ];

        foreach ($views as $view) {
            $contents = file_get_contents($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $view));

            $this->assertStringContainsString(
                '$.fn.dataTable.render.text()',
                $contents,
                "Missing text renderer in {$view}"
            );
        }
    }

    public function test_dashboard_modal_values_are_html_escaped(): void
    {
        $root = dirname(__DIR__, 2);
        $views = [
            'resources/views/admin/dashboard.blade.php',
            'resources/views/teamadmin/dashboard.blade.php',
            'resources/views/user/dashboard.blade.php',
        ];

        foreach ($views as $view) {
            $contents = file_get_contents($root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $view));

            $this->assertStringContainsString('function escapeHtml(value)', $contents);
            $this->assertStringContainsString('escapeHtml(p.Product_detail)', $contents);
            $this->assertStringNotContainsString('${p.Product_detail ||', $contents);
        }
    }
}
