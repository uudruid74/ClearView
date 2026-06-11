<?php

namespace ClearView\Form;
use ClearView\Form;
use ProcessWire;

class login extends Form
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
    // end class
}
