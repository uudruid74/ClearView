<?php

namespace ClearView;

use ClearView\Facet;
use ClearView\Pane;

class Form extends Pane
{
    public $__loadExternal = 'Page::contents';

    /**
     * Closes the form programmatically (not called by close button) with an optional delay.
     *
     * @param int|null $delay The delay in milliseconds before closing the form.
     */
    public function close($delay = Config::FORM_CLOSE_DELAY): void
    {
        // don't ever busy wait!  Async sleep!
        ClearView::asyncjs("await sleep({$delay}); me('#{{Config::layername_modal}} dialog').fadeOut()");
    }

    /**
     * Renders the tab body of the form.
     */
    public function render(): void
    {
        (new Facet($this))
            ->open([
                'glyph'     => 'article',
                'id'        => '{{Pane::name}}-{{Config::id_form_tabbody}}',
                'class'     => '{{Config::class_tabbody}} vertical',
            ])
            ->debug("Tabbody output for {{id}}")
            ;
    }

    /**
     * Called by open top create a new form in a modal dialog
     */
    public function open(): void
    {
        // start by opening a container to align the form
        (new Facet($this))
            ->open('<dialog open>')
            ->debug("Opening Form!")

            // now open the main form
            ->open("<form
                id='{{Config::id_main_form}}'
                class='vertical'
                hx-include='#{{Pane::name}}'
                hx-indicator='this'
                hx-ext='preload buzz'
                hx-target='#{{Pane::name}}-{{Config::id_form_tabbody}}'
                preload='always mouseover' preload-images='true'
            >")
            ->create([ 'glyph' => 'closebutton' ])
            ->create([ 'glyph' => 'tabbar'])
            ->html()
            ->close();
    }
}
