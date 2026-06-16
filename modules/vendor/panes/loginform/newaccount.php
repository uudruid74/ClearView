<?php

namespace ClearView;
use ClearView\Inlay;
use ProcessWire;

require_once("hannas/gcmlib.php");

/**
 * Loginform newaccount inlay — handles new account creation.
 *
 * @see ClearView\Inlay
 */
class loginform_newaccount extends Inlay
{
    // Email sanity check.  TODO: Implement blacklist checking
    public function isValidEmail($email)
    {
        $mailcheck = ProcessWire\modules()->get("EmailVerification");
        return ($mailcheck->validHost($email));
    }

    // Return full email address
    public function getEmailAddr()
    {
        $extdata = $this->getVar('Input::nextInlay');
        if (isset($extdata)) {
            $email = getEmailAddress($extdata);

            if (isset($email) && (strlen($email) >= 10) && $this->isValidEmail($email)) {
                return $email;
            }
            if (isset($email) && strlen($email) < 10) {
                $this->setVar('forminfo', "Sorry, {$email} doesn't look right");
            }
        }
        $this->setVar('formtitle', "Invalid Email");
        return null;
    }

    public function submit()
    {
        //  Open the AlertBox with Terms & Conditions page
        //  switch (AlertBox([ page => 'termsconditions', ...]) || return) {
        //  case 'confirm':
        return $this->createAccount();
    }

    public function createAccount()
    {
        $extdata = $this->getVar('email\Input::nextInlay');
        $email = $this->getEmailAddr($extdata);
        $existingMail = $this->getVar("User::email=$email");
        $username = $this->getVar('username') ?? '';
        if (strlen($username) < ($this->getVar('min_user_len') ?? 3)) {
            $this->setVars([
                'formtitle' => "Error",
                'forminfo'  => "Use a longer username"
            ]);
            return;
        }
	$existingUser = $this->getVar("User::name=$username");
        if ($existingMail->id !== 0) {
            $this->setVars([
                'formtitle' => "Error",
                'forminfo'  => "Email Exists"
            ]);
            return;
        }
        if ($existingUser->id !== 0) {
            $this->setVars([
                'formtitle' => "Error",
                'forminfo'  => "Username Exists"
            ]);
            return;
        }
        $password = $this->getVar('removeWhitespace30\password') ?? '';
        $displayname = $this->getVar('text30\displayname') ?? ucfirst($username);
        if (strlen($password) < 7) {
            $this->setVars([
                'formtitle' => "Error",
                'forminfo'  => "Password is too small"
            ]);
            return;
        }
        if (strlen($displayname) < 4) {
            $displayname = ucfirst($username);
        }
        $user = $this->getVar("ClearView::User")->add($username);
        $user->pass = $password;
        $user->addRole("apprentice");
	$user->setVars([
		"email" => $email,
		"displayname" => $displayname
	]);
        $user->save(); // should be unnecessary now
        $this->setVar('title', "Success!");
        $this->close();
    }

    public function init()
    {
        $this->debug("called init");
        $extdata = $this->getVar('email\Input::nextInlay');
        $email = $this->getEmailAddr($extdata);
        if (isset($email)) {
            list($username, $domain) = explode("@", $email);
            $this->debug("Email address is {$username}@{$domain}");
            $this->setVars([
                'email'          => $mail,
                'email.disabled' => true,
                'username'       => $username,
                'displayname'    => ucfirst($username)
            ]);
        } else {
            $this->setVars([
                'title' => "Error",
                'info'  => "Invalid Link!"
            ]);
            $this->close();
            return "Error";
        }
    }
}
