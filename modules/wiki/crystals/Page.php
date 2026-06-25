<?php

namespace ClearView;

use ClearView\Crystal;
use ClearView\Mosaic;

/**
 * WikiPage — Crystal override for wiki page rendering.
 *
 * Extends Crystal (→ Page → Shard) and overrides getField('body')
 * to read markdown from the filesystem instead of ProcessWire.
 *
 * URL → filesystem mapping:
 *   1. Get current URL from Input
 *   2. Strip Config::baseWikiUrl prefix
 *   3. Prepend Config::baseWikiPath
 *   4. Append .md
 *   5. Read, parse via MarkdownToShard, return Shard array
 *
 * Registered as a Crystal (WikiPage inlay) by Crystal::loadAll().
 * The _init.php swaps this into the 'Page' inlay slot so that
 * {{Page::body}} and Pane::load() resolve through this crystal.
 *
 * @see Crystal
 * @see Page
 * @see MarkdownToShard
 */
class WikiPage extends Crystal
{
    /**
     * Construct and register as the wiki Page override.
     */
    public function __construct($pwObject = null, $name = null, $inlay = 'ClearView', $mos = null)
    {
        parent::__construct($pwObject, $name, $inlay, $mos);
    }

    /**
     * Get a field from the page.
     *
     * Intercepts 'body' to serve markdown content from the vault.
     * All other fields fall through to the parent (ProcessWire) Page.
     *
     * @param string $key  Field name
     * @return mixed  Shard array for body, or ProcessWire value for other fields
     */
    public function getField(string $key)
    {
        if ($key !== 'body') {
            return parent::getField($key);
        }

        // _init.php config vars are stored in Mosaic Config inlay
        // (not in Config::$config), so read via index() directly.
        $baseUrlShard = Mosaic::index('Config', 'baseWikiUrl');
        $basePathShard = Mosaic::index('Config', 'baseWikiPath');
        $baseUrl = $baseUrlShard ? (string)$baseUrlShard : null;
        $basePath = $basePathShard ? (string)$basePathShard : null;

        if ($baseUrl === null || $basePath === null) {
            // Wiki module not active — fall through to ProcessWire
            return parent::getField($key);
        }

        $requestUrl = Mosaic::getVar('Input::url') ?? $_SERVER['REQUEST_URI'] ?? '';

        // Strip query string
        $urlPath = parse_url($requestUrl, PHP_URL_PATH) ?? '/';

        // Check if this is a wiki URL
        if (!str_starts_with($urlPath, $baseUrl)) {
            return parent::getField($key);
        }

        // Map URL to filesystem path
        $relative = substr($urlPath, strlen($baseUrl));
        $relative = trim($relative, '/');
        if ($relative === '') {
            $relative = '_index';
        }

        $filePath = rtrim($basePath, '/') . '/' . $relative . '.md';

        if (!file_exists($filePath)) {
            return [
                'glyph' => 'div',
                'children' => [[
                    'glyph' => 'h1',
                    'value' => 'Page Not Found',
                ], [
                    'glyph' => 'p',
                    'value' => "Wiki page not found: {$relative}.md",
                ]],
            ];
        }

        $markdown = file_get_contents($filePath);
        if ($markdown === false) {
            return parent::getField($key);
        }

        // Build wiki base for wikilink resolution
        $wikiLinkBase = $baseUrl;
        if ($relative !== '_index' && str_contains($relative, '/')) {
            $wikiLinkBase = $baseUrl . dirname($relative) . '/';
        }

        $tree = MarkdownToShard::parse($markdown, $wikiLinkBase);

        return $tree;
    }
}
