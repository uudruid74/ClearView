<?php

namespace ClearView;

/**
 * Null Input crystal — reads from TestJig jig values for headless testing.
 */
class Input extends Crystal
{
    public $address = "Crystal-Input";

    public function __construct($pwObject = null, $panename = null, $inlayname = null, $mos = null)
    {
        $this->data = [];
        parent::__construct($pwObject, $panename, $inlayname, $mos);
    }

    public function getVar($key = null)
    {
        $jig = TestJig::getJig($key);
        if ($jig !== null) return $jig;
        return $key ?? null;
    }
}
