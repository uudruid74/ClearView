<?php

namespace ClearView;

/**
 * Null Pane crystal — returns null for all values.
 * Used when ProcessWire is unavailable (CLI testing, headless mode).
 */
class PaneCrystal extends \ClearView\PaneCrystal
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
    public static function load(string $panename, ?string $inlayname = null): ?\ClearView\Element
    {
        // In null mode, return a stub Element if needed for rendering.
        // For now, return null — callers handle this gracefully.
        return null;
    }
}
