<?php
namespace ClearView;
use ClearView\Pane;
use ClearView\Facet;
use ClearView\Mosaic;
use ClearView\Shared;

/**
 * Subclass of Pane used when the URL includes an inlay segment.
 *
 * An inlay represents a tab/subpage inside a pane. The default html()
 * method renders Page::body and fires inlaychange when the inlay differs
 * from Shared::prevInlay.
 *
 * Inlay subclasses live under modules/<module>/panes/<panename>/<inlayname>.php
 * with class names like ClearView\<panename>_<inlayname>.
 *
 * @see ClearView\Pane
 * @see ClearView\Shared
 */
class Inlay extends Pane
{
    /**
     * Default HTML response for an inlay.
     *
     * Renders {{Page::body}} from the ProcessWire page matching the
     * pane/inlay URL. Fires inlaychange event when Shared::prevInlay
     * differs from the current inlay name.
     *
     * @return void
     */
    public function html(): void
    {
        $inlayName = ClearView::inlay();

        // Fire inlaychange if the inlay changed
        if (Shared::$prevInlay !== null && Shared::$prevInlay !== $inlayName) {
            $this->triggerevent('inlaychange', ['pane' => $this->getField('name')]);
        }

        (new Facet($this))
            ->html("{{Page::body}}")
            ->close();

        Shared::$prevInlay = $inlayName;
    }
}
