<?php

namespace ClearView\Element;

use ClearView\Element;
use ClearView\Facet;

class meta extends Element
{
    public function render()
    {
        (new Facet($this))
            ->out("<meta {{name=name}} {{content=content}}>")
            ;
    }

} // end of class
