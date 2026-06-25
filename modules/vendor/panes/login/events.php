<?php

namespace ClearView;
use ClearView\Inlay;

/**
 * Login events inlay — handles pane lifecycle events.
 *
 * Loaded automatically when the pane has listen="..." set.
 * Each event name maps to a method of the same name on this class.
 *
 * For now, only closepane is wired. When the pane closes, this
 * cleans up any login-specific state.
 *
 * @see ClearView\Inlay
 */
class login_events extends Inlay
{
    /**
     * Called when the pane is closed by the user.
     * Cleans up login-specific session state.
     */
    public function closepane(): void
    {
        $this->fill([
            'headline' => '',
            'summary'  => '',
        ]);
    }
}
