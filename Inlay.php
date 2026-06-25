<?php
namespace ClearView;
use ClearView\Framework;
use ClearView\Facet;
use ClearView\Mosaic;
use ClearView\Page;

/**
 * Subclass of Runtime used when the URL includes an inlay segment.
 * An inlay represents a tab/subpage inside a pane. The default html()
 * method renders Page::body and fires inlaychange when the inlay differs
 * from $this['Shared::prevInlay'].
 * Inlay subclasses live under modules/<module>/panes/<panename>/<inlayname>.php
 * with class names like ClearView\<panename>_<inlayname>.
 * @see ClearView\\Framework
 * @see ClearView\\Mosaic
 */
class Inlay extends Framework
{
    /**
     * Load Inlay by panename and inlayname.
     * When no inlay is specified (or 'Pane'), returns Pane class directly.
     * With an inlay, searches the module stack for
     * modules/<module>/panes/<panename>/<inlayname>.php
     * and returns ClearView\<panename>_<inlayname>.
     * @param string $panename
     * @param string $inlayname
     * @return string class name that was loaded
     * @throws Exception if no matching file is found
     */
    public static function load(string $panename, string $inlayname): string
    {
        // 1. No inlay → Pane is already loaded
        if (empty($inlayname) || $inlayname === 'Pane') {
            return '\\ClearView\\Framework';
        }

        // 2. Inlay → search modules/<module>/panes/<panename>/<inlayname>.php
        $className = "{$panename}_{$inlayname}";
        foreach (Framework::Modules() as $module) {
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
     * Renders {{Page::body}} from the ProcessWire page matching the
     * pane/inlay URL. Fires inlaychange event when $this['Shared::prevInlay']
     * differs from the current inlay name.
     * @return void
     */
    public function html(?string $template = null): void
    {
        (new Facet())
            ->html('Page::body')
            ->triggerevent('inlaychange', ['inlay' => $this['Input::inlayname']],
                unless: [[ $this['Shared::prevInlay'] === $this['Shared::inlayname'] ]])
            ->close();

        $this['Shared::prevInlay'] = $this['Input::inlayname'];
    }
}
