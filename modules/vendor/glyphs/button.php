<?php

namespace ClearView\Element;

use ClearView\Element;
use ClearView\Facet;

class button extends Element
{
    public static $buzz = 50;

    public function render()
    {
        $name = $this->getField('name') ?? $this->getField('type') ?? $this->getField('id') ?? 'unnamedbutton';
        $this->initFields([
                'type'          => 'submit',
                'name'          => $name,
                'hx-indicator'  => 'this'
        ]);
        $this->settarget('this');
        $this->setswap('innerHTML');

        $type = $this->getField('type');
        if ($type === 'loginout') {
            if (ProcessWire\user()->isLoggedIn() ?? false) {
                $type = 'login';
            } else {
                $type = 'logout';
            }
        }
        $this->debug("Button type is $type");
        switch ($type) {
            case "login":
                if ($this->missingField('hx-post')) {
                    $this->seturl('/form/login/login/');
                }
                $this->setField('type','submit');
                break;
            case "logout":
                if ($this->missingField('hx-post')) {
                    $this->seturl('/form/login/logout/');
                }
                $this->setField('type','submit');
                break;
            case "submit":
                $this->seturl();
                break;
            default:
                $this->debug("Button type [{$type}] is unknown");
                $this->seturl();
                $this->settrigger('click');
                $type = 'submit';
        }
        if ($this->getField('center')) {
            new Facet('<center>');
        }
        new Facet('<button {{id=id}} {{title=title}} {{name=name}} {{type=type}} {{hx}}>{{value}}');
    }
}
