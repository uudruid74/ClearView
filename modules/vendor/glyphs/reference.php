<?php

namespace ClearView\Element;

use ClearView\Element;
use ClearView\Mosaic;
use ClearView\Facet;
use ClearView\jsonmangler;
use ClearView\Config;

/**
 * A Reference is a proxy element that forwards all field access and rendering
 * to a named Shard stored in Mosaic. It carries only its target name when
 * serialized, ensuring named children live exactly once in Mosaic.
 *
 * References replace original tree slots after Shard::loadShard() stores
 * named children in Mosaic, preventing duplicate Shard instances and DOM
 * clutter from the legacy __unnamed system.
 *
 * @see \ClearView\Shard::loadShard()
 * @see \ClearView\Mosaic
 */
class reference extends Element
{
    /**
     * Constructs a Reference, forcing anonymous inlay so it never
     * registers in Mosaic (which would overwrite the target Shard).
     *
     * Captures the real inlay as _refInlay for Mosaic resolution.
     */
    public function __construct($obj = null, ?string $primaryField = null, ?string $named = null, ?string $childType = null)
    {
        if (is_array($obj)) {
            // Capture the real inlay for resolution before forcing anon.
            // On round-trip through Mosaic::loadMosaic, loadShard sets
            // $obj['inlay'] to the current inlay — save it so resolve()
            // can find the target.  When canonicalizeChildren() already
            // provided _refInlay, keep it.
            if (!isset($obj['_refInlay']) && isset($obj['inlay']) && $obj['inlay'] !== Config::SHARD_ANONINLAY) {
                $obj['_refInlay'] = $obj['inlay'];
            }
            $obj['inlay'] = Config::SHARD_ANONINLAY;
        }
        parent::__construct($obj, $primaryField, $named, $childType);
    }
    /**
     * Resolves the target Shard from Mosaic by inlay + name.
     *
     * @return \ClearView\Shard|null The resolved target, or null if not found.
     */
    protected function resolve(): ?\ClearView\Shard
    {
        $name = $this->data['name'] ?? null;
        if (!$name) {
            return null;
        }
        // References are transient (anon inlay) — the real inlay for
        // Mosaic resolution is stored in _refInlay to avoid overwriting
        // the target Shard's Mosaic entry.  _refInlay survives jsonmangler
        // round-trips (single-underscore prefix is not stripped).
        $inlay = $this->data['_refInlay']
              ?? Facet::inlay();
        return Mosaic::index($inlay, $name);
    }

    /**
     * Forwards field access to the resolved target Shard.
     *
     * Special fields:
     *  - 'name': returned from local data (the reference's own name).
     *  - 'glyph': always returns 'reference'.
     *  - 'id': delegated to the target's getField('id').
     *  - everything else: forwarded to the target.
     *
     * @param string $name Field name.
     * @return mixed The field value.
     */
    public function getField(string $name)
    {
        if ($name === 'name') {
            return $this->data['name'] ?? null;
        }
        if ($name === 'glyph') {
            return 'reference';
        }
        $target = $this->resolve();
        if ($target) {
            return $target->getField($name);
        }
        return parent::getField($name);
    }

    /**
     * Renders the Reference by delegating to the resolved target.
     */
    public function render()
    {
        $target = $this->resolve();
        if ($target) {
            $target->render();
        }
    }

    /**
     * Returns the HTML of the resolved target.
     *
     * @return string The rendered HTML.
     */
    public function getHtml(): string
    {
        $target = $this->resolve();
        if ($target) {
            return $target->getHtml();
        }
        return '';
    }

    /**
     * Serializes the Reference to mangled JSON.
     *
     * Overrides parent to emit only {glyph, name} — the minimal
     * payload needed to identify the target in Mosaic. The parent
     * mangle() would skip 'name' when it equals 'id' (which it
     * always does for references), so we pass a minimal array.
     *
     * @return string The mangled JSON string.
     */
    public function deflate(): string
    {
        return jsonmangler::mangle([
            'glyph' => 'reference',
            'name' => $this->data['name'] ?? '',
            '_refInlay' => $this->data['_refInlay'] 
                        ?? $this->data['inlay'] 
                        ?? Facet::inlay(),
        ]);
    }
}
