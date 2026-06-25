<?php

namespace ClearView;

/**
 * Null Session crystal — returns null for all values.
 * Used when ProcessWire is unavailable (CLI testing, headless mode).
 */
class Session extends Crystal
{
    public function __construct($pwObject = null, $panename = null, $inlayname = null, $mos = null)
    {
        $this->data = [];
    }

    public function getVar($key = null)
    {
        return $varname ?? $key ?? null;
    }
}
