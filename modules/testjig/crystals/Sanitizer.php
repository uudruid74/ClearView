<?php

namespace ClearView;

/**
 * Null Sanitizer crystal — returns null for all values.
 * Used when ProcessWire is unavailable (CLI testing, headless mode).
 */
class Sanitizer extends Crystal
{
    public $address = "Crystal-Sanitizer";
    public function __construct($pwObject = null, $panename = null, $inlayname = null, $mos = null)
    {
        $this->data = [];
        parent::__construct($pwObject, $panename, $inlayname, $mos);
    }

    public function getVar($key = null)
    {
        return $varname ?? $key ?? null;
    }
}
