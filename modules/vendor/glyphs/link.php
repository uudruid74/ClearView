<?php

namespace ClearView\Element;

use ClearView\Element;
use ClearView\Facet;

class link extends Element
{
    public function render()
    {
        (new Facet($this))
            ->out("<link {{rel=rel}} {{rel=rel}} {{href=href}} {{type=type}} {{sizes=sizes}} {{as=as}}>")
            ;
    }

} // end of class:wq

