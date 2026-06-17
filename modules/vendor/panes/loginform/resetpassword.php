<?php

namespace ClearView;
use ClearView\Inlay;
use ProcessWire;

require_once("hannas/gcmlib.php");

/**
 * Loginform resetpassword inlay — handles password reset flow.
 *
 * @see ClearView\Inlay
 */
class loginform_resetpassword extends Inlay
{
    // Email sanity check.  TODO: Implement blacklist checking
    public function isValidEmail($email)
    {
        $mailcheck = ProcessWire\modules()->get("EmailVerification");
        return ($mailcheck->validHost($email));
    }

    // destroying this dialog destroys the email hash in the URL
    // The emailhash ("/e/") page will show a regular login/logout
    // This will change a lot!
    // FIXME:: No longer knows when cancelled!
    public function close($delay = 0)
    {
        parent::close($delay);
        $this->redirect("/e/");
    }

    // Return a user id for a given email hash
    public function getUser()
    {
        $extdata = $this->getVar('Pane::nextinlay');
        if (isset($extdata)) {
            $email = getEmailAddress($extdata);

            if (isset($email) && $this->isValidEmail($email) === false) {
                $this->fill([
                    'headline' => 'Invalid Email',
                    'summary'  => "Sorry, {$email} doesn't look right!"
                ]);
                return null;
            }
        } else {
            $this->debug("No next inlay in URL!");
            return null;
        }
        // Find existing user by email
        //$user = ProcessWire/users()->get("email=$email");
        $user = $this->getVar("User::email=$email");
        if ($user->id === 0) {
            return null;
        } else {
            return $user;
        }
    }

    // In the future, add a login tab and close the previous one
    public function submit()
    {
        $user = $this->getUser();
        $code = $this->getVar("digits\code");
        if ($user) {
            if ($code === $user->get('code')) {
                $user->setVar("code", "");
                $user->pass = $this->getVar('stripWhitespace30\password');
                if ($user->save()) {
                    $this->setVar('info', "Success!");
                    $this->close();
                    ;
                } else {
                    $this->setVar('info', "Can't update user profile!");
                }
            } else {
                $this->setVar('info', "Sorry, the code does not match.");
            }
        } else {
            $this->setVar('info', "That link isn't right. Please contact support.");
        }
    }

    public function init()
    {
        $this->debug("initializing reset password form");
        $user = $this->getUser();
        if ($user) {
            $this->initVars([
                'email'    => $user->email,
                'username' => $user->name
            ]);
        }
    }
}
