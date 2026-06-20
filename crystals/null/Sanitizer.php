<?php

namespace ClearView;

/**
 * Null Sanitizer crystal — returns null for all values.
 * Used when ProcessWire is unavailable (CLI testing, headless mode).
 */
class Sanitizer extends \ClearView\Sanitizer
{
    public function __construct($pwObject = null, $panename = null, $inlayname = null, $mos = null)
    {
        $this->data = [];
    }

    public function getVar($key = null)
    {
        return null;
    }
}
