<?php

namespace ClearView;
use ClearView\Inlay;

/**
 * Loginform newaccount inlay — handles new account creation.
 *
 * @see ClearView\Inlay
 */
class loginform_newaccount extends Inlay
{
    /**
     * Validates an email address using the EmailVerification PW module.
     *
     * @param string $email Email address to validate.
     * @return bool True if the email host is valid.
     */
    public function isValidEmail(string $email): bool
    {
        // TODO: Module crystal should autoload PW modules via Module:: prefix
        // return Mosaic::getVar('Module::EmailVerification')->validHost($email);
        $mailcheck = \ProcessWire\modules()->get("EmailVerification");
        return $mailcheck->validHost($email);
    }

    /**
     * Extracts and validates the email address from the nextinlay queue.
     *
     * @return string|null Full email address, or null if invalid.
     */
    public function getEmailAddr(): ?string
    {
        $extdata = $this['Input::nextinlay'];
        if (!isset($extdata)) {
            return null;
        }

        $email = getEmailAddress($extdata);
        if (isset($email) && strlen($email) >= 10 && $this->isValidEmail($email)) {
            return $email;
        }
        if (isset($email) && strlen($email) < 10) {
            $this['summary'] = "Sorry, {$email} doesn't look right";
        }
        $this['headline'] = "Invalid Email";
        return null;
    }

    /**
     * Submits the form — shows terms confirmation AlertBox before creating account.
     */
    public function submit()
    {
        $choice = ClearView::AlertBox([
            'title'   => 'Terms & Conditions',
            'page'    => 'termsconditions',
            'buttons' => ['confirm' => 'I Agree', 'cancel' => 'Cancel'],
        ]);
        if ($choice === null) return;

        switch ($choice) {
            case 'confirm':
                return $this->createAccount();
            case 'cancel':
                return;
        }
    }

    /**
     * Creates the user account after all validations pass.
     */
    public function createAccount()
    {
        $email = $this->getEmailAddr();
        if (!isset($email)) {
            $this->fill(['formtitle' => 'Error', 'forminfo' => 'Invalid email']);
            return;
        }

        $existingMail = $this["User::email=$email"];
        if ($existingMail->id !== 0) {
            $this->fill(['formtitle' => 'Error', 'forminfo' => 'Email Exists']);
            return;
        }

        $username = $this['username'] ?? '';
        $existingUser = $this["User::name=$username"];
        if ($existingUser->id !== 0) {
            $this->fill(['formtitle' => 'Error', 'forminfo' => 'Username Exists']);
            return;
        }

        $password = $this['removeWhitespace30\password'] ?? '';
        $displayname = $this['text30\displayname'] ?? ucfirst($username);

        if (strlen($username) < ($this['min_user_len'] ?? 3)) {
            $this->fill(['formtitle' => 'Error', 'forminfo' => 'Use a longer username']);
            return;
        }
        if (strlen($password) < 7) {
            $this->fill(['formtitle' => 'Error', 'forminfo' => 'Password is too small']);
            return;
        }
        if (strlen($displayname) < 4) {
            $displayname = ucfirst($username);
        }

        ClearView::User()->add([
            'username'    => $username,
            'password'    => $password,
            'email'       => $email,
            'role'        => 'apprentice',
            'displayname' => $displayname,
        ]);

        $this['title'] = 'Success!';
        $this->close();
    }

    /**
     * Pre-fills the form with data from the email link.
     */
    public function init()
    {
        $this->debug('called init');
        $email = $this->getEmailAddr();
        if (isset($email)) {
            [$username] = explode('@', $email);
            $this->fill([
                'email'          => $email,
                'email.disabled' => true,
                'username'       => $username,
                'displayname'    => ucfirst($username),
            ])
            ->debug("Email address is {$email}");
        } else {
            $this->fill([
                'formtitle' => 'Error',
                'forminfo'  => 'Invalid Link!',
            ])
            ->close();
            return 'Error';
        }
    }
}
