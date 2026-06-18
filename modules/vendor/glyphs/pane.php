<?php

namespace ClearView\Element;

use ClearView\Element;
use ClearView\Facet;

class pane extends Element
{
    public function init()
    {
        $inlay = $this['inlay'] ?? 'ClearView';
        $this->initFields([
            'element'       => 'div',
            'hx-get'        => "/{{name}}/$inlay/open/",
            'hx-trigger'    => "load",
            'hx-indicator'  => 'this',
            'hx-target'     => 'this',
            'hx-swap'       => 'outerHTML',
        ]);
    }

    public function render()
    {
        // Build hx-vals from all Shard attributes (skip internal/HTMX keys)
        $vals = [];
        $skip = ['element', 'glyph', 'id', 'inlay', 'name',
                  'hx-get', 'hx-trigger', 'hx-target', 'hx-swap',
                  'hx-indicator', 'hx-vals', 'preload', 'preload-images',
                  '__loadExternal', '__pF'];
        $this->iterateFields(function($value, $key) use (&$vals, $skip) {
            if (!in_array($key, $skip, true) && is_string($value)) {
                $vals[$key] = $value;
            }
        }, null);
        if (!empty($vals)) {
            $this->setField('hx-vals', json_encode($vals));
        }

        (new Facet($this))
            ->open("<{{element}} {{id=id}} {{hx}} {{preload}} {{preload-images}}>")
            ;
    }

} // end of class
