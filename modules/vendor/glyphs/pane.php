<?php

namespace ClearView\Element;

use ClearView\Element;
use ClearView\Facet;

class pane extends Element
{
    public function init()
    {
        $inlay = $this['inlay'] ?? 'ClearView';
        $this->initFields([
            'element'       => 'div',
            'hx-get'        => "/{{name}}/$inlay/open/",
            'hx-trigger'    => "load",
            'hx-indicator'  => 'this',
            'hx-target'     => 'this',
            'hx-swap'       => 'outerHTML',
        ]);
    }

    public function render()
    {
        (new Facet($this))
            ->open("<{{element}} {{id=id}} {{hx}} {{preload}} {{preload-images}}>")
            ;
    }

} // end of class
