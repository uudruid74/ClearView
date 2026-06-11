<?php

namespace ClearView\Element;

use ClearView\Element;
use ClearView\Facet;

class layout extends Element
{
    public function render()
    {
        (new Facet($this))
            ->open('<div {{id=id}} {{style=style}} {{hx}}>{{value}}')
        ;
    }

} // end of class
