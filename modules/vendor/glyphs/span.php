<?php

namespace ClearView\Element;
use ClearView\Element;
use ClearView\Facet;

class span extends Element
{
    public function render(bool $capture = false): ?string
    {
        (new Facet($this))
            ->open('<span {{hx}}>{{value}}');
    }
} // end of class
