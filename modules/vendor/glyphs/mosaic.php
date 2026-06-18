<?php

namespace ClearView\Element;

use ClearView\Element;
use ClearView\Facet;
use ClearView\Pane;
use ClearView\Config;
use ClearView\Exception;

/**
 * Mosaic glyph — renders hidden Mosaic state inputs for client-side synchronization.
 *
 * On initial page load, outputs all Shards as hidden <input> fields inside a
 * preserved <div>. On HTMX updates, produces OOB hidden-input patches for
 * inserted, updated, or removed Shards.
 *
 * @see \ClearView\Mosaic
 * @see \ClearView\Facet
 */
class Mosaic extends Element
{
    /**
     * Renders Mosaic state as hidden inputs or OOB patches.
     */
    public function render()
    {
        $mosaic = ClearView::Mosaic();
        if (!$mosaic) {
            return;
        }
        $mosaic->outputMosaic();
    }
}
