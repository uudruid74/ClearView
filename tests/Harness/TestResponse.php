<?php

namespace ClearView\Test\Harness;

/**
 * Wraps the raw result of a curl request made by ServerHarness.
 * Network / curl errors are captured with status 0 and the error message
 * set as the body so tests can still inspect the failure.
 */
class TestResponse
{
    private int $status;
    private string $body;
    /** @var array<string, string> */
    private array $headers;

    /**
     * @param int    $status  HTTP status code (0 on curl error).
     * @param string $body    Response body or curl error message.
     * @param array  $headers Parsed response headers (lowercase keys).
     */
    public function __construct(int $status, string $body, array $headers = [])
    {
        $this->status  = $status;
        $this->body    = $body;
        $this->headers = $headers;
    }

    /** HTTP status code. 0 indicates a curl / network error. */


    public function status(): int
    {
        return $this->status;
    }

    /** Full response body as a string. */


    public function body(): string
    {
        return $this->body;
    }

    /** All response headers as a lowercase-keyed associative array. */


    public function headers(): array
    {
        return $this->headers;
    }

    /** Single header value, or null if not present. */


    public function header(string $name): ?string
    {
        return $this->headers[\strtolower($name)] ?? null;
    }

    /**
     * Decode the body as JSON.
     * @return array|null  Parsed array, or null when the body is not valid JSON.
     */
    public function json(): ?array
    {
        $decoded = \json_decode($this->body, true);
        return \is_array($decoded) ? $decoded : null;
    }
}
