<?php

namespace ClearView\Element;

use ClearView\Element;
use ClearView\Facet;
use ClearView\Mosaic as MosaicStore;
use ClearView\Exception;

/**
 * Mosaic glyph — renders hidden Mosaic state inputs for client-side synchronization.
 *
 * On initial page load, outputs all Shards as hidden <input> fields inside a
 * preserved <div>. On HTMX updates, produces OOB hidden-input patches for
 * inserted, updated, or removed Shards.
 *
 * Replaces the old Facet::dumpVars() / Mosaic::outputMosaic() / Mosaic::updateMosaic()
 * call chain. Pane body templates now emit <mosaic /> to trigger this glyph.
 *
 * @see \ClearView\Mosaic
 * @see \ClearView\Facet
 */
class Mosaic extends Element
{
    /**
     * Renders Mosaic state as hidden inputs or OOB patches.
     *
     * Decision: if a Pane-name is present in the request (command execution),
     * produce update/insert OOB patches. Otherwise produce the full initial
     * Mosaic dump.
     */
    public function render()
    {
        $panename = Mosaic::index('ClearView', 'Input')->getVar("Pane-name");
        if (empty($panename)) {
            MosaicStore::outputMosaic();
        } else {
            MosaicStore::updateMosaic();
        }
    }
}
