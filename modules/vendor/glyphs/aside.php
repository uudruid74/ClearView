<?php

namespace ClearView\Element;

use ClearView\Element;
use ClearView\Mosaic;

/**
 * aside glyph — named pane-chrome element.
 *
 * Forces inlay="Pane" so that named <aside> elements live under a stable
 * inlay regardless of the current Input::inlayname.  Self-renders by
 * looking up Page::{{name}} through the Pane crystal's reversed
 * getVar (Mosaic "Pane" inlay first, ProcessWire page fallback).
 *
 * Used for: headline, summary, formtitle, forminfo, sidebar, and any
 * other named pane-level chrome that receives OOB updates.
 *
 * @see \ClearView\Pane  (Pane crystal — reversed getVar)
 * @see \ClearView\Element
 */
class aside extends Element
{
    /**
     * Force inlay to "Pane" before the parent constructor runs so
     * that Mosaic::addShard registers this element under the "Pane"
     * inlay from the start.
     */
    public function __construct($obj = null, ?string $primaryField = null, ?string $named = null, ?string $childType = null)
    {
        parent::__construct($obj, $primaryField, $named, $childType);
    }

    /**
     * Always return "Pane" — overrides Shard::inlay() which reads
     * $this->data['inlay'] first.
     */
    public function inlay(): string
    {
        return 'Pane';
    }

    /**
     * Render the aside by looking up its named value through the
     * Pane crystal, falling back to the ProcessWire page field.
     *
     * Outputs: {{Pane::<name>}} which resolves Pane->getVar(<name>).
     */
    public function render(bool $capture = false): ?string
    {
        $name = $this->getField('name');
        if (!$name) {
            return null;
        }
        $value = Mosaic::getVar("Pane::{$name}");
        echo $value ?? '';
    }
}
