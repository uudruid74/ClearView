<?php

namespace ClearView\Element;

use ClearView\Element;
use ClearView\Facet;
use ClearView\Mosaic;

/**
 * Container for the primary page content.
 *
 * Does NOT hx-boost itself; boosting is delegated to the inner <article>.
 * Calls Mosaic outputMosaic() to preserve Mosaic state across swaps.
 * Loads the view file specified by Shared::mainLayout (or the view attribute),
 * and renders captured children inside it.
 */
class main extends Element
{
    /**
     * Initialize defaults — no hx-boost on <main>.
     */
    public function init()
    {
        $this->initFields([
            'hx-indicator'   => 'this',
            'hx-ext'         => 'preload buzz',
            'preload'        => 'always mouseover',
            'preload-images' => 'true',
        ]);

        // Seed view from Shared::mainLayout if not already set on the element.
        if (!$this->getField('view') && ($sl = ClearView::Mosaic()->getVar('Shared::mainLayout'))) {
            $this->setField('view', $sl);
        }

        // Persist the current view so the client round-trips correctly.
        if ($view = $this->getField('view')) {
            ClearView::Mosaic()->setVar('Shared::mainLayout', $view);
        }
    }

    /**
     * Opens <main>, emits preserved Mosaic inputs, then loads
     * and renders the current view or captured children.
     */
    public function render(bool $capture = false): ?string
    {
        $facet = (new Facet($this))
            ->open('<main {{class=class}} {{hx}}>');

        // Emit the Mosaic hidden inputs inside <main> so they survive swaps.
        $facet->create(new \ClearView\Element\Mosaic());

        // Update fields that live outside <main> (headline, summary, sidebar).
        $this->pushWatchedFieldOOB();

        // Render captured children (article, aside, etc. from the view).
        $facet->renderChildren();
    }

    /**
     * Pushes OOB HTML updates for ProcessWire page fields listed in page-fields.
     *
     * During hx-boost navigation, <article> content is swapped but header/sidebar
     * fields outside the boost target stay stale. This iterates the comma-separated
     * page-fields list, reads each field from the ProcessWire Page, and emits
     * an hx-swap-oob element that replaces the innerHTML of #field.
     */
    protected function pushWatchedFieldOOB(): void
    {
        $pageFields = $this->getField('page-fields');
        if (empty($pageFields)) {
            return;
        }
        $fields = array_map('trim', explode(',', (string)$pageFields));
        $pageCrystal = ClearView::Mosaic()->getVar('Page', 'ClearView');
        if (!$pageCrystal) {
            return;
        }
        foreach ($fields as $field) {
            if (!$field) {
                continue;
            }
            $content = $pageCrystal->getField($field);
            if ($content !== null) {
                echo "<div id=\"{$field}\" hx-swap-oob=\"innerHTML\">{$content}</div>\n";
            }
        }
    }

} // end of class
