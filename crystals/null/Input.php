<?php

namespace ClearView;

/**
 * Null Input crystal — returns null for all values.
 * Used when ProcessWire is unavailable (CLI testing, headless mode).
 */
class Input extends \ClearView\Input
{
    public function __construct($pwObject = null, $panename = null, $inlayname = null, $mos = null)
    {
        // Skip ProcessWire — no parent constructor call.
        $this->data = [];
    }

    public function getVar($key = null)
    {
        return null;
    }
}
