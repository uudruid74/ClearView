<?php

namespace ClearView;

/**
 * Null View crystal — returns null for all vars.
 */
class View extends Crystal
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
