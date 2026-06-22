<?php

namespace ClearView;

/**
 * Null Pane crystal — returns null for all values.
 * Used when ProcessWire is unavailable (CLI testing, headless mode).
 */
class PaneCrystal extends \ClearView\Pane
{
    public function __construct($pwObject = null, $panename = null, $inlayname = null, $mos = null)
    {
        $this->data = [];
    }

    public function getVar($key = null)
    {
        return null;
    }

    /**
     * Headless Pane loader — returns null since no ProcessWire pages exist.
     */
    public static function load(string $panename, ?string $inlayname = null, ?Mosaic $mosaic = null): ?\ClearView\Element
    {
        return null;
    }
}
