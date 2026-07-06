<?php

namespace ClearView;

use ClearView\Crystal;
use ClearView\Pane;
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
    public function __construct($pwObject = null,$panename=null,$inlayname=null,$mos)
    {
        parent::__construct($pwObject ?? \ProcessWire\input(), $panename, $inlayname,$mos);
    }

    /** Returns the default method name for a given request method. */
    public static function defaultMethod(?string $method = null): string
    {
        $map = ['POST' => 'post', 'CLI' => 'open', 'GET' => 'html', 'PUT' => 'put', 'DELETE' => 'delete'];
        return $map[$method] ?? 'html';
    }

    /**
     * Gets a variable or field from the input object.
     *
     * Supports special cases for 'requestMethod', 'url', 'all', 'panename', 'inlayname', 'methodname', 'nextinlay', and 'inlaypath'.
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
        if (Pane::inTesting()) {
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
                return $this->parseUrlSegments($pw)[1] ?? 'Default';
            case 'inlayname':
                return $this->parseUrlSegments($pw)[2] ?? 'Pane';
            case 'methodname':
                return $this->parseUrlSegments($pw)[3] ?? self::defaultMethod($pw->requestMethod());
            case 'inlaypath':
                return $this->parseInlayPath($pw);
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

    /**
     * Parses the URL path with the panename segment removed.
     *
     * Examples:
     *   /node/my/path/page  →  '/my/path/page'  (panename='node')
     *   /Default/login      →  '/login'          (panename='Default')
     *
     * ALSO sets the current Page crystal to the ProcessWire page
     * at the inlaypath so that subsequent {{Page::name}} references
     * resolve to the named page, not the full-URL page.
     *
     * @param mixed $pw The ProcessWire input object.
     * @return string The URL path without the leading panename segment.
     */
    private function parseInlayPath($pw): string
    {
        $parsed = parse_url($pw->url());
        $path = isset($parsed['path']) ? $parsed['path'] : '';
        $segments = explode('/', trim($path, '/'));
        $inlaypath = isset($segments[1]) ? '/' . implode('/', array_slice($segments, 1)) : '/';

        // Route the Page crystal to the inlaypath page so that
        // Page::name / Page::body resolve to the named page.
        $pwPage = \ProcessWire\pages()->get($inlaypath);
        if ($pwPage && $pwPage->id) {
            $currentPage = Mosaic::index('ClearView', 'Page');
            $pageClass = $currentPage ? get_class($currentPage) : Page::class;
            $newPage = new $pageClass($pwPage, 'Page', 'ClearView');
            $newPage->address = 'ClearView-Page';
            Mosaic::addShard($newPage);
        }

        return $inlaypath;
    }
}
