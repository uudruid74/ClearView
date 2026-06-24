<?php

namespace ClearView;

use ClearView\Crystal;
use ProcessWire;

/**
 * Crystal for managing user data in ProcessWire.
 *
 * Wraps ProcessWire's user object to provide access to user properties
 * and fields. Supports change tracking and automatic saving.
 *
 * @see \ClearView\Crystal
 */
class User extends Crystal
{
    /**
     * Initializes the User Crystal with a ProcessWire user object.
     *
     * @param mixed $pwObject The ProcessWire user object (defaults to WireUser via user()).
     */
    public function __construct($pwObject=null,$panename=null,$inlayname=null,$mos)
    {
        parent::__construct($pwObject ?? \ProcessWire\user(),$panename,$inlayname,$mos);
    }

    /**
     * Attempts to login the user. Triggers loginchange on success.
     * @return User|null The User on success, null on failure.
     */
    public function trylogin()
    {
        $user = ClearView::Session()->login($this['name30\username'], $this['removeWhitespace30\password']);
        if ($user) {
            Framework::triggerevent('loginchange');
            return new \ClearView\User($user);
        }
        return null;
    }

    /**
     * Logs out the current user. Triggers loginchange.
     */
    public function logout()
    {
        ClearView::Session()->logout();
        Framework::triggerevent('loginchange');
    }

    /**
     * Creates and saves a new user. Auto-saves — no manual save() needed.
     * @param array $fields ['username', 'password', 'email', 'role', 'displayname', ...]
     * @return User The new user crystal.
     */
    public function add(array $fields): self
    {
        $username = $fields['username'] ?? '';
        $user = \ProcessWire\users()->add($username);
        unset($fields['username']);

        if (isset($fields['password'])) {
            $user->pass = $fields['password'];
            unset($fields['password']);
        }
        if (isset($fields['role'])) {
            $user->addRole($fields['role']);
            unset($fields['role']);
        }

        foreach ($fields as $key => $value) {
            $user->set($key, $value);
        }
        $user->save();
        return new \ClearView\User($user);
    }

    /**
     * Updates an existing user's fields. Auto-saves.
     * @param array $fields ['email', 'displayname', ...]
     * @return self
     */
    public function update(array $fields): self
    {
        $pwUser = $this[Config::PAGE_PWOBJECT];
        foreach ($fields as $key => $value) {
            $pwUser->set($key, $value);
        }
        $pwUser->save();
        return $this;
    }

    /**
     * Deletes the current user.
     */
    public function delete(): void
    {
        $pwUser = $this[Config::PAGE_PWOBJECT];
        \ProcessWire\users()->delete($pwUser);
        Framework::triggerevent('loginchange');
    }
}
