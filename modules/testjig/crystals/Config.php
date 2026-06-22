<?php

namespace ClearView;

/**
 * Null Config crystal — returns null for all vars.
 */
class Config extends Crystal
{
    public function __construct($pwObject = null, $panename = null, $inlayname = null, $mos = null)
    {
        $this->mosaic = $mos;
        $this->data = [];
    }

    public function getVar($key = null)
    {
        return null;
    }
}
