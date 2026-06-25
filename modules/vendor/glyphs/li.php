<?php

namespace ClearView\Element;

use ClearView\Element;
use ClearView\Facet;

class li extends Element
{
    public function render()
    {
        (new Facet($this))
            ->open('<li {{data-tooltip=data-tooltip}} {{hx}}>{{value}}');
    }
} // end of class
