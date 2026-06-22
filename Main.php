<?php
namespace ClearView;
use ClearView\Runtime;
use ClearView\Facet;
use ClearView\Exception;

/**
 * Default ProcessWire route handler for URLs that do not have their own pane.
 *
 * Main is the renamed successor to the old `modules/vendor/glyphs/Default.php`.
 * It renders the full HTML document (<!DOCTYPE html>, <html>, head/body via view loading)
 * for non-HX requests, and delegates boosted navigation to the <main> glyph.
 *
 * Lifecycle:
 * 1. ProcessWire matches a template that maps to Main.
 * 2. ClearView::init() constructs a Main instance as the current pane.
 * 3. Main::render() emits the full HTML document.
 * 4. Inside the document, <main view="..."> loads the configured layout view.
 *
 * @see ClearView\\Runtime
 * @see ClearView\\Facet
 * @see ClearView\\Mosaic
 */
class Main extends Runtime
{
    /** @var string External view file to load (View::Default) */
    public $__loadExternal = "View::Default";

    /**
     * Full page render for non-HX requests.
     *
     * Emits <!DOCTYPE html> and opens the <html> tag. The view loaded by
     * <main> supplies <head>, <body>, and the <main> element itself via
     * Facet template processing.
     *
     * @return void
     */
    public function render(): void
    {
        Exception::outputComment("Main Page Rendering - render");
        (new Facet($this['Pane::body']))
            ->out("<!DOCTYPE html>")
            ->open("<html {{lang=lang}} {{data-theme=data-theme}} {{manifest=manifest}} {{dir=dir}} {{xmlns=xmlns}}>")
            ->close();
    }
}
// end of class
