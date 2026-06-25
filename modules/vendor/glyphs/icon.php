<?php

namespace ClearView\Element;

use ClearView\Facet;
use ClearView\Element;
use ProcessWire;

class icon extends Element
{
    public function init()
    {
        if (!$this->hasField('src')) {
            $this->setField('src', $this->getVar('Find::url=/{{Pane::name}}/.icon')->url);
        }
        $this->initField('alt', Facet::_("{{title}} icon"));
    }
    public function render(bool $capture = false): ?string
    {
        new Facet('<img {{src=src}} {{alt=alt}} {{title=title}} {{hx}} {{disabled}}>');
    }

} // end of class
