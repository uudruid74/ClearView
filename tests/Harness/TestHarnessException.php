<?php

namespace ClearView\Test;

/**
 * Exception thrown when the test harness encounters a fatal error:
 * missing curl, unreachable server, invalid configuration, etc.
 */
class TestHarnessException extends \RuntimeException
{
}
