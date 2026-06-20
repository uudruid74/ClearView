<?php

namespace ClearView;

use ClearView\Crystal;
use ClearView\Element;
use ClearView\Facet;
use ClearView\Shard;
use ClearView\Exception;
use ClearView\Page;
use ProcessWire;

/**
 * Crystal for view loading and resolution.
 *
 * Consolidates view-file discovery (module stack + vendor fallback)
 * and view → Shard conversion. Used as {{View::viewname}} in templates
 * and __loadExternal declarations.
 *
 * @see \ClearView\Crystal
 * @see \ClearView\Element::loadView  (delegation stub for backward compat)
 */
class View extends Crystal
{
    /**
     * Gets a View in html format and creates a local DOM formed from Shards.
     * This lets you encapsulate your UI into small chunks you can include as
     * {{View::viewname}} in templates or __loadExternal declarations.
     *
     * @param string|null $key The view to retrieve
     * @return mixed The Shard or null if not found.
     */
    public function getVar($key = null)
    {
        return self::loadView($key);
    }

    // ── View-file discovery ───────────────────────────────────

    /**
     * Includes a PHP view file from the module stack, then the vendor /views/ directory.
     *
     * Tries modules/<module>/views/<viewName>.php through the module stack first,
     * then falls back to the base vendor views.
     *
     * @param string $viewName The name of the view file (without .php extension).
     * @throws Exception If the view file is not found.
     */
    public static function loadPHPView(string $viewName): void
    {
        $baseDir = dirname(__DIR__);  // one level up from crystals/

        // 1. Module stack: modules/<module>/views/<viewName>.php
        foreach (Page::buildModuleStack() as $module) {
            $path = "{$baseDir}/modules/{$module}/views/{$viewName}.php";
            if (file_exists($path)) {
                if (!(include $path)) {
                    throw new Exception("Can't load the PHP View {$path}");
                }
                Exception::debug('TRACE', "Loaded $path as View");
                return;
            }
        }

        // 2. Base: modules/vendor/views/{{Page::name}}/<viewName>.php
        $filePath = "{$baseDir}/modules/vendor/views/{{Page::name}}/{$viewName}.php";
        if (!file_exists($filePath)) {
            Exception::error("View file not found: {$filePath}");
            if (ClearView::Mosaic()->getVar("Pane::name") !== 'Default') {
                $filePath = "{$baseDir}/modules/vendor/views/Default/{$viewName}.php";
                if (!file_exists($filePath)) {
                    throw new Exception("View file $filePath not found: {$filePath}");
                }
            } else {
                throw new Exception("View file $filePath not found: {$filePath}");
            }
        }
        if (!(include $filePath)) {
            throw new Exception("Can't load the PHP View {$filePath} ");
        }
        Exception::debug('TRACE', "Loaded $filePath as View");
    }

    // ── View → Shard conversion ───────────────────────────────

    /**
     * Loads a view file, captures its output, and returns it as a Shard object.
     *
     * @param string $view The name of the view file (without .php extension).
     * @param string|null $from Source marker (Shard::VIEW for view-loaded Shards).
     * @return Shard The Shard object representing the view's content.
     * @throws Exception If the view file is not found.
     */
    public static function loadView(string $view, ?string $from = null): Shard
    {
        $facet = new Facet();
        $facet->record();
        self::loadPHPView($view);  // direct, skips Facet::__call routing
        $output = $facet->close();

        $data = jsonmangler::fromhtml($output, $view);
        unset($data['__loadExternal']);
        return Shard::loadShard(
            $data,
            inlay: "__$view",
            from: ($from === Shard::VIEW) ? Shard::VIEW : Shard::HTML,
        );
    }
}
