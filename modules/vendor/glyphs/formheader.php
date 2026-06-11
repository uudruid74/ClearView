<?php

namespace ClearView\Element;

use ClearView\Element;
use ClearView\Facet;

class formheader extends Element
{
    public $id = 'formheader';
    public $__loadExternal = 'Page::contents';

    public function render()
    {
        new Facet('<div {{id=id}} {{hx}}>');
    }

} // end of class
