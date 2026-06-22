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
	// TO-DO Turn this into a Crystal:
	// $mailcheck = $this['Module::EmailVerification'];
        $mailcheck = ProcessWire\modules()->get("EmailVerification");
        return ($mailcheck->validHost($email));
    }

    // Return full email address
    public function getEmailAddr()
    {
        $extdata = $this['Input::nextinlay'];
        if (isset($extdata)) {
            $email = getEmailAddress($extdata);

            if (isset($email) && (strlen($email) >= 10) && $this->isValidEmail($email)) {
                return $email;
            }
            if (isset($email) && strlen($email) < 10) {
                $this['summary'] = "Sorry, {$email} doesn't look right";
            }
        }
        $this['headline'] = "Invalid Email";
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
        $extdata = $this['email\Input::nextinlay'];
        $email = $this->getEmailAddr($extdata);
        $existingMail = $this["User::email=$email"];
        $username = $this['username'] ?? '';
        if (strlen($username) < ($this['min_user_len'] ?? 3)) {
            $this->fill([
                'formtitle' => "Error",
                'forminfo'  => "Use a longer username"
            ]);
            return;
        }
	$existingUser = $this["User::name=$username"];
        if ($existingMail->id !== 0) {
            $this->fill([
                'formtitle' => "Error",
                'forminfo'  => "Email Exists"
            ]);
            return;
        }
        if ($existingUser->id !== 0) {
            $this->fill([
                'formtitle' => "Error",
                'forminfo'  => "Username Exists"
            ]);
            return;
        }
        $password = $this['removeWhitespace30\password'] ?? '';
        $displayname = $this['text30\displayname'] ?? ucfirst($username);
        if (strlen($password) < 7) {
            $this->fill([
                'formtitle' => "Error",
                'forminfo'  => "Password is too small"
            ]);
            return;
        }
        if (strlen($displayname) < 4) {
            $displayname = ucfirst($username);
        }
        $user = $this["ClearView::User"]->add($username);
        $user->pass = $password;
        $user->addRole("apprentice");
	$user->fill([
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
        $extdata = $this['email\Input::nextinlay'];
        $email = $this->getEmailAddr($extdata);
        if (isset($email)) {
            list($username, $domain) = explode("@", $email);
            $this->debug("Email address is {$username}@{$domain}");
            $this->fill([
                'email'          => $mail,
                'email.disabled' => true,
                'username'       => $username,
                'displayname'    => ucfirst($username)
            ]);
        } else {
            $this->fill([
                'formtitle' => "Error",
                'forminfo'  => "Invalid Link!"
            ]);
            $this->close();
            return "Error";
        }
    }
}
