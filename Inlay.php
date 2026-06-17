<?php
namespace ClearView;
use ClearView\Pane;
use ClearView\Facet;
use ClearView\Mosaic;
use ClearView\Shared;
use ClearView\Page;

/**
 * Subclass of Pane used when the URL includes an inlay segment.
 *
 * An inlay represents a tab/subpage inside a pane. The default html()
 * method renders Page::body and fires inlaychange when the inlay differs
 * from Shared::prevInlay.
 *
 * Inlay subclasses live under modules/<module>/panes/<panename>/<inlayname>.php
 * with class names like ClearView\<panename>_<inlayname>.
 *
 * @see ClearView\Pane
 * @see ClearView\Shared
 */
class Inlay extends Pane
{
    /**
     * Load Inlay by panename and inlayname.
     *
     * When no inlay is specified (or 'Pane'), returns Pane class directly.
     * With an inlay, searches the module stack for
     * modules/<module>/panes/<panename>/<inlayname>.php
     * and returns ClearView\<panename>_<inlayname>.
     *
     * @param string $panename
     * @param string $inlayname
     * @return string class name that was loaded
     * @throws Exception if no matching file is found
     */
    public static function load(string $panename, string $inlayname): string
    {
        // 0. Test harness: if InlayRegistry has a stub, return the stub class.
        if (php_sapi_name() === 'cli' || defined('STDIN')) {
            if (\ClearView\Test\InlayRegistry::hasStub($panename, $inlayname)) {
                return \ClearView\Test\InlayRegistry::getClass($panename, $inlayname);
            }
        }

        // 1. No inlay → load Pane directly
        if (empty($inlayname) || $inlayname === 'Pane') {
            return '\\ClearView\\Pane';
        }

        // 2. Inlay → search modules/<module>/panes/<panename>/<inlayname>.php
        $className = "{$panename}_{$inlayname}";
        foreach (Page::buildModuleStack() as $module) {
            $path = __DIR__ . "/modules/{$module}/panes/{$panename}/{$inlayname}.php";
            if (file_exists($path)) {
                require_once($path);
                return "\\ClearView\\{$className}";
            }
        }
        throw new \ClearView\Exception("Cannot load inlay: {$panename}/{$inlayname}");
    }

    /**
     * Default HTML response for an inlay.
     *
     * Renders {{Page::body}} from the ProcessWire page matching the
     * pane/inlay URL. Fires inlaychange event when Shared::prevInlay
     * differs from the current inlay name.
     *
     * @return void
     */
    public function html(): void
    {
        $inlayName = $this->inlay();

        // Fire inlaychange if the inlay changed
        if (Shared::$prevInlay !== null && Shared::$prevInlay !== $inlayName) {
            $this->triggerevent('inlaychange', ['pane' => $this->panename]);
        }

        (new Facet($this->body()))
            ->html("{{Page::body}}")
            ->close();

        Shared::$prevInlay = $inlayName;
    }
}
