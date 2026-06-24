<?php

namespace ClearView\Test\Harness;

use ClearView\Test\TestHarnessException;

/**
 * Curl-based HTTP smoke-test helper for ClearView.
 * Preferred transport is PHP's curl_* extension.  Falls back to a shell
 * `curl` subprocess when the extension is unavailable.  Throws
 * TestHarnessException when neither curl transport is usable.
 * Usage:
 *   $server = ServerHarness::at('http://clearview.local')->asHtmx();
 *   $resp   = $server->get('/form/login/open/');
 *   assert($resp->status() === 200);
 *   assert(str_contains($resp->body(), '<form'));
 */
class ServerHarness
{
    private string $baseUrl;
    private int $timeout = 30;

    /** @var array<string, string> Default headers sent with every request. */
    private array $defaultHeaders = [];

    /** True when the PHP curl extension is loaded. */


    private bool $usePhpCurl;

    // ── Factory ──────────────────────────────────────────────────────

    /**
     * Create a harness pointed at a base URL.
     * @throws TestHarnessException when neither curl_* nor shell curl is available.
     * @param mixed $baseUrl Description.
     * @return self Description.
     */
    public static function at(string $baseUrl): self
    {
        $instance = new self();
        $instance->baseUrl = \rtrim($baseUrl, '/');
        $instance->detectCurl();
        return $instance;
    }

    /** Detect available curl transport. Throws on complete absence. */


    private function detectCurl(): void
    {
        $this->usePhpCurl = \function_exists('curl_init');

        if (! $this->usePhpCurl) {
            // Shell fallback check — try to execute `curl --version`
            $output = [];
            $code   = 0;
            \exec('curl --version 2>&1', $output, $code);
            if ($code !== 0) {
                throw new TestHarnessException(
                    'ServerHarness requires curl (PHP curl_* extension or shell curl binary). Neither is available.'
                );
            }
        }
    }

    // ── Builder ──────────────────────────────────────────────────────

    /** Add a default header sent with every request. */


    public function withHeader(string $name, string $value): self
    {
        $this->defaultHeaders[$name] = $value;
        return $this;
    }

    /** Mark every request as an HTMX request (sets HX-Request: true). */


    public function asHtmx(): self
    {
        return $this->withHeader('HX-Request', 'true');
    }

    /** Override the default 30-second timeout. */


