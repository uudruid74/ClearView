<?php

namespace ClearView;

/**
 * Null Input crystal — reads from TestRig jig values for headless testing.
 */
class Input extends Crystal
{
    public function __construct($pwObject = null, $panename = null, $inlayname = null, $mos = null)
    {
        $this->data = [];
    }

    public function getVar($key = null)
    {
        $jig = TestRig::getJig($key);
        if ($jig !== null) return $jig;
        return $key ?? null;
    }
}
