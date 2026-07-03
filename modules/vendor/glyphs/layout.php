<?php

namespace ClearView\Element;

use ClearView\Element;
use ClearView\Facet;

/**
 * Layout element — loads a view and replaces itself with the rendered fragment.
 *
 * Used for form headers and reusable layout pieces.
 *
 * When a view= attribute is set:
 *   - fromhtml() sets __loadExternal, which the Shard constructor resolves,
 *     populating the element with the view's Shard tree.
 *   - render() acts like a fragment: no wrapper emitted, children are rendered
 *     by the parent Facet.  The <layout> element itself is invisible.
 *
 * When no view= is set:
 *   - render() emits a <div> wrapper with id, style, and hx attributes,
 *     rendering captured children or value inside.
 *
 * @see \ClearView\Element\fragment
 * @see \ClearView\jsonmangler::processNode()
 */
class layout extends Element
{
    public function render(bool $capture = false): ?string
    {
        // View mode: become invisible — let children render through parent.
        if (!empty($this['view'])) {
            return null;
        }

        // Default mode: emit <div> wrapper.
        (new Facet($this))
            ->open('<div {{style=style}} {{hx}}>{{value}}')
        ;
    }
}
