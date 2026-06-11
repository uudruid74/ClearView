<?php

namespace ClearView\Element;
use ClearView\Element;
use ClearView\Config;

class fragment extends Element
{
    protected $primaryField = 'children';
    protected string $contentsType = self::ShardArray;

    public function render()
    {
        return;
    }
} // end of class
