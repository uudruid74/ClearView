<?php

namespace ClearView\Element;
use ClearView\Element;
use ClearView\Facet;

class span extends Element
{
    public function render()
    {
        (new Facet($this))
            ->open('<span {{hx}}>{{value}}');
    }
} // end of class
