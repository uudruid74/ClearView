<?php

namespace ClearView;
use ClearView\Inlay;

/**
 * Loginform resetpassword inlay — handles password reset flow.
 *
 * @see ClearView\Inlay
 */
class loginform_resetpassword extends Inlay
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
        $mailcheck = \ProcessWire\modules()->get("EmailVerification");
        return $mailcheck->validHost($email);
    }

    /**
     * When the user closes the pane, redirect to the email hash page.
     *
     * TODO: This should move to events.php's onclosepane() once the
     * listen="closepane" attribute is on the pane element. The events
     * inlay will handle this automatically via htmx trigger dispatch.
     *
     * TODO: Implement blacklist checking for email validation.
     * TODO: Handle cancelled state — currently no way to detect if
     * the user cancelled vs. completed the reset.
     */
    public function onclose()
    {
        parent::close();
        $this->redirect("/e/");
    }

    /**
     * Returns a user for a given email hash from the URL.
     *
     * @return \ClearView\User|null The user, or null if not found/invalid.
     */
    public function getUser(): ?\ClearView\User
    {
        $extdata = $this->getVar('Pane::nextinlay');
        if (!isset($extdata)) {
            $this->debug("No next inlay in URL!");
            return null;
        }

        $email = getEmailAddress($extdata);
        if (!isset($email) || !$this->isValidEmail($email)) {
            $this->fill([
                'headline' => 'Invalid Email',
                'summary'  => "Sorry, {$email} doesn't look right!",
            ]);
            return null;
        }

        $user = $this->getVar("User::email=$email");
        if ($user->id === 0) {
            return null;
        }
        return $user;
    }

    /**
     * Submits the password reset — validates the code and sets new password.
     */
    public function submit()
    {
        $user = $this->getUser();
        if (!$user) {
            $this->setVar('info', "That link isn't right. Please contact support.");
            return;
        }

        $code = $this->getVar("digits\code");
        if ($code !== $user->get('code')) {
            $this->setVar('info', "Sorry, the code does not match.");
            return;
        }

        $user->update([
            'code' => '',
            'pass' => $this->getVar('stripWhitespace30\password'),
        ]);

        $this->setVar('info', 'Success!');
        $this->close();
    }

    /**
     * Pre-fills the form with user data from the email link.
     */
    public function init()
    {
        $this->debug("initializing reset password form");
        $user = $this->getUser();
        if ($user) {
            $this->initVars([
                'email'    => $user->email,
                'username' => $user->name,
            ]);
        }
    }
}
