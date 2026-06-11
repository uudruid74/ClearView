<?php

namespace ClearView\Element;

use ClearView\Element;
use ClearView\Facet;

class script extends Element
{
    public function render()
    {
        (new Facet($this))
            ->out("<script {{src=src}} {{integrity=integrity}} {{crossorigin=crossorigin}}></script>")
            ;
    }

} // end of class
