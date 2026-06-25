<?php

namespace ClearView\Element;
use ClearView\Element;
use ClearView\Facet;
use ClearView\Mosaic;

/**
 * Container element that performs hx-boost and serves as the target
 * for inlay/layout swaps. Every boosted content area lives inside an <article>.
 *
 * For panes: the <article> receives the rendered Pane::body or Inlay::html() output.
 * For Main.php: the <article name="main"> inside the loaded layout receives
 * the page content; <attr view="..."> can retarget to <main> for layout switches.
 *
 * @see ClearView\Element
 * @see ClearView\Pane
 */
class article extends Element
{
    /**
     * Initialize the <article> glyph with hx-boost defaults.
     *
     * Sets hx-boost, hx-target, and hx-swap defaults for boosted navigation.
     * If 'inlay' attribute is set, initializes Shared::prevInlay on first load.
     *
     * @return void
     */
    public function init(): void
    {
        $this->initFields([
            'hx-boost'  => 'true',
            'hx-target' => 'this',
            'hx-swap'   => 'innerHTML',
        ]);

        // Seed Shared::prevInlay from the inlay attribute on first load
        $inlay = $this->getField('inlay');
        if ($inlay !== null) {
            $prevInlay = ClearView::Mosaic()->getVar('Shared::prevInlay');
            if ($prevInlay === null) {
                ClearView::Mosaic()->setVar('prevInlay', $inlay, 'Shared');
            }
        }
    }

    /**
     * Render the <article> element.
     *
     * Opens <article {{hx}}> for template-expanded children.
     *
     * @return void
     */
    public function render()
    {
        (new Facet($this))
            ->open('<article {{name=name}} {{hx}}>');
    }
} 
// end of class
