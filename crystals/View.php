<?php

namespace ClearView;

use ClearView\Crystal;
use ProcessWire;

/**
 * Crystal for deman loading views.
 * Use as {{View::viewname}} and returns the element.
 *
 * @see \ClearView\Crystal
 */
class View extends Crystal
{
    /**
     * Gets a View in html format and creates a local DOM formed from Shards
     * This let's you encapsulate your UI into small chunks you can include as
     * {{View::viewname}} in templates or __loadExternal declarations.
     *
     * @param string|null $key The view to retrieve
     * @return mixed The value of the field, the ProcessWire object, or null if not found.
     */
    public function getVar($key = null)
    {
        return ClearView::loadView($key);
    }
}
