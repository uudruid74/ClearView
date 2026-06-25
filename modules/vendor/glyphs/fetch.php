<?php

namespace ClearView\Element;

use ClearView\Element;

class fetch extends Element
{
    public $script = "{ await tick(); me('#{{dest}}').value = {{fetch}}); }";

    public function render(bool $capture = false): ?string
    {
        $this->initField('dest', $this->getVar("{{id}}"));    // set dest to write elsewhere

        new Facet('<input {{name=id}} type="hidden" {{hx}} {{value=value}}>');
    }

} // end of class
