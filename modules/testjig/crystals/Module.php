<?php

namespace ClearView;

/** Null Module crystal — returns null for all modules. */
class Module extends Crystal
{
    public $address = "Crystal-Module";
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
