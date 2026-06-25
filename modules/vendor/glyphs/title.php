<?php

namespace ClearView\Element;

use ClearView\Element;
use ClearView\Facet;

class title extends Element
{
    public function init()
    {
        $this->initVar('value', "{{Config::title_prefix}} {{Page::title}}");
    }

    public function render(bool $capture = false): ?string
    {
        (new Facet($this))
            ->open("<title>{{value}}")
        ;
    }

} // end of class
