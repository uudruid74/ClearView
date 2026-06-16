<?php

namespace ClearView\Element;
use ClearView\Element;
use ClearView\Config;

/**
 * Invisible grouping Shard whose primary field is `children`.
 *
 * Renders nothing itself — only its children are emitted by the parent Facet.
 * Not mangled into <article> tags; stays a pure fragment throughout the pipeline.
 *
 * When used with view= attribute (e.g. <fragment view="icons/*"/>):
 *   - fromhtml() resolves the view and populates children inline.
 *   - Folder globs expand into sibling fragments from views/<context>/icons/*.php.
 *
 * @see \ClearView\jsonmangler::processNode()
 */
class fragment extends Element
{
    protected $primaryField = 'children';
    protected string $contentsType = self::ShardArray;

    public function render()
    {
        return;
    }
} // end of class
