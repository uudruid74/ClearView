<?php

namespace ClearView;
use ClearView\Inlay;
use ProcessWire;

/**
 * Loginform login inlay — handles login/logout.
 *
 * @see ClearView\Inlay
 */
class loginform_login extends Inlay
{
    public function logout()
    {
        ClearView::Session()->logout();
        $this->triggerevent('userchange');
    }

    public function login()
    {
        // Requires inputs named username and password
        if (ClearView::Session()->trylogin()) {
            $this->setVars([        // Login successful
                'formtitle'     => 'Login Succeeded!',
                'forminfo'      => 'Welcome back<br>{{text20\User::displayname}}!',
                'login'         => 'Success!'
            ]);
            $this->triggerevent('userchange')
                 ->close();         // close the form
        } else {
            $this->setVars([        // Login failed
                'formtitle'     => "Login Failed!",
                'forminfo'      => "Try again, or reset your password using the link below",
                'login'         => 'Try Again!'
            ]);
        }
    }
}
