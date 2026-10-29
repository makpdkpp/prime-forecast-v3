<?php

// Standalone entry point: do not bootstrap the application against the normal DB.
$root = dirname(__DIR__);
chdir($root);
if (in_array('--help', $argv, true)) {
    echo "Usage: php scripts/mcp-preflight.php\nRuns isolated Laravel MCP tests and writes outputs/mcp-preflight/<run>/report.json\nExit 0 = automated tests pass; exit 1 = failed or unavailable. Demo acceptance remains required.\n";
    exit(0);
}
if (count($argv) > 1) {
    fwrite(STDERR, "Unknown arguments. Use --help.\n");
    exit(1);
}

$run = gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));
$directory = $root.'/outputs/mcp-preflight/'.$run;
if (! mkdir($directory, 0777, true) && ! is_dir($directory)) {
    fwrite(STDERR, "Cannot create report directory.\n");
    exit(1);
}
$report = [
    'run_id' => $run,
    'started_at' => gmdate(DATE_ATOM),
    'scope' => 'Laravel gateway, OAuth and sales drafts; isolated SQLite in memory',
    'automated_status' => 'failed',
    'release_decision' => 'NOT_APPROVED',
    'production_sales_write' => 'Blocked by current environment policy; separate implementation and review required',
    'manual_acceptance' => 'pending; see docs/mcp-preflight.md',
    'summary' => null,
];

$prerequisites = is_file($root.'/vendor/bin/phpunit')
    && extension_loaded('pdo_sqlite') && extension_loaded('dom') && function_exists('proc_open');
$exitCode = 1;
if ($prerequisites) {
    $process = proc_open([
        PHP_BINARY, $root.'/vendor/bin/phpunit', '--configuration', $root.'/phpunit.mcp.xml',
        '--log-junit', $directory.'/junit.xml', '--colors=never',
    ], [0 => ['pipe', 'r'], 1 => ['file', $directory.'/test-output.txt', 'w'], 2 => ['file', $directory.'/test-errors.txt', 'w']], $pipes, $root);
    if (is_resource($process)) {
        fclose($pipes[0]);
        $exitCode = proc_close($process);
    }
    if (is_file($directory.'/junit.xml')) {
        $xml = new DOMDocument;
        if (@$xml->load($directory.'/junit.xml', LIBXML_NONET)) {
            $suites = (new DOMXPath($xml))->query('/testsuites/testsuite');
            $summary = array_fill_keys(['tests', 'assertions', 'failures', 'errors', 'skipped'], 0);
            foreach ($suites as $suite) {
                foreach (array_keys($summary) as $key) {
                    $summary[$key] += (int) $suite->getAttribute($key);
                }
            }
            $report['summary'] = $summary;
            if ($exitCode === 0 && $summary['tests'] > 0 && $summary['failures'] + $summary['errors'] + $summary['skipped'] === 0) {
                $report['automated_status'] = 'passed';
            }
        }
    }
} else {
    $report['error'] = 'Requires Composer vendor dependencies, pdo_sqlite, dom and proc_open.';
}
$report['finished_at'] = gmdate(DATE_ATOM);
file_put_contents($directory.'/report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
require __DIR__.'/mcp-preflight-html.php';
file_put_contents($directory.'/report.html', renderMcpPreflightHtml($report));
echo 'Automated MCP checks: '.strtoupper($report['automated_status']).PHP_EOL;
if ($report['summary']) {
    echo json_encode($report['summary']).PHP_EOL;
}
echo 'Release decision: NOT_APPROVED (Demo acceptance and production write policy required)'.PHP_EOL;
echo 'Report: '.$directory.'/report.json'.PHP_EOL;
echo 'HTML report: '.$directory.'/report.html'.PHP_EOL;
echo 'Local test diagnostics: '.$directory.'/test-output.txt'.PHP_EOL;
exit($report['automated_status'] === 'passed' ? 0 : 1);
