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
 * When used with view= attribute (e.g. <fragment view="head/*"/>):
 *   - Only explicit view= attributes trigger view loading.
 *   - Wildcard globs (*) load all matching files from ALL modules and combine as children.
 *   - Non-wildcard views load a single file from the first matching module.
 *
 * @see \ClearView\jsonmangler::processNode()
 */
class fragment extends Element
{
    protected $primaryField = 'children';

    public function render(bool $capture = false): ?string
    {
        return;
    }
} // end of class
