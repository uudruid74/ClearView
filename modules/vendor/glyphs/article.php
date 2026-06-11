<?php

namespace ClearView\Element;
use ClearView\Element;
use ClearView\Facet;

class article extends Element
{
    public function render()
    {
        (new Facet($this))
            ->open('<article {{id=id}} {{hx}}>');
    }
} 
// end of class
