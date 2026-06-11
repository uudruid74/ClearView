<?php

namespace ClearView\Element;

use ClearView\Element;
use ClearView\Facet;
use ClearView\Mosaic;

class main extends Element
{
    public function init()
    {
        $this->initFields([
            'hx-buzz'       => 50,
            'hx-indicator'  => 'this',
            'hx-boost'      => 'true',
            'hx-target'     => 'this',
            'hx-ext'        => 'preload buzz',
            'preload'       => 'always mouseover',
            'preload-images'=> 'true'
        ]);
    }


    public function render()
    {
        (new Facet($this))
            ->open(<<<EOT
                <main {{id=id}}
                    {{page-fields=page-fields}}
                    {{preload=preload}}
                    {{preload-images=preload-images}}
                    {{hx}}>{{value}}
EOT
            );
        // Push watched field OOB updates for hx-boost navigation.
        // When <main> navigates via hx-boost, fields listed in page-fields
        // (headline, summary, sidebar) live outside <main> and need updating.
        $this->pushWatchedFieldOOB();
    }

    /**
     * Pushes OOB HTML updates for ProcessWire page fields listed in page-fields.
     *
     * During hx-boost navigation, <main> content is swapped but header/sidebar
     * fields outside <main> stay stale. This iterates the comma-separated
     * page-fields list, reads each field from the ProcessWire Page, and emits
     * an hx-swap-oob element that replaces the innerHTML of #field.
     */
    protected function pushWatchedFieldOOB(): void
    {
        $pageFields = $this->getField('page-fields');
        if (empty($pageFields)) {
            return;
        }
        $fields = array_map('trim', explode(',', (string)$pageFields));
        $pageCrystal = Mosaic::getVar('Page', 'ClearView');
        if (!$pageCrystal) {
            return;
        }
        foreach ($fields as $field) {
            if (!$field) {
                continue;
            }
            $content = $pageCrystal->getField($field);
            if ($content !== null) {
                // OOB swap innerHTML so the target element's tag
                // (h2#headline, div#summary, layout#sidebar) is preserved.
                echo "<div id=\"{$field}\" hx-swap-oob=\"innerHTML\">{$content}</div>\n";
            }
        }
    }

} // end of class
