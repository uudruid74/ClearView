<?php

namespace ClearView\Element;
use ClearView\Element;
use ClearView\Facet;

class img extends Element
{
    public function render()
    {
        new Facet('<img {{id=id}} {{src=src}} {{width=width}} {{height=height}} {{alt=alt}} {{hx}}>');
    }
} // end of class
