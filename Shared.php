<?php

namespace ClearView;

/**
 * Shared global state container for the ClearView runtime.
 * Holds values that must persist across a single request and be
 * accessible from any Element, Pane, or Glyph without coupling
 * them through Mosaic or the ProcessWire object graph.
 * Currently tracks the active main layout so the `<attr>` glyph
 * can detect and prevent redundant view-based layout changes,
 * and the previous inlay for inlaychange detection.
 */
class Shared
{
    /** @var string|null The current main layout name (e.g. 'dashboard') */
    public static ?string $mainLayout = null;

    /** @var string|null The previously active inlay name; used to detect inlay changes */
    public static ?string $prevInlay = null;

    /** @var array Debug flags controlling debug console and trace behavior */
    public static array $debugflags = [];

    /** Check if debug console is enabled for the current pane. */


    public static function isDebugConsole(): bool
    {
        return in_array('DEBUG_CONSOLE', self::$debugflags, true)
            || in_array('ALL', self::$debugflags, true);
    }
}
