<?php

namespace ClearView\Test;

/**
 * Exception thrown when a test fixture encounters invalid or
 * inconsistent state: duplicate shard ids, missing named shards,
 * invalid stub data, etc.
 * This is a convenience alias for ClearView\Test\Fixture\TestFixtureException
 * so that test cases in the ClearView\Test namespace can catch it without
 * an explicit use import.
 */
class TestFixtureException extends \ClearView\Test\Fixture\TestFixtureException
{
}
