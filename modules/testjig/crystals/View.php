<?php

namespace ClearView;

/** Null View crystal — returns null for all vars. */


class View extends Crystal
{
    public $address = "Crystal-View";

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
