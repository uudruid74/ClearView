<?php

namespace ClearView\Element;

use ClearView\Element;
use ClearView\Mosaic;

// Specify a method in your form/page that matches the
// id of this element.  The class will then output this
// element itself, possibly by just calling newElement.
// This allows you to add some code inside a long json
// form description.

class redirect extends Element
{
    public function render()
    {
        new Facet(Mosaic::handleMethodCall($this->id()));
    }

} // end of class
