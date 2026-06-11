<?php

namespace ClearView\Element;

use ClearView\Element;
use ClearView\Facet;

#[\AllowDynamicProperties]
class a extends Element
{
    public static $buzz = 20;

    public function init()
    {
        if ($this->hasField('switchtab')) {
            $this->initField('hx-on:click',
                "htmx.takeClass('#{{Pane::name}}-pane-{{Config::id_form_tabprefix}}{{switchtab}}', 'active');");
        }
    }

    public function render()
    {
        switch($this->linktype()) {
            // Link adds a tab to the form
            case 'addtab':
                new Facet("<a {{id=id}} hx-post='/form/{{addtab}}/addtab/' hx-swap='outerHTML' {{title=title}} {{hx}}>{{value}}");
                break;

            // Link switches to another tab
            case 'switchtab':
                new Facet("<a {{id=id}} hx-get='/form/{{switchtab}}/html/' hx-swap='outerHTML' {{title=title}} {{hx}}>{{value}}");
                break;

            // Email verification link
            case 'emailkey':
                $key = ClearView::hanna("[[user emailkey=1]]");       //FIXME: This needs to be in a class!
                new Facet("<a {{id=id}} href='/e/{$key}/' {{title=title}} {{hx}}>{{value}}");
                break;

            // Everything else (note hx-get instead of href)
            default:
                new Facet("<a {{id=id}} {{href=href}} {{title=title}} {{hx}}>{{value}}");
        }
    }

    protected function linktype() {
        return   $this->hasField('emailkey')    ? 'emailkey' :
                ($this->hasField('switchtab')   ? 'switchtab' :
                ($this->hasField('addtab')      ? 'addtab' :
                'Default'));
    }


} // end of class
