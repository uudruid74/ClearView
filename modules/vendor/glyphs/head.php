<?php

namespace ClearView\Element;
use ClearView\Element;
use ClearView\Facet;

class head extends Element
{
    public function render()
    {
        (new Facet($this))
            ->open('<head {{hx}}>')
        ;
    }
} // end of class
