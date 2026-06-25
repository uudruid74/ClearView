<?php

namespace ClearView;
use ClearView\Inlay;

/**
 * Login newuser inlay — handles new user email flow.
 *
 * @see ClearView\Inlay
 */
class login_newuser extends Inlay
{
    /**
     * Validates an email address using the EmailVerification PW module.
     *
     * @param string $email Email address to validate.
     * @return bool True if the email host is valid.
     */
    public function isValidEmail(string $email): bool
    {
        $mailcheck = \ProcessWire\modules()->get('EmailVerification');
        return $mailcheck->validHost($email);
    }

    /**
     * Processes the email submission — sends a registration or reset link.
     */
    public function submit()
    {
        $email = $this->getVar('email\email');
        if (!$this->isValidEmail($email)) {
            $this->fill([
                'headline' => 'Invalid Email',
                'summary'  => "Sorry, {{email}} doesn't look right!",
            ]);
            return;
        }

        // Find existing user
        $user = $this->getVar("User::email=$email");
        $post = '';
        $info = '';

        if (!isset($user) || $user->id === 0) {
            // New Account
            $post = '/emailtemplate/new-account/';
            $info = 'Check your email to register your account';
        } else {
            // Existing User
            $post = '/emailtemplate/reset-password/';
            $info = "Hi {{User::displayname}}! We sent a link and 6 digit code to your email!";
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $user->update(['code' => $code]);
        }

        [$em1, $em2] = explode('@', $email);
        $emailurl = urlencode($em1) . '/' . urlencode($em2);

        $this->fill([
            'headline' => 'Sending Mail!',
            'summary'  => $info,
        ]);

        // Sends email via HTTP POST
        (new \ProcessWire\WireHttp())->post("{$post}{$emailurl}/", ['email' => $email]);
    }
}
