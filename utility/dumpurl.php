#!/usr/bin/php
<?php

// Path to your main ProcessWire index.php
$processWireBootstrap = "/var/www/devel.virtuallyreal.games/htdocs/index.php";

// Check for a URL argument from the command line
global $argv;
if (!isset($argv[1])) {
    fwrite(STDERR, "Error: You must provide a URL as a command line argument.\n");
    exit(1);
}

$fullUrl = $argv[1];
$parsedUrl = parse_url($fullUrl);
$path = trim($parsedUrl['path'] ?? '/', '/');

// Split path into segments
$segments = $path === '' ? [] : explode('/', $path);

// Optional 4th segment for 'nextinlay'
$nextInlay = $segments[3] ?? null;

// --- Detect CLI ---
$isCLI = php_sapi_name() === 'cli' || defined('STDIN');

// --- Create global CLI variable with extra fields ---
if ($isCLI) {
    $GLOBALS['cliUrlSegments'] = [
        'panename'      => $segments[0] ?? null,
        'inlayname'     => $segments[1] ?? null,
        'methodname'    => $segments[2] ?? null,
        'nextinlay'     => $nextInlay,
        'url'           => '/' . implode('/', $segments) . '/',
        'all'           => [],
        'requestMethod' => 'CLI'
    ];

    fwrite(STDERR, "--- CLI mode ---\n");
    fwrite(STDERR, "panename: " . ($GLOBALS['cliUrlSegments']['panename'] ?? 'NULL') . "\n");
    fwrite(STDERR, "inlayname: " . ($GLOBALS['cliUrlSegments']['inlayname'] ?? 'NULL') . "\n");
    fwrite(STDERR, "methodname: " . ($GLOBALS['cliUrlSegments']['methodname'] ?? 'NULL') . "\n");
    fwrite(STDERR, "nextinlay: " . ($GLOBALS['cliUrlSegments']['nextinlay'] ?? 'NULL') . "\n");
    fwrite(STDERR, "url: " . $GLOBALS['cliUrlSegments']['url'] . "\n");
    fwrite(STDERR, "requestMethod: " . $GLOBALS['cliUrlSegments']['requestMethod'] . "\n");
}

// --- Bootstrap ProcessWire ---
require_once($processWireBootstrap);

if (php_sapi_name() === 'cli' && isset($session)) {
    $session->close();
}

// --- Find page ---
$requestedPage = $pages->findOne("url=/{$path}/");
if (!$requestedPage instanceof ProcessWire\Page) {
    fwrite(STDERR, "Error: No ProcessWire page found for URL '{$path}'.\n");
    exit(1);
}

// Register the current page
$wire->wire('page', $requestedPage);

// Include template stack
$templateName = $requestedPage->template()->name;
$templateFile = $config->paths->templates . $templateName . ".php";
$initFile     = $config->paths->templates . "_init.php";
$mainFile     = $config->paths->templates . "_main.php";

ob_start();

if (is_file($initFile)) {
    fwrite(STDERR, "Including _init.php\n");
    include($initFile);
}

if (is_file($templateFile)) {
    fwrite(STDERR, "Including {$templateName}.php\n");
    include($templateFile);
} else {
    fwrite(STDERR, "Error: Template file '{$templateFile}' not found for page '{$requestedPage->path}'.\n");
    exit(1);
}

if (is_file($mainFile)) {
    fwrite(STDERR, "Including _main.php\n");
    include($mainFile);
}

// Output captured HTML
$output = ob_get_clean();
echo $output;

fwrite(STDERR, "\n--- Done ---\n");
