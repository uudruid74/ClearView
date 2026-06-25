<?php

namespace ClearView\Element;
use ClearView\Element;
use ClearView\Facet;

class html extends Element
{
    public function render(bool $capture = false): ?string
    {
        (new Facet($this))
            ->open('<html {{lang=lang}} {{data-theme=data-theme}} {{manifest=manifest}} {{xmlns=xmlns}} {{hx}}>');
    }
} // end of class
