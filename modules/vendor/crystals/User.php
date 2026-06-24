<?php

namespace ClearView;

use ClearView\Crystal;
use ProcessWire;

/**
 * Crystal for managing user data in ProcessWire.
 *
 * Wraps ProcessWire’s user object to provide access to user properties and fields. Supports change tracking
 * and automatic saving when modified.
 *
 * @see \ClearView\Crystal
 */
class User extends Crystal
{
    /**
     * Initializes the User Crystal with a ProcessWire user object.
     *
     * Called during system initialization or when accessing user data. Uses ProcessWire’s `user()` function
     * if no object is provided.
     *
     * Why: Sets up access to user data within ClearView’s data model.
     *
     * @param mixed $pwObject The ProcessWire user object (defaults to WireUser via `user()`).
     */
    public function __construct($pwObject=null,$panename=null,$inlayname=null,$mos)
    {
        parent::__construct($pwObject ?? \ProcessWire\user(),$panename,$inlayname,$mos);
    }
    /**
     * Attempts to login the user.  The username and password must be attributes of the current pane
     * @return User|null The User on success, null on failure.
     */
    public function trylogin()
    {
        $user = ClearView::Session()->login($this['name30\username'], $this['removeWhitespace30\password']);
        return $user ? new \ClearView\User($user) : null;
    }

    /**
     * Companion to the above, log them out!
     */
    public function logout()
    {
	ClearView::Session()->logout();
    }

}
