<?php

namespace ClearView\Element;
use ClearView\Element;
use ClearView\Facet;

class img extends Element
{
    public function render(bool $capture = false): ?string
    {
        new Facet('<img {{src=src}} {{width=width}} {{height=height}} {{alt=alt}} {{hx}}>');
    }
} // end of class