    public function withTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        return $this;
    }

    // ── HTTP verbs ───────────────────────────────────────────────────

    public function get(string $path): TestResponse
    {
        return $this->request('GET', $path);
    }

    public function post(string $path, array $data = []): TestResponse
    {
        return $this->request('POST', $path, $data);
    }

    public function put(string $path, array $data = []): TestResponse
    {
        return $this->request('PUT', $path, $data);
    }

    public function delete(string $path): TestResponse
    {
        return $this->request('DELETE', $path);
    }

    /**
     * POST with Mosaic-encoded form keys.
     * Keys in $mosaicData are sent as-is — they should already follow the
     * Mosaic naming convention (e.g. "LoginForm-username").
     * @param mixed $path Description.
     * @param mixed $mosaicData Description.
     * @return TestResponse Description.
     */
    public function postMosaic(string $path, array $mosaicData): TestResponse
    {
        return $this->request('POST', $path, $mosaicData);
    }

    // ── Transport ────────────────────────────────────────────────────

    /**
     * Execute a single HTTP request.
     * @param string     $method  HTTP verb.
     * @param string     $path    URL path (appended to baseUrl).
     * @param array|null $data    POST / PUT body data.
     * @return TestResponse Description.
     */
    private function request(string $method, string $path, ?array $data = null): TestResponse
    {
        $url = $this->baseUrl . '/' . \ltrim($path, '/');

        return $this->usePhpCurl
            ? $this->phpCurlRequest($method, $url, $data)
            : $this->shellCurlRequest($method, $url, $data);
    }

    // ── PHP curl_* transport ─────────────────────────────────────────

    private function phpCurlRequest(string $method, string $url, ?array $data): TestResponse
    {
        $ch = \curl_init();

        \curl_setopt_array($ch, [
            \CURLOPT_URL            => $url,
            \CURLOPT_CUSTOMREQUEST  => $method,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_HEADER         => true,
            \CURLOPT_TIMEOUT        => $this->timeout,
            \CURLOPT_FOLLOWLOCATION => false,
            \CURLOPT_HTTPHEADER     => $this->buildHeaderLines(),
        ]);

        if ($data !== null) {
            \curl_setopt($ch, \CURLOPT_POSTFIELDS, \http_build_query($data));
        }

        $raw    = \curl_exec($ch);
        $errno  = \curl_errno($ch);
        $error  = \curl_error($ch);
        $info   = \curl_getinfo($ch);

        \curl_close($ch);

        if ($errno !== 0 || $raw === false) {
            return new TestResponse(0, $error ?: 'Unknown curl error');
        }

        return $this->parseHttpResponse($raw, (int) ($info['http_code'] ?? 0));
    }

    // ── Shell curl transport ─────────────────────────────────────────

    private function shellCurlRequest(string $method, string $url, ?array $data): TestResponse
    {
        $cmd = \sprintf(
            'curl -s -i -X %s --max-time %d',
            \escapeshellarg($method),
            $this->timeout
        );

        foreach ($this->defaultHeaders as $name => $value) {
            $cmd .= ' -H ' . \escapeshellarg("{$name}: {$value}");
        }

        if ($data !== null) {
            $cmd .= ' -d ' . \escapeshellarg(\http_build_query($data));
        }

        $cmd .= ' ' . \escapeshellarg($url) . ' 2>&1';

        $output = [];
        $code   = 0;
        \exec($cmd, $output, $code);

        $raw = \implode("\n", $output);

        if ($code !== 0) {
            return new TestResponse(0, $raw ?: "curl exited with code {$code}");
        }

        return $this->parseHttpResponse($raw, 0);
    }

    // ── Shared helpers ───────────────────────────────────────────────

    /** Build the HTTP header lines array for php-curl. */


    private function buildHeaderLines(): array
    {
        $lines = [];
        foreach ($this->defaultHeaders as $name => $value) {
            $lines[] = "{$name}: {$value}";
        }
        return $lines;
    }

    /**
     * Parse a raw HTTP response (headers + body) into a TestResponse.
     * Handles the standard "HTTP/1.x status\r\nHeader: value\r\n\r\nbody"
     * format returned by `curl -i` and CURLOPT_HEADER.
     * @param mixed $raw Description.
     * @param mixed $curlInfoStatus Description.
     * @return TestResponse Description.
     */
    private function parseHttpResponse(string $raw, int $curlInfoStatus): TestResponse
    {
        // Split headers from body at the first double-CRLF.
        $pos    = \strpos($raw, "\r\n\r\n");
        $header = $pos !== false ? \substr($raw, 0, $pos) : '';
        $body   = $pos !== false ? \substr($raw, $pos + 4) : $raw;

        $lines  = \explode("\r\n", $header);
        $status = $curlInfoStatus ?: $this->parseStatusLine($lines[0] ?? '');

        $headers = [];
        foreach ($lines as $i => $line) {
            if ($i === 0) {
                continue; // status line
            }
            $colon = \strpos($line, ':');
            if ($colon !== false) {
                $key   = \strtolower(\trim(\substr($line, 0, $colon)));
                $value = \trim(\substr($line, $colon + 1));
                $headers[$key] = $value;
            }
        }

        return new TestResponse($status, $body, $headers);
    }

    /** Extract the status code from an HTTP status line like "HTTP/1.1 200 OK". */


    private function parseStatusLine(string $line): int
    {
        if (\preg_match('/^HTTP\/\d\.\d\s+(\d{3})/', $line, $m)) {
            return (int) $m[1];
        }
        return 0;
    }
}
