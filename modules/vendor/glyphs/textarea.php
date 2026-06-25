<?php
namespace ClearView\Element;
use ClearView\Element;
use ClearView\Facet;

class textarea extends Element
{
    protected $primaryField = Config::SHARD_ARRAYNAME;

    public function render()
    {
        (new Facet($this))
        ->open('<label class="dynamic">{{label}}:', match: [$this->hasField('label')])
        ->open('<textarea
            {{hx}}
            {{type=type}}
            {{rows=rows}} {{cols=cols}}
            {{name=name}}
            {{title=title}}
            {{disabled}}>'
        );
    }
}
