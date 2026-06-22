<?php

namespace ClearView;

use ClearView\Crystal;
use ClearView\Mosaic;
use ClearView\Page;
use ProcessWire;

/**
 * Pane crystal — ProcessWire page wrapper and named-element container.
 *
 * Registered under the "Pane" inlay for {{Pane::headline}} template
 * lookups.  getVar() checks Mosaic "Pane" inlay FIRST (for runtime
 * aside values set via fill()), then falls back to the ProcessWire
 * page field.  Writing to the PW page requires explicit Page crystal
 * access.
 *
 * @see \ClearView\Crystal
 * @see \ClearView\Page
 * @see \ClearView\PaneAttr   (attribute manager)
 */
class Pane extends Crystal
{
    /**
     * Initializes the Pane Crystal with a ProcessWire page.
     *
     * Called by Crystal::plugAllCrystals() with null, then replaced
     * by Runtime::__construct() with the actual pane page.
     *
     * @param mixed  $pwObject  The ProcessWire page (null during auto-plug).
     * @param string|null $panename
     * @param string|null $inlayname
     * @param mixed  $mos       Mosaic reference
     */
    public function __construct($pwObject = null, $panename = null, $inlayname = null, $mos = null)
    {
        parent::__construct($pwObject, $panename, $inlayname, $mos);
    }

    /**
     * Gets a variable from the Pane crystal.
     *
     * REVERSED from the old PaneCrystal: checks Mosaic "Pane" inlay
     * first, then falls back to the ProcessWire page field.  This
     * means runtime values (headline, summary, formtitle, etc.) take
     * priority over stored PW page data.
     *
     * @param string|null $key  The key to retrieve, or null for the PW object.
     * @return mixed  The value, wrapped Page for Wire objects, or null.
     */
    public function getVar($key = null)
    {
        if ($key === null || $key === '') {
            return $this->data[Config::PAGE_PWOBJECT];
        }

        // 1. Check Mosaic "Pane" inlay first — runtime values win.
        $shard = Mosaic::index('Pane', $key);
        if ($shard) {
            $val = $shard->getField('value') ?? $shard;
            if ($val !== null) {
                return $val;
            }
        }

        // 2. Fall back to the ProcessWire page field.
        $pwObject = $this->data[Config::PAGE_PWOBJECT];
        if ($pwObject instanceof \ProcessWire\Page) {
            $value = $pwObject->get($key);
            if ($value !== null) {
                if ($value instanceof \ProcessWire\Wire) {
                    return new Page($value);
                }
                return $value;
            }
        }

        // 3. Last-resort: raw Mosaic shard under the old lastInlay
        //    fallback (preserves transient values like Pane::Key).
        $lastInlay = Mosaic::getVar('Shared::lastInlay');
        if ($lastInlay) {
            $shard = Mosaic::index($lastInlay, "Pane::" . $key);
            if ($shard) {
                return $shard->getField('value') ?? $shard;
            }
        }

        return null;
    }

    /**
     * Setting a variable goes through the ProcessWire page (with sanitization),
     * same as the parent Page class.  Use Mosaic::setVar directly for runtime
     * pane-scoped values.
     */
    public function setVar(string $key, $value): void
    {
        $pwObject = $this->data[Config::PAGE_PWOBJECT];
        if ($pwObject instanceof \ProcessWire\Page) {
            $pwObject->set(
                $key,
                ClearView::Sanitizer()->sanitize($value, Config::SANI_PAGE_SAVE)
            );
        }
    }

    public function getField(string $key)
    {
        return $this->getVar($key);
    }

    /**
     * Load and resolve the body Element for a pane+inlay.
     *
     * Finds the ProcessWire page, converts its body field to an Element,
     * merges PaneAttr::attributes, and loads any template view.
     *
     * @param string      $panename
     * @param string      $inlayname
     * @param Mosaic|null $mosaic    Current Mosaic instance for attribute lookup.
     * @return \ClearView\Element
     */
    public static function load(string $panename, string $inlayname, ?Mosaic $mosaic = null): \ClearView\Element
    {
        // 1. Find the ProcessWire page for this pane
        $pwPage = \ProcessWire\pages()->get("name={$panename}");
        if (!$pwPage || !$pwPage->id) {
            $pwPage = \ProcessWire\pages()->get('/');
        }

        // 2. Get the body field
        $bodyField = $pwPage->get('body');
        if (empty($bodyField)) {
            $bodyField = '<div></div>';
        }

        // 3. Convert to Element via fromhtml → loadShard.
        //    Store under "Pane" inlay so Pane::body resolves via Mosaic.
        $data = \ClearView\jsonmangler::fromhtml((string)$bodyField);
        $element = \ClearView\Shard::loadShard($data, id: 'body', inlay: 'Pane');

        // 4. Merge PaneAttr attributes into the Element
        $mosaic = $mosaic ?? Mosaic::instance();
        $attrsShard = Mosaic::index('PaneAttr', 'attributes');
        if ($attrsShard) {
            $attrs = $attrsShard->getFields('');
            if (is_array($attrs)) {
                // Filter out internal keys
                unset($attrs['id'], $attrs['inlay'], $attrs['glyph'], $attrs['name']);
                foreach ($attrs as $key => $value) {
                    if (is_string($value)) {
                        $element->setField($key, $value);
                    }
                }
            }
        }

        // 5. Load template view if configured
        $viewName = Mosaic::getVar('Shared::templateView');
        if ($viewName) {
            $viewElement = \ClearView\View::loadView($viewName);
            if ($viewElement) {
                return $viewElement;
            }
        }

        return $element;
    }
}
