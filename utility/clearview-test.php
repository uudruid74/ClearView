#!/usr/bin/env php
<?php
/**
 * ClearView Test Runner
 * Thin CLI wrapper around PHPUnit that provides:
 *   - Suite selection (unit, integration, smoke)
 *   - Filter by test name
 *   - CI mode with concise output
 *   - Exit-code contract (0=pass, 1=fail, 2=bootstrap, 3=no match)
 * Usage: php utility/clearview-test.php [options]
 */

declare(strict_types=1);

// ─── Resolve project root ────────────────────────────────────────────────
define('CLEARVIEW_TEST_ROOT', dirname(__DIR__));

// ─── Parse CLI arguments ─────────────────────────────────────────────────
$longopts = [
    'filter:',
    'suite:',
    'ci',
    'base-url:',
    'no-smoke',
    'bootstrap:',
    'help',
];
$options = getopt('', $longopts);

if (isset($options['help'])) {
    echo <<<HELP
ClearView Test Runner

  php utility/clearview-test.php [options]

Options:
  --filter <pattern>     Run tests whose class/method matches <pattern>.
  --suite <name>         Run one PHPUnit testsuite (unit|integration|smoke).
  --no-smoke             Exclude smoke tests even if present in the suite.
  --base-url <url>       Override base URL for smoke tests.
  --ci                   Concise one-line-per-test output for CI.
  --bootstrap <file>     Override tests/bootstrap.php.
  --help                 Show this help.

Exit codes:
  0  All tests passed
  1  One or more tests failed
  2  Bootstrap / config / environment failure
  3  No tests matched the selector

HELP;
    exit(0);
}

// ─── Bootstrap ───────────────────────────────────────────────────────────
$bootstrap = $options['bootstrap'] ?? CLEARVIEW_TEST_ROOT . '/tests/bootstrap.php';

if (!file_exists($bootstrap)) {
    fwrite(STDERR, "Bootstrap file not found: {$bootstrap}\n");
    exit(2);
}

// ─── Load optional config ────────────────────────────────────────────────
$config = [];
$configPath = CLEARVIEW_TEST_ROOT . '/.clearview-test.json';
if (file_exists($configPath)) {
    $config = json_decode(file_get_contents($configPath), true) ?? [];
}

// Set CLEARVIEW_BASE_URL from CLI, config, or env
if (isset($options['base-url'])) {
    putenv('CLEARVIEW_BASE_URL=' . $options['base-url']);
} elseif (isset($config['smokeBaseUrl'])) {
    putenv('CLEARVIEW_BASE_URL=' . $config['smokeBaseUrl']);
}

// ─── Locate PHPUnit ──────────────────────────────────────────────────────
$phpunitBin = null;
$candidates = [
    CLEARVIEW_TEST_ROOT . '/phpunit.phar',
    CLEARVIEW_TEST_ROOT . '/vendor/bin/phpunit',
    'phpunit',  // system PATH
];

foreach ($candidates as $c) {
    if (file_exists($c) || ($c === 'phpunit' && shell_exec('which phpunit 2>/dev/null'))) {
        $phpunitBin = $c;
        break;
    }
}

if ($phpunitBin === null) {
    fwrite(STDERR, "PHPUnit not found. Place phpunit.phar in the project root.\n");
    exit(2);
}

// Resolve PHPUnit to absolute path (phpunit.phar needs it for --configuration)
if ($phpunitBin === 'phpunit') {
    $phpunitBin = trim(shell_exec('which phpunit') ?? '');
} elseif (!str_starts_with($phpunitBin, '/')) {
    $phpunitBin = CLEARVIEW_TEST_ROOT . '/' . $phpunitBin;
}

// ─── Build PHPUnit arguments ─────────────────────────────────────────────
$args = [];

// Configuration
$phpunitXml = CLEARVIEW_TEST_ROOT . '/phpunit.xml.dist';
if (!file_exists($phpunitXml)) {
    $phpunitXml = CLEARVIEW_TEST_ROOT . '/phpunit.xml';
}
if (file_exists($phpunitXml)) {
    $args[] = '--configuration=' . escapeshellarg($phpunitXml);
}

// Bootstrap
$args[] = '--bootstrap=' . escapeshellarg($bootstrap);

// Filter
if (isset($options['filter'])) {
    $args[] = '--filter=' . escapeshellarg($options['filter']);
}

// Suite selection
$suite = $options['suite'] ?? null;
$validSuites = ['unit', 'integration', 'smoke'];
if ($suite !== null && !in_array($suite, $validSuites, true)) {
    fwrite(STDERR, "Invalid suite '{$suite}'. Valid: unit, integration, smoke\n");
    exit(2);
}

if ($suite !== null) {
    $args[] = '--testsuite=' . escapeshellarg($suite);
}

// Exclude smoke tests
if (isset($options['no-smoke']) && $suite === null) {
    $args[] = '--exclude-group=smoke';
}

// CI mode
$ci = isset($options['ci']) || ($config['ciOutput'] ?? false);
if ($ci) {
    // TeamCity format gives structured output we can parse
    $args[] = '--no-progress';
    $args[] = '--no-output';
    // Use testdox for concise CI output
    $args[] = '--testdox';
}

// Colors off in CI
if ($ci) {
    $args[] = '--colors=never';
}

// ─── Run PHPUnit ─────────────────────────────────────────────────────────
$cmd = 'php ' . escapeshellarg($phpunitBin) . ' ' . implode(' ', $args) . ' 2>&1';
$output = [];
$exitCode = 1;

exec($cmd, $output, $exitCode);

// ─── Parse output for CI summary ─────────────────────────────────────────
if ($ci) {
    $passed = 0;
    $failed = 0;
    $skipped = 0;
    $results = [];

    foreach ($output as $line) {
        // PHPUnit TestDox format: " ✔ Method name" or " ✘ Method name"
        if (preg_match('/^[ ]*([✔✘]) (.+)$/u', $line, $m)) {
            $status = ($m[1] === '✔') ? 'PASS' : 'FAIL';
            $results[] = ['status' => $status, 'test' => trim($m[2])];
            if ($status === 'PASS') {
                $passed++;
            } else {
                $failed++;
            }
        }
        // Also catch "Class Name (PHPUnit\..."

        // Skipped tests
        if (preg_match('/^[ ]*(↩|ℹ|→) (.+)$/u', $line, $m)) {
            $results[] = ['status' => 'SKIP', 'test' => trim($m[2])];
            $skipped++;
        }
    }

    // If no results parsed, just output raw
    if (empty($results)) {
        echo implode("\n", $output) . "\n";
    } else {
        foreach ($results as $r) {
            printf("%-5s %s\n", $r['status'], $r['test']);
        }
        echo "\n";
    }

    printf("Passed: %d  Failed: %d  Skipped: %d\n", $passed, $failed, $skipped);
}

// Map PHPUnit exit codes to ClearView contract
// PHPUnit: 0=success, 1=failure, 2=error
// ClearView: 0=pass, 1=fail, 2=bootstrap/config/env, 3=no match

$totalLines = count($output);

// Detect "No tests executed" (PHPUnit 10+ uses Warnings, exit 0 or 1)
$noTests = false;
foreach ($output as $line) {
    if (stripos($line, 'No tests executed') !== false
        || stripos($line, 'no tests') !== false
        || stripos($line, 'Cannot find file') !== false) {
        $noTests = true;
        break;
    }
}

if ($noTests) {
    fwrite(STDERR, "No tests matched the selector.\n");
    exit(3);
}

exit($exitCode);
