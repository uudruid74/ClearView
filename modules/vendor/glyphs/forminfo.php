<?php

namespace ClearView\Element;

use ClearView\Element;
use ClearView\Facet;

class forminfo extends Element
{
    public $id = 'forminfo';

    public function init()
    {
        $this->initFields([
            'value' => $this->getVar('Page::headline'),
            'class' => 'forminfo'
        ]);
    }

    public function render()
    {
        new Facet('<div {{id=id}} {{hx}}>{{value}}');
    }

} // end of class
