<?php

namespace ClearView\Test\Fixture;

use ClearView\Shard;
use ClearView\Test\InlayRegistry;
use ClearView\Test\TestFixtureException;

/**
 * Fluent builder for synthetic inlay data.
 *
 * Replaces a real inlay class with stub data so tests can assert rendered
 * output without ProcessWire or a real module stack.
 *
 * Usage:
 *   InlayStub::for('OrderPane', 'OrderList')
 *       ->returns(['orders' => [['id' => 42]]])
 *       ->register();
 */
class InlayStub
{
    private string $panename;
    private string $inlayname;
    private mixed $payload = null;
    private ?callable $callable = null;

    /**
     * @param string $panename  Target pane.
     * @param string $inlayname Target inlay.
     */
    private function __construct(string $panename, string $inlayname)
    {
        $this->panename  = $panename;
        $this->inlayname = $inlayname;
    }

    /**
     * Create a stub targeting a specific pane/inlay.
     */
    public static function for(string $panename, string $inlayname): self
    {
        return new self($panename, $inlayname);
    }

    /**
     * Provide the payload the inlay would normally fetch.
     *
     * @param array|Shard $data Static stub data.
     * @return $this
     */
    public function returns(array|Shard $data): self
    {
        $this->payload = $data;
        return $this;
    }

    /**
     * Provide a callable that receives ($panename, $inlayname, array $context)
     * and returns array|Shard.
     *
     * The callable is invoked at render time by StubPane.
     *
     * @param callable $fn Signature: fn(string $panename, string $inlayname, array $context): array|Shard
     * @return $this
     */
    public function returnsCallable(callable $fn): self
    {
        $this->callable = $fn;
        return $this;
    }

    /**
     * Register the stub in InlayRegistry so ClearView::loadInlay() resolves it.
     *
     * @return $this
     * @throws TestFixtureException if neither returns() nor returnsCallable() was called.
     */
    public function register(): self
    {
        if ($this->payload === null && $this->callable === null) {
            throw new TestFixtureException(
                "InlayStub for {$this->panename}/{$this->inlayname}: " .
                "call returns() or returnsCallable() before register()."
            );
        }

        // If a callable is configured, wrap it so the caller (StubPane) sees
        // the result.  StubPane calls InlayRegistry::getStub() — at that point
        // the callable is invoked once and the result is cached.
        if ($this->callable !== null) {
            $callable = $this->callable;
            $panename = $this->panename;
            $inlayname = $this->inlayname;

            // Wrap in a closure that invokes the callable on first access.
            $resolved = false;
            $cached   = null;

            $wrapped = function () use ($callable, $panename, $inlayname, &$resolved, &$cached) {
                if (!$resolved) {
                    $result = $callable($panename, $inlayname, []);
                    if (!is_array($result) && !($result instanceof Shard)) {
                        throw new TestFixtureException(
                            "InlayStub returnsCallable() for {$panename}/{$inlayname} " .
                            "returned invalid type: " . gettype($result)
                        );
                    }
                    $cached   = $result;
                    $resolved = true;
                }
                return $cached;
            };

            InlayRegistry::register($this->panename, $this->inlayname, $wrapped);
        } else {
            InlayRegistry::register($this->panename, $this->inlayname, $this->payload);
        }

        return $this;
    }
}
