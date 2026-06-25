<?php

namespace ClearView;

/**
 * Null Pane crystal — returns field names as values for getVar().
 * Used when ProcessWire is unavailable (CLI testing, headless mode).
 */
class PaneCrystal extends Crystal
{
    public $address = "Crystal-Pane";
    public function __construct($pwObject = null, $panename = null, $inlayname = null, $mos = null)
    {
        $this->data = [];
        parent::__construct($pwObject, $panename, $inlayname, $mos);
    }

    public function getVar($varname = null)
    {
        return $varname ?? null;
    }

    /** Headless Pane loader — returns null since no ProcessWire pages exist. */
    public static function load(string $panename, ?string $inlayname = null, ?Mosaic $mosaic = null): ?\ClearView\Element
    {
        return null;
    }
}
