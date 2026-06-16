<?php

namespace ClearView\Test\Fixture;

/**
 * Exception thrown by test fixtures when preconditions are violated.
 *
 * Distinct from ClearView\Exception (which extends ProcessWire's WireException)
 * so that test fixtures can signal errors without a ProcessWire dependency.
 */
class TestFixtureException extends \RuntimeException
{
}
