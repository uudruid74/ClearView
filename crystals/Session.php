<?php

namespace ClearView;

use ClearView\Crystal;
use ClearView\Mosaic;
use ClearView\User;
use ProcessWire;

/**
 * Crystal for managing session data in ProcessWire.
 *
 * Wraps ProcessWire’s session object to provide access to session variables under a ClearView-specific namespace.
 *
 * @see \ClearView\Crystal
 */
class Session extends Crystal
{
    /**
     * Initializes the Session Crystal with a ProcessWire session object.
     *
     * Uses ProcessWire’s `session()` function if no object is provided.
     *
     * Why: Sets up access to session data within ClearView’s data model.
     *
     * @param mixed $pwObject The ProcessWire session object (defaults to WireSession via `session()`).
     */
    public function __construct($pwObject=null,$panename=null,$inlayname=null)
    {
        parent::__construct($pwObject ?? \ProcessWire\session(),$panename,$inlayname);
    }

    /**
     * Attempts to login the user.  The username and password must be attributes of the current pane
     * @return User|null The User on success, null on failure.
     */
    public function trylogin()
    {
        $user = $this[Config::PAGE_PWOBJECT]->login(
            Mosaic::getVar('name30\username'),
            Mosaic::getVar('removeWhitespace30\password')
        );
        return $user ? new \ClearView\User($user) : null;
    }

    /**
     * Companion to the above, log them out!
     */
    public function logout()
    {
        $this[Config::PAGE_PWOBJECT]->logout();
    }

    /**
     * Gets a CSRF token for a given panename.
     *
     * Creates the token if it doesn't exist.
     *
     * @param string $panename The name of the pane.
     * @return string The CSRF token value.
     */
    public function getPaneKey($panename)
    {
        $session = \ProcessWire\session();
        if (!$session->CSRF->hasToken($panename)) {
            $session->CSRF->createToken($panename);
        }
        return $session->CSRF->getTokenValue($panename);
    }

    /**
     * Gets a session variable or the Page Crystal.
     *
     * @param string|null $key The key to retrieve, 'PaneToken' for the current pane's CSRF token,
     * 		'Page' for the Page Crystal, or null for the session object.
     * @return mixed The session value, Page Crystal, session object, or null if not found.
     */
    public function getVar($key = null)
    {
        if ($key === 'PaneKey') {
            return $this->getPaneKey(Mosaic::getVar("Pane::name"));
        }
        if ($key === null || $key === '') {
            return $this[Config::PAGE_PWOBJECT];
        }
        // Fall back to non-namespaced session data
        return \ProcessWire\session()->get($key);
    }
}
