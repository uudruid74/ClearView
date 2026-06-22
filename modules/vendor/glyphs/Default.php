<?php
namespace ClearView;
use ClearView\Runtime;
use ClearView\Facet;

/**
 * DefaultPane — legacy glyph replaced by Main.
 *
 * @deprecated Use ClearView\\Main instead.
 */
class DefaultPane extends Runtime
{
    public $__loadExternal = "View::Default";

    public function render()
    {
        Exception::outputComment("Default Page Rendering - render");
        (new Facet($this))
            ->out("<!DOCTYPE html>")
            ->open("<html {{lang=lang}} {{data-theme=data-theme}} {{manifest=manifest}} {{dir=dir}} {{xmlns=xmlns}}>")
        ;
    }
}
// end of class
