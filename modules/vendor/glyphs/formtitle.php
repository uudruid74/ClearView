<?php

namespace ClearView\Element;

use ClearView\Facet;
use ClearView\Element;

class formtitle extends Element
{
    public $id = 'formtitle';

    public function init()
    {
        $this->initFields([
            'value' => $this->getVar('Page::displayname') ?? $this->getVar('Page::title'),
            'class' => 'formtitle'
        ]);
    }

    public function render()
    {
        new Facet('<div {{id=id}} {{hx}}>{{value}}');
    }

} // end of class
