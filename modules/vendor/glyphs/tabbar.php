<?php

namespace ClearView\Element;

use ClearView\Config;
use ClearView\Element;
use ClearView\Facet;
use ClearView\Mosaic;
use ClearView\Shard;

// A tabbar breaks up a panel into inlays.
class tabbar extends Element
{
    public $inlay   = "Pane";
    public $id      = Config::ID_FORM_TABBAR;

    public function init()
    {
        $this->initFields([
            'hx-target' => '#{{Pane::name}}-{{Config::id_form_tabbody}}',
            'hx-swap'   => 'outerHTML',
            'extratabs' => $this->getVar("Page::extra_tabs"),
            'class'     => '{{Config::class_tabbar}} horizontal',
        ]);
        $this->initializeTabs();
    }

    /**
     * Renders the tabbar by initializing tabs and rendering contents.
     */
    public function render()
    {
        // Output the tabbar container
        $this->debug('TAB CONTENTS: {{id}} {{inlay}} :' . Facet::_($this->getField(Config::SHARD_ARRAYNAME)));
        new Facet("<ul {{id=id}} {{hx}}>");
    }
    /**
     * Initializes the tabbar contents by creating an array of tab elements.
     */
    protected function initializeTabs()
    {
        // Get comma-separated tab list
        $extraTabs = $this->getField('extratabs');  // Get the comma-separated string
        $pageName = $this->getVar('Page::name');    // Get current page name to activate

        // Merge the extratabs with existing tabs
        $tabs = $extraTabs ? array_filter(explode(',', trim($extraTabs))) : [];
        $tabs = array_merge([$pageName], $tabs);
        $tabList = array_unique(array_filter($tabs));
        $tabNames = array_map('trim', $tabList);

        // Explode into array of tab names
        $tabArray = [];
        $first = true;

        foreach ($tabNames as $tabname) {
            $tabElement = [
                'id'           => "{{Config::id_form_tabprefix}}{$tabname}",
                'hx-get'        => Facet::_("/{{Pane::name}}/{$tabname}/html/"),
                'hx-trigger'    => 'click',
                'glyph'         => 'li',
                'data-tooltip'  => ClearView::Mosaic()->getVar("Page::$tabname.displayname"),
                'inlay'         => $this->inlay(),
                'name'          => $tabname,
                'value'         => ClearView::Mosaic()->getVar("Find::url=/{{Pane::name}}/{$tabname}/.title"),
                // Javascript to make the tabs activate themselves
                'hx-on:click'   => "htmx.takeClass('#'+me(event).id,'active')"
            ];
            // Set cssclasses for the active tab
            if ($tabname === $pageName) {
                $this->addClass('active', $tabElement);
            }
            $this->debug("TAB: " . Facet::_($tabElement));
            $tabArray[] = Shard::loadShard($tabElement);
        }
        // Store the tab array in children
        $this->setField(Config::SHARD_ARRAYNAME, $tabArray);
    }

    // TODO: change li to new "tab" class, inherit from li
    // Update the tabbar contents by just pushing the new element id to chidren
}
