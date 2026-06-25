<?php

namespace ClearView;

/** Null Config crystal — returns null for all vars. */


class Config extends Crystal
{
    public $address = "Crystal-Config";

    public function __construct($pwObject = null, $panename = null, $inlayname = null, $mos = null)
    {
        $this->mosaic = $mos;
        $this->data = [];
        parent::__construct($pwObject, $panename, $inlayname, $mos);
    }

    public function getVar($key = null)
    {
        return $varname ?? $key ?? null;
    }
}
