<?php

namespace ClearView;

/**
 * Shared global state container for the ClearView runtime.
 *
 * Holds values that must persist across a single request and be
 * accessible from any Element, Pane, or Glyph without coupling
 * them through Mosaic or the ProcessWire object graph.
 *
 * Currently tracks the active main layout so the `<attr>` glyph
 * can detect and prevent redundant view-based layout changes.
 */
class Shared
{
    /** @var string|null The current main layout name (e.g. 'dashboard') */
    public static ?string $mainLayout = null;
}
