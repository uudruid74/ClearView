<?php

namespace ClearView\Test;

use ClearView\Exception;

/**
 * Thrown when a test fixture receives invalid configuration or payload.
 *
 * Raised by InlayStub, InlayRegistry, ViewBuilder, and other test fixtures
 * when stub data is malformed, duplicates are registered, or missing shards
 * are referenced at render time.
 */
class TestFixtureException extends Exception
{
    /**
     * @param string $message Human-readable description of the fixture error.
     * @param int $code Optional error code (default 0).
     * @param \Throwable|null $previous Chained exception.
     */
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
