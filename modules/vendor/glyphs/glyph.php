<?php

namespace ClearView\Element;
use ClearView\Element;
use ClearView\Facet;

/**
 * Default render implementation for elements that don't have their own class
 */
class glyph extends Element
{
    /**
     * This is the default render for unknown elements
     */
    public function render(bool $capture = false): ?string
    {
        (new Facet($this))
            ->open(<<<EOT
<{{glyph}}
    
    {{name=name}}
    {{class=class}}
    {{content=content}}
    {{src=src}}
    {{style=style}}
{{hx}}>
{{value}}
EOT
            );
    }
}
// end of class
