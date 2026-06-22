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

}
