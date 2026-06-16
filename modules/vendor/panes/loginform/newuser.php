<?php

namespace ClearView;
use ClearView\Inlay;
use ProcessWire;

/**
 * Loginform newuser inlay — handles new user email flow.
 *
 * @see ClearView\Inlay
 */
class loginform_newuser extends Inlay
{
    public function isValidEmail($email)
    {
        $mailcheck = ProcessWire\modules()->get('EmailVerification');
        return ($mailcheck->validHost($email));
    }

    public function submit()
    {
        $email = $this->getVar('email\email');
        if ($this->isValidEmail($email) === false) {
            $this->setVars([
                'headline' => "Invalid Email",
                'summary' => "Sorry, {{email}} doesn't look right!"
            ]);
            return;
        }

        // Find existing user
        $user = $this->getVar("User::email=$email");
        $post = "";
        $info = "";

        if (!isset($user) || $user->id === 0) {
            // New Account
            $post = "/emailtemplate/new-account/";
            $info = "Check your email to register your account";
        } else {
            // Existing User
            $post = "/emailtemplate/reset-password/";
            $info = "Hi {{User:displayname}}! We sent a link and 6 digit code to your email!";
            $user->setVar("code", str_pad(random_int(0, 999999), 6, 0, STR_PAD_LEFT));
            $user->save(); // Should be unnecessary now.
        }
        [$em1,$em2] = explode("@", $email);
        $emailurl = urlencode($em1) . "/" . urlencode($em2);  // Displayed in this format so you can see that @=>/
        $this->setVars([
            'headline' => "Sending Mail!",
            'summary' => $info
        ]);
        ClearView::sendPost("{$post}{$emailurl}/", [ "email" => "$email" ]);      // Sends email!
    }
}
