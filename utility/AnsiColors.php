<?php

namespace ClearView;

class AnsiColors
{
    private static $colors = [
        'black' => '\x1b[30m',  // black
        'INLAY' => '\x1b[30m',  // black
        'ERROR' => '\x1b[31m',  // red
        'GLYPH' => '\x1b[32m',  // green
        'INFO'  => '\x1b[33m',  // yellow
        'EVENT' => '\x1b[34m',  // blue
        'FACET' => '\x1b[34m',  // blue
        'VAR'   => '\x1b[35m',  // magenta
        'TRACE' => '\x1b[36m',  // cyan
        'JSON'  => '\x1b[37m',  // white
        'bold'  => '\x1b[1m',   // bold
        'off'   => '\x1b[0m',   // stop
    ];

    /**
     * Returns the ANSI color code for a given tag.
     *
     * @param string $tag The tag to get the color for.
     * @return string The ANSI color code.
     */
    public static function color($tag)
    {
        return self::$colors[$tag] ?? self::$colors['black'];
    }
}
