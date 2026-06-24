<?php

namespace ClearView\Test\Fixture;

use ClearView\Test\InlayRegistry;
use ClearView\Test\StubPane;
use ClearView\Shard;

/**
 * Synthetic data source for inlays under test.
 *
 * Registers with InlayRegistry so that ClearView::loadInlay()
 * returns a StubPane subclass instead of searching the real
 * module/glyph filesystem.
 *
 * Usage:
 *   InlayStub::for('OrderPane', 'OrderList')
 *       ->returns(['orders' => [['id' => 42]]])
 *       ->register();
 */
class InlayStub
{
    /** @var string */
    public string $panename;

    /** @var string */
    public string $inlayname;

    /** @var array|Shard|null The payload to return */
    public array|Shard|null $data = null;

    /** @var callable|null Alternative callable payload */
    public $callable = null;

    /** @var string|null Custom pane class (bypass StubPane) */
    public ?string $paneClass = null;

    /**
     * Target a specific pane/inlay pair.
     */
    public static function for(string $panename, string $inlayname): self
    {
        $stub = new self();
        $stub->panename  = $panename;
        $stub->inlayname = $inlayname;
        return $stub;
    }

    /**
     * Provide the payload the inlay would normally fetch.
     *
     * @param array|Shard $data
     */
    public function returns(array|Shard $data): self
    {
        $this->data     = $data;
        $this->callable = null;
        return $this;
    }

    /**
     * Provide a callable that receives (panename, inlayname, context)
     * and returns the payload.
     */
    public function returnsCallable(callable $fn): self
    {
        $this->callable = $fn;
        $this->data     = null;
        return $this;
    }

    /**
     * Register the stub in the central InlayRegistry.
     *
     * @throws TestFixtureException if no payload has been set
     */
    public function register(): self
    {
        if ($this->data === null && $this->callable === null) {
            throw new TestFixtureException(
                "InlayStub for '{$this->panename}/{$this->inlayname}': " .
                "call returns() or returnsCallable() before register()"
            );
        }

        InlayRegistry::register($this);
        return $this;
    }
}
