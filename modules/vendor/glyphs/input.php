<?php

namespace ClearView\Element;

use ClearView\Element;
use ClearView\Facet;

class input extends Element
{
    public static $change = "keyup changed delay:{{delay}}ms";

    public function init()
    {
        $this->initFields([
            'type' => 'text',
            'delay' => 500,
            'name' => $this->id()
        ]);
    }

    public function render()
    {
        switch ($this->getField('type')) {
            case "username":
                $this->initField('autocomplete', 'username');
                break;
            case "password":
                $this->initFields([
                    'autocomplete'  => 'password',
                ]);
                $password = true;
                break;
            case "email":
                $this->initFields([
                    'autocomplete' => 'email',
                ]);
                break;
        }

        (new Facet($this))
        ->open('<label class="dynamic">{{label}}:',match: [
                [ $this->hasField('label') ], [!$this->isOob()]
        ])
        ->out('<input
            {{id=id}} {{hx}}
            {{type=type}}
            {{value=value}}
            {{autocomplete=autocomplete}}
            {{name=name}}
            {{title=title}}
            {{disabled}}
        >')
        ->out('</label><center style="right:3%;position:relative;"><label style="margin-top: 0;">Show Password:
            <input
                name="show-password"
                type="checkbox"
                role="switch"
            >
            <script>
                me().on("change", ev => { me("#{{id}}").attribute("type", me(ev.target).checked ? "text" : "password"); });
            </script>
            </center>
            ', match: [[isset($password)], [!$this->isOob()]]);
    }
}
