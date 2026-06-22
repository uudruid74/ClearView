<?php

namespace ClearView;

use ClearView\Crystal;
use ClearView\Mosaic;
use ProcessWire;

/**
 * Static crystal — namespaced session variables that survive page reload.
 *
 * Stores values in ProcessWire's session using the current pane name as
 * namespace and the current inlay + key as the session key.  Unlike
 * Mosaic-stored variables (which live in the DOM and reset on reload),
 * Static variables persist across full page loads.
 *
 * Template usage: {{Static::varname}}
 *
 * Pane and inlay must match at retrieval time — variables are scoped
 * to the (panename, inlayname) pair.
 *
 * @see \ClearView\Crystal
 * @see https://processwire.com/api/ref/session/
 */
class StaticCrystal extends Crystal
{
    /**
     * Initializes the Static crystal.
     *
     * No ProcessWire object — uses session() directly.
     */
    public function __construct($pwObject = null, $panename = null, $inlayname = null, $mos = null)
    {
        parent::__construct(null, 'Static', 'ClearView', $mos);
    }

    /**
     * Get a static session variable.
     *
     * Key is stored as "<inlayname>-<key>" under the pane name namespace.
     *
     * @param string|null $key  The variable name.
     * @return mixed  The stored value, or null.
     */
    public function getVar($key = null)
    {
        if ($key === null || $key === '') {
            return null;
        }

        $panename  = Mosaic::getVar('Input::panename');
        $inlayname = Mosaic::getVar('Input::inlayname') ?? 'Pane';
        $address   = "{$inlayname}-{$key}";

        return \ProcessWire\session()->getFor($panename, $address);
    }

    /**
     * Set a static session variable.
     *
     * Stores under "<inlayname>-<key>" in the pane name namespace.
     * Survives full page reloads and Mosaic destruction.
     *
     * @param string $key    The variable name.
     * @param mixed  $value  The value to store.
     */
    public function setVar(string $key, $value): void
    {
        $panename  = Mosaic::getVar('Input::panename');
        $inlayname = Mosaic::getVar('Input::inlayname') ?? 'Pane';
        $address   = "{$inlayname}-{$key}";

        \ProcessWire\session()->setFor($panename, $address, $value);
    }
}
