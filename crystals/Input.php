<?php

namespace ClearView;

use ClearView\Crystal;
use ProcessWire;

/**
 * Crystal for managing input data in ProcessWire.
 *
 * Wraps ProcessWire’s input object to provide access to request data (e.g., GET, POST, URL segments).
 *
 * @see \ClearView\Crystal
 */
class Input extends Crystal
{
    /**
     * Initializes the Input Crystal with a ProcessWire input object.
     * Uses ProcessWire’s `input()` function if no object is provided.
     *
     * @param mixed $pwObject The ProcessWire input object (defaults to WireInput via `input()`).
     */
    public function __construct($pwObject = null,$panename=null,$inlayname=null)
    {
        parent::__construct($pwObject ?? \ProcessWire\input(), $panename, $inlayname);
    }

    /**
     * Gets a variable or field from the input object.
     *
     * Supports special cases for 'requestMethod', 'url', 'all', 'panename', 'inlayname', 'methodname', and 'nextinlay'.
     * For other keys, uses the parent getVar, supporting '.' notation for nested fields.
     *
     * @param string|null $key The key to retrieve, or null for the entire object.
     * @return mixed The value of the field, the ProcessWire object, or null if not found.
     */
    public function getVar($key = null)
    {
        static $nextinlay = 4;

        Exception::debug("Input->getVar('$key') called");
        $pw = $this->data[Config::PAGE_PWOBJECT];
        if (ClearView::inTesting()) {
            return $GLOBALS['cliUrlSegments'][$key] ?? null;
        }
        if ($key === null || $key === '') {
            return $pw;
        }
        switch ($key) {
            case 'requestMethod':
                return $pw->requestMethod();
            case 'url':
                return $pw->url();
            case 'panename':
                return $this->parseUrlSegments($pw)[1] ?? null;
            case 'inlayname':
                return $this->parseUrlSegments($pw)[2] ?? null;
            case 'methodname':
                return $this->parseUrlSegments($pw)[3] ?? null;
            case 'nextinlay':
                return $this->parseUrlSegments($pw)[$nextinlay++] ?? null;
            case 'all':
            default:
                return $pw->{strtolower($pw->requestMethod())}->{$key} ?? null;
        }
    }

    private function parseUrlSegments($pw): array
    {
        $parsed = parse_url($pw->url());
        $domain = isset($parsed['host']) ? $parsed['host'] : '';
        $path = isset($parsed['path']) ? trim($parsed['path'], '/') : '';
        $segments = $path ? array_filter(explode('/', $path)) : [];
        array_unshift($segments, $domain);
        return array_values($segments);
    }
}
