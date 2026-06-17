<?php

namespace ClearView;

use ClearView\Crystal;
use ClearView\Mosaic;
use ClearView\Page;
use ProcessWire;

/**
 * Crystal for the pane page in ProcessWire.
 *
 * Wraps the ProcessWire page associated with the current pane URL.
 * After Crystal::plugAllCrystals() creates it with a null page,
 * ClearView::init() replaces it with the correct ProcessWire page.
 *
 * Field access (Pane::name, Pane::title, etc.) delegates to the
 * ProcessWire page.  Unknown keys fall back to raw Mosaic shards
 * under the "Pane" inlay so that transient values (e.g. Pane::Key
 * as a CSRF token from URL parameters) continue to work.
 *
 * @see \ClearView\Crystal
 * @see \ClearView\Page
 */
class PaneCrystal extends Crystal
{
    /**
     * Initializes the Pane Crystal with a ProcessWire page.
     *
     * Called by Crystal::plugAllCrystals() with null, then replaced
     * by ClearView::init() with the actual pane page.
     *
     * @param mixed $pwObject The ProcessWire page (null during auto-plug).
     */
    public function __construct($pwObject = null, $panename = null, $inlayname = null)
    {
        parent::__construct($pwObject, $panename, $inlayname);
    }

    /**
     * Gets a variable from the Pane Crystal.
     *
     * First tries the ProcessWire page's field.  If the page doesn't
     * have the field, falls back to a raw Mosaic shard under the
     * "Pane" inlay — this preserves transient values like Pane::Key
     * that arrive via URL parameters.
     *
     * @param string|null $key The key to retrieve, or null for the PW object.
     * @return mixed The value, wrapped Page for Wire objects, or null.
     */
    public function getVar($key = null)
    {
        if ($key === null || $key === '') {
            return $this->data[Config::PAGE_PWOBJECT];
        }

        $pwObject = $this->data[Config::PAGE_PWOBJECT];

        // Try the ProcessWire page field first
        if ($pwObject instanceof \ProcessWire\Page) {
            $value = $pwObject->get($key);
            if ($value !== null) {
                if ($value instanceof \ProcessWire\Wire) {
                    return new Page($value);
                }
                return $value;
            }
        }

        // Fall back to raw Mosaic shard for transient values (e.g. Pane::Key).
        // Check the "Pane" inlay first, then the shared last-inlay namespace
        // where loadMosaic stores URL parameters without a "-" separator.
        // The input key is the literal "Pane::Key", so reconstruct it.
        $shard = Mosaic::index('Pane', $key);
        if (!$shard) {
            $lastInlay = Mosaic::getVar('Shared::lastInlay');
            if ($lastInlay) {
                $shard = Mosaic::index($lastInlay, "Pane::" . $key);
            }
        }
        if ($shard) {
            return $shard->getField('value') ?? $shard;
        }

        return null;
    }

    /**
     * Setting a variable goes through the ProcessWire page (with sanitization),
     * same as the parent Page class.
     */
    public function setVar(string $key, $value): void
    {
        $pwObject = $this->data[Config::PAGE_PWOBJECT];
        if ($pwObject instanceof \ProcessWire\Page) {
            $pwObject->set(
                $key,
                Mosaic::index('ClearView', 'Sanitizer')->sanitize($value, Config::SANI_PAGE_SAVE)
            );
        }
    }

    public function getField(string $key)
    {
        return $this->getVar($key);
    }
}
