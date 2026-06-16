<?php

namespace ClearView\Test;

/**
 * Exception thrown when a test fixture encounters invalid or
 * inconsistent state: duplicate shard ids, missing named shards,
 * invalid stub data, etc.
 */
class TestFixtureException extends \RuntimeException
{
}
