<?php

namespace ClearView\Test\Fixture;

use ClearView\ClearView;
use ClearView\Config;
use ClearView\Mosaic;
use ClearView\Framework;
use ClearView\Facet;
use ClearView\Shard;

/**
 * Builds a ClearView element tree at runtime for unit testing.
 * Resets ClearView/Pane/Facet state in new()/reset() so tests do not leak.
 * Registers shards in Mosaic and can render individual elements or the full tree.
 * @see \ClearView\Test\Fixture\TestFixtureException
 */
class ViewBuilder
{
    /** @var string Pane name for this builder session */
    private string $panename;

    /** @var string Inlay name for this builder session */
    private string $inlayname;

    /** @var Mosaic The Mosaic for this builder session */
    private Mosaic $mosaic;

    /** @var array<string> Shard IDs in registration order */
    private array $shardIds = [];

    /** @var array<string,bool> Set of "inlay-id" keys for duplicate detection */
    private array $registered = [];

    /**
     * Create a fresh builder, resetting all singleton state.
     * @param string|null $panename  Pane name (default: 'TestPage')
     * @param string|null $inlay     Inlay name (default: 'Default')
     */
    public static function new(?string $panename = 'TestPage', ?string $inlay = 'Default'): self
    {
        $builder = new self();
        $builder->panename  = $panename;
        $builder->inlayname = $inlay;
        $builder->reset();

        // Pane Crystal: set Pane::name for template resolution
        Mosaic::setVar("Pane::name", $panename);
        // Page::url defaults to /<panename>/
        Mosaic::setVar("Page::url", "/{$panename}/");

        return $builder;
    }

    /** Reset ClearView, Pane, Mosaic, and Facet state so tests do not leak. */


    public function reset(): self
    {
        // Reset ClearView singleton: null the instance then create a fresh
        // one via reflection (avoids ClearView::init() which needs ProcessWire).
        $cvRef = new \ReflectionClass(ClearView::class);
        $cvProp = $cvRef->getProperty('instance');
        $cvProp->setAccessible(true);
        $cvProp->setValue(null, null);

        // Create a fresh ClearView instance headlessly
        $constructor = $cvRef->getConstructor();
        $constructor->setAccessible(true);
        $instance = $cvRef->newInstanceWithoutConstructor();
        $constructor->invoke($instance);
        $cvProp->setValue(null, $instance);

        // Create a fresh Mosaic owned by a Pane (replaces old Mosaic singleton).
        $this->mosaic = new Mosaic();
        new Framework($this->panename, $this->inlayname, $this->mosaic);

        // Set a stub CurrentPane so Element constructors don't crash.
        \ClearView\ClearView::CurrentPane(new Shard([
            'id'    => '_stub_',
            'inlay' => Config::SHARD_ANONINLAY,
        ]));

        // Reset Facet static state
        $fRef = new \ReflectionClass(Facet::class);
        foreach (['tagstack', 'oobCount', 'recordCount', 'containedCount', 'data'] as $field) {
            $fProp = $fRef->getProperty($field);
            $fProp->setAccessible(true);
            $default = in_array($field, ['tagstack', 'data'], true) ? [] : 0;
            $fProp->setValue(null, $default);
        }

        $this->shardIds   = [];
        $this->registered = [];

        return $this;
    }

    /** Add a raw Shard by ID. */


    public function withShard(string $id, array $data, ?string $inlay = null): self
    {
        $inlay = $inlay ?? $this->inlayname;
        $key   = "{$inlay}-{$id}";

        if (isset($this->registered[$key])) {
            throw new TestFixtureException("Duplicate shard ID '{$id}' in inlay '{$inlay}'");
        }

        $data['id']    = $id;
        $data['inlay'] = $inlay;

        $shard = Shard::loadShard($data);
        Mosaic::addShard($shard, id: $id, inlay: $inlay);

        $this->shardIds[]          = $id;
        $this->registered[$key]    = true;

        return $this;
    }

    /** Add an Element (glyph) by ID. */


    public function withElement(string $id, string $tag, array $attrs, ?string $inlay = null): self
    {
        $inlay = $inlay ?? $this->inlayname;
        $key   = "{$inlay}-{$id}";

        if (isset($this->registered[$key])) {
            throw new TestFixtureException("Duplicate element ID '{$id}' in inlay '{$inlay}'");
        }

        $attrs['id']    = $id;
        $attrs['inlay'] = $inlay;
        $attrs['glyph'] = $tag;

        $shard = Shard::loadShard($attrs);
        Mosaic::addShard($shard, id: $id, inlay: $inlay);

        $this->shardIds[]       = $id;
        $this->registered[$key] = true;

        return $this;
    }

    /** Attach a child shard to a parent shard by ID. */


    public function withChild(string $parentId, string $childId): self
    {
        $parent = Mosaic::index($this->inlayname, $parentId);
        if (!$parent) {
            throw new TestFixtureException("Parent shard '{$parentId}' not found in inlay '{$this->inlayname}'");
        }

        if (!isset($this->registered["{$this->inlayname}-{$childId}"])) {
            throw new TestFixtureException("Child shard '{$childId}' not found in inlay '{$this->inlayname}'");
        }

        $childShard = Mosaic::index($this->inlayname, $childId);
        $children   = $parent->getField('children') ?? [];
        $children[] = [
            'glyph'      => 'reference',
            'name'       => $childId,
            'inlay'      => $this->inlayname,
            '_refInlay' => $this->inlayname,
        ];
        $parent->setField('children', $children);

        return $this;
    }

    /** Set a Mosaic / Crystal variable. */


    public function withVar(string $expression, mixed $value): self
    {
        Mosaic::setVar($expression, $value);
        return $this;
    }

    /** Get a registered element by ID from Mosaic. */


    public function getElement(string $id): \ClearView\Element
    {
        $shard = Mosaic::index($this->inlayname, $id);
        if (!$shard || !($shard instanceof \ClearView\Element)) {
            throw new TestFixtureException("Named element '{$id}' not found in inlay '{$this->inlayname}'");
        }
        return $shard;
    }

    /** Render the assembled tree or a single named shard to HTML. */


    public function render(?string $id = null): string
    {
        if ($id !== null) {
            $key = "{$this->inlayname}-{$id}";
            if (!isset($this->registered[$key])) {
                throw new TestFixtureException("Named shard '{$id}' not found in inlay '{$this->inlayname}'");
            }
            $shard = Mosaic::index($this->inlayname, $id);
            return $shard->getHtml();
        }

        $output = '';
        foreach ($this->shardIds as $shardId) {
            $shard = Mosaic::index($this->inlayname, $shardId);
            if ($shard) {
                $output .= $shard->getHtml();
            }
        }
        return $output;
    }
}
