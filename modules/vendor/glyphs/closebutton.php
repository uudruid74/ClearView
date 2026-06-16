<?php

namespace ClearView\Element;

use ClearView\Element;
use ClearView\Facet;
use ClearView\Config as Config;

class closebutton extends Element
{
    // inlay and id *must* be defined early
    public $inlay       = 'Pane';       // close the whole pane
    public $id          = Config::ID_CLOSE_BUTTON;
    public $buzz        = 15;           // tiny click
    public $script      = 'me("body").on("keydown", ev => { if (ev.key === "Escape") { htmx.trigger("button[rel=prev]", "click") } });';

    public function init()
    {
        $this
            ->debug("Initializing closebutton")
            ->initFields([
                'dest'          => Facet::_("#{{Config::layername_modal}} dialog"),
                'class'         => Config::CLASS_CLOSE_BUTTON,
                'hx-on:closepane' => "me('{{dest}}').fadeOut()",
                'hx-on:click'   => "halt(event); me('{{dest}}').fadeOut()",
            ]);
    }

    public function render()
    {
        new Facet(
        '<button
            {{id=id}}
            aria-label="Close"
            rel="prev"
            data-tooltip="Close"
            {{hx}}>'
        );
    }

} // end of class
