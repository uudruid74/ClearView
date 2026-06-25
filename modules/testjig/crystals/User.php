<?php

namespace ClearView;

/**
 * Null User crystal — test login for "admin"/"1234".
 * Used when ProcessWire is unavailable (CLI testing, headless mode).
 */
class User extends Crystal
{
    public int $id = 0;

    public function __construct($pwObject = null, $panename = null, $inlayname = null, $mos = null)
    {
        $this->data = [];
        parent::__construct($pwObject, $panename, $inlayname, $mos);
        parent::__construct($pwObject, $panename, $inlayname, $mos);
    }

    public function getVar($key = null)
    {
        return $key ?? null;
    }

    /**
     * Test login — accepts admin/1234, rejects everything else.
     */
    public function trylogin()
    {
	Exception::debug('VAR',Facet::_("trylogin: user='{{username}}' pass='{{password}}'"));
        if (Mosaic::getVar('username') === 'admin' && Mosaic::getVar('password') === '1234') {
            $this->id = 1;
            Framework::triggerevent('loginchange');
            return $this;
	}
	Exception::debug('VAR',Facet::_('[{{Input::inlayname}}] Password Failed for username: ' . 
		Mosaic::getVar('username') . " and password: " . Mosaic::getVar('password')));
        return null;
    }

    public function logout()
    {
        $this->id = 0;
        Framework::triggerevent('loginchange');
    }
}
