<?php

namespace ClearView\Element;

use ClearView\Element;
use ClearView\Facet;

/**
 * Loads a view and replaces itself with the rendered fragment.
 *
 * Used for form headers and any reusable layout piece. Supports
 * glob patterns (view="formheader/*") and subfolder references.
 *
 * When a view is specified, the <layout> element itself is not emitted;
 * only the view's Shard tree is rendered as children of the parent element.
 * Without a view, renders a <div> wrapper with captured children.
 */
class layout extends Element
{
    /**
     * Initializes the layout element.
     *
     * If a view is set, the Shard constructor already loaded it via
     * __loadExternal (set by jsonmangler::fromhtml). No additional
     * loading needed here — the view's children are already merged
     * into this Shard's data.
     */
    public function init()
    {
        // View loading is handled by __loadExternal in Shard constructor.
        // Default class/id can be applied here if not already set.
    }

    /**
     * Renders the layout.
     *
     * If a view is set, renders children only (the loaded view's Shard tree).
     * The <layout> element itself is not emitted.
     *
     * Without a view, emits a <div> wrapper with captured children.
     */
    public function render()
    {
        if (!empty($this->data['view'])) {
            // View mode: render children only, no wrapper element.
            $this->renderChildren();
        } else {
            // Default mode: <div> wrapper with captured children.
            (new Facet($this))
                ->open('<div {{id=id}} {{style=style}} {{hx}}>{{value}}')
            ;
        }
    }

} // end of class
