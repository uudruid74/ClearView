<?php

namespace ClearView\Test;

use ClearView\Mosaic;

/**
 * Helper that sets up matching Pane::Key / Session::PaneKey tokens
 * so that Pane::handleCommand() CSRF checks pass in tests.
 *
 * In production, Pane::Key arrives via URL parameters (stored in
 * Mosaic under the "Pane" inlay), and Session::PaneKey is generated
 * by Session::getPaneKey() from ProcessWire's CSRF token system.
 *
 * This helper sets both to the same value so command-dispatch tests
 * don't fail with "Invalid PaneKey" exceptions.
 */
class PaneKeyHelper
{
    /**
     * Generate and inject matching Pane::Key / Session::PaneKey tokens
     * for the given panename.
     *
     * After calling this, Mosaic::getVar('Pane::Key') and
     * Mosaic::getVar('Session::PaneKey') will both return $token.
     *
     * @param string      $panename  Pane name (default: 'TestPage').
     * @param string|null $token     Explicit token; random if null.
     * @return string                The token that was set.
     */
    public static function seed(string $panename = 'TestPage', ?string $token = null): string
    {
        $token = $token ?? 'tok_' . \bin2hex(\random_bytes(12));

        // --- Pane::Key — stored as a Mosaic shard under the "Pane" inlay.
        //     The Pane Crystal's getVar() resolves "Key" by searching
        //     Mosaic::index('Pane', 'Key').  So we store the literal key
        //     "Pane::Key" under the inlay that handleCommand's
        //     $this->getVar() will query.
        //
        //     In production loadMosaic() stores parameters with the inlay
        //     being the last-seen inlay.  For tests, we store it directly
        //     under the Pane Crystal's inlay (which is 'ClearView').
        //     getVar('Pane::Key') reads from the Pane Crystal which falls
        //     back to Mosaic::index('Pane', 'Key').  So we store it there.

        Mosaic::setVar('Key', $token, 'Pane');
        Mosaic::setVar('name', $panename, 'Pane');

        // --- Session::PaneKey — set the CSRF token so Session::getPaneKey()
        //     returns the same value.
        $session = \ProcessWire\session();
        if (!$session->CSRF->hasToken($panename)) {
            $session->CSRF->createToken($panename);
        }
        // Override the auto-generated token with our known value.
        $session->CSRF->tokens[$panename] = $token;

        // Also set as a direct Mosaic variable for tests that bypass crystals.
        Mosaic::setVar('PaneKey', $token, 'Session');

        return $token;
    }

    /**
     * Convenience: seed a key and return it as a Mosaic-form key
     * suitable for passing as an input parameter.
     */
    public static function seedAndKey(string $panename = 'TestPage'): string
    {
        return self::seed($panename);
    }
}
