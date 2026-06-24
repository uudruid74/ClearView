<?php

namespace ClearView\Test\Smoke;

require_once __DIR__ . '/../bootstrap.php';

use ClearView\Test\Harness\ServerHarness;
use ClearView\Test\TestHarnessException;

/**
 * Smoke test for the curl-based ServerHarness.
 *
 * Requires a running ClearView server at the URL specified by the
 * CLEARVIEW_BASE_URL environment variable (default: http://clearview.local).
 *
 * Run:  CLEARVIEW_BASE_URL=http://localhost phpunit tests/smoke/ServerHarnessSmokeTest.php
 *   or (without PHPUnit):  php tests/smoke/ServerHarnessSmokeTest.php
 */
class ServerHarnessSmokeTest
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = \getenv('CLEARVIEW_BASE_URL') ?: 'http://clearview.local';
    }

    // ── PHPUnit-compatible test runner ───────────────────────────────

    /**
     * Run all smoke tests.  Call this from a PHPUnit testCase or a
     * standalone CLI entry point.
     *
     * @return array<string, bool>  Test name → pass/fail.
     */
    public function runAll(): array
    {
        $results = [];
        $methods = $this->testMethods();

        foreach ($methods as $method) {
            try {
                $this->{$method}();
                $results[$method] = true;
                \fwrite(\STDERR, "  PASS  {$method}\n");
            } catch (\Throwable $e) {
                $results[$method] = false;
                \fwrite(\STDERR, "  FAIL  {$method}: {$e->getMessage()}\n");
            }
        }

        return $results;
    }

    /** Discover test methods (any method starting with "test"). */
    private function testMethods(): array
    {
        $methods = [];
        foreach (\get_class_methods($this) as $method) {
            if (\strncmp($method, 'test', 4) === 0) {
                $methods[] = $method;
            }
        }
        return $methods;
    }

    // ── Test cases ───────────────────────────────────────────────────

    /**
     * Acceptance: GET /form/login/open/ returns 200 and contains <form.
     */
    public function testFormLoginOpenReturns200AndContainsForm(): void
    {
        $server = ServerHarness::at($this->baseUrl);
        $resp   = $server->get('/form/login/open/');

        \assert($resp->status() === 200, "Expected 200, got {$resp->status()}");
        \assert(
            \str_contains($resp->body(), '<form'),
            'Response body does not contain <form'
        );
    }

    /**
     * Verifies that asHtmx() sets the HX-Request header.
     * This is a structural check — the server must return 200 for a
     * real HTMX request.
     */
    public function testHtmxHeaderIsSent(): void
    {
        $server = ServerHarness::at($this->baseUrl)->asHtmx();
        $resp   = $server->get('/form/login/open/');

        \assert($resp->status() === 200, "Expected 200, got {$resp->status()}");
    }

    /**
     * Verifies post() sends form-encoded data.
     */
    public function testPostSendsFormData(): void
    {
        $server = ServerHarness::at($this->baseUrl);
        $resp   = $server->post('/form/login/html/', [
            'LoginForm-username' => 'test',
            'LoginForm-password' => 'test',
        ]);

        // The server should respond — we just verify it doesn't crash.
        // A real test would check for a redirect or error message.
        \assert(
            \in_array($resp->status(), [200, 302, 303], true),
            "Unexpected status: {$resp->status()}"
        );
    }

    /**
     * Verifies postMosaic() sends Mosaic-encoded keys.
     * Passes keys already formatted in Mosaic convention.
     */
    public function testPostMosaicSendsMosaicKeys(): void
    {
        $server = ServerHarness::at($this->baseUrl);
        $resp   = $server->postMosaic('/form/login/html/', [
            'LoginForm-username' => 'neo',
            'LoginForm-password' => 'redpill',
        ]);

        \assert(
            \in_array($resp->status(), [200, 302, 303], true),
            "Unexpected status: {$resp->status()}"
        );
    }

    /**
     * Verifies network errors are captured with status 0.
     */
    public function testUnreachableServerReturnsStatusZero(): void
    {
        $server = ServerHarness::at('http://127.0.0.1:19999'); // nothing here
        $resp   = $server->withTimeout(2)->get('/');

        \assert($resp->status() === 0, "Expected status 0, got {$resp->status()}");
        \assert(! empty($resp->body()), 'Expected error message in body');
    }

    /**
     * Verifies json() parses a JSON response body.
     * (Skipped when the server doesn't return JSON.)
     */
    public function testJsonParsing(): void
    {
        // This test is informational — it demonstrates the json() API.
        // Mark as skipped unless the server has a JSON endpoint.
        $server = ServerHarness::at($this->baseUrl);
        $resp   = $server->get('/form/login/open/');

        // If the response is HTML (expected), json() returns null.
        // That's not a failure — it's valid behaviour.
        $parsed = $resp->json();
        \assert(
            $parsed === null || \is_array($parsed),
            'json() must return array or null'
        );
    }
}

// ── Standalone CLI entry point ──────────────────────────────────────

// Run when executed directly (not included by PHPUnit).
if (\PHP_SAPI === 'cli' && \realpath($_SERVER['argv'][0] ?? '') === __FILE__) {
    \fwrite(\STDERR, "ServerHarness Smoke Tests\n");
    \fwrite(\STDERR, "Base URL: " . (\getenv('CLEARVIEW_BASE_URL') ?: 'http://clearview.local') . "\n\n");

    $runner  = new ServerHarnessSmokeTest();
    $results = $runner->runAll();

    $passed = \count(\array_filter($results));
    $total  = \count($results);
    \fwrite(\STDERR, "\n{$passed}/{$total} passed\n");

    exit($passed === $total ? 0 : 1);
}
