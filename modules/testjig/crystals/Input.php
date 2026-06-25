<?php

namespace ClearView;

/**
 * Null Input crystal — returns jig values for headless testing.
 * Used when ProcessWire is unavailable (CLI testing, headless mode).
 */
class Input extends Crystal
{
    public function __construct($pwObject = null, $panename = null, $inlayname = null, $mos = null)
    {
        $this->data = [];
    }

    public function getVar($key = null)
    {
        $fromMosaic = Mosaic::getVar($key, 'Input');
        error_log("Input crystal: getVar('$key') → " . var_export($fromMosaic, true));
        if ($fromMosaic !== null) return $fromMosaic;
        return $key ?? null;
    }
}
