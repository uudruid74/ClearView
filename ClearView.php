<?php

namespace ClearView;

use ClearView\Mosaic;
use ClearView\ClearView;
use ClearView\Facet;
use ClearView\Exception;
use ProcessWire;

/**
 * Singleton controller for the ClearView framework.
 *
 * Created by ClearView::Init($template) once per request. Owns the
 * JavaScript/async/OOB/debug buffers, resolves URL components, loads
 * Pane/Inlay classes from the module stack, and exposes static helpers
 * for scripts, HTMX detection, and cross-inlay dispatch.
 *
 * @see \ClearView\Mosaic
 * @see \ClearView\Facet
 * @see \ClearView\Crystal
 * @see \ClearView\Inlay
 */
class ClearView
{
    /** @var array Buffer for JavaScript code to be sent to the client */
    protected $scripts;

    /** @var string Non-blocking JS code is sent first */
    protected $async;

    /** @var string Buffer for out-of-band (OOB) HTML elements */
    protected $oobBuffer;

    /** @var string Buffer for pane-scoped debug messages (replaces global console.log) */
    protected $debugBuffer;

    /** @var string panename from the request */
    protected $panename = 'Default';

    /** @var string inlayname of the request */
    protected $inlayname = 'ClearView';

    /** @var string Current command or method name being processed */
    private $command;

    /** @var object|null The current creator object (Pane) providing rendering context */
    private $creator;

    /** @var ClearView|null Singleton instance of ClearView */
    private static $instance = null;

    /** @var array<string>|null Cached module search stack for the current request */
    private static $moduleStackCache = null;

    /**
     * Prevents cloning of the singleton instance.
     *
     * Used to enforce the singleton pattern, ensuring only one ClearView instance exists.
     *
     * Why: Maintains a single controller for framework operations, preventing inconsistent states.
     *
     * @return void
     */
    protected function __clone()
    {
    }

    /**
     * Prevents unserialization of the singleton instance.
     *
     * Used to enforce the singleton pattern, preventing restoration from serialized data.
     *
     * Why: Ensures the ClearView instance is created fresh via init() to avoid stale state.
     *
     * @return void
     */
    public function __wakeup()
    {
    }

    /**
     * Initializes the ClearView singleton.
     *
     * Called during system startup to actually call our Pane's inlay and begin processing
     *
     * Why: Sets up the core framework controller for rendering and event handling.
     *
     * @param $template name of the current template
     * @return void
     * @throws Exception If ClearView is reinitialized
     */
    /**
     * Returns the default method name for a given request method.
     *
     * Used as fallback when no explicit command is present in the URL.
     * Single source of truth for the GET→open, POST→html, PUT→put, DELETE→delete mapping.
     *
     * @param string|null $method Request method (defaults to current request method)
     * @return string Default method name
     */
    public static function defaultMethod(?string $method = null): string
    {
        $map = ['POST' => 'html', 'CLI' => 'open', 'GET' => 'open', 'PUT' => 'put', 'DELETE' => 'delete'];
        return $map[$method ?? \ProcessWire\input()->requestMethod()] ?? 'open';
    }

    public static function init($template): void
    {

        if (self::$instance !== null) {
            throw new Exception("ClearView is already initialized");
        }
        self::$instance = new self();
        Mosaic::init();
        Crystal::plugAllCrystals();

        if ($template == 'Default') {
            self::$instance->panename = $template;
            self::$instance->inlayname = 'Pane';
            // Default route now uses ClearView\Main (the renamed successor
            // to the old modules/vendor/glyphs/Default.php).
            $PaneClass = '\\ClearView\\Main';
        } else {
            list (self::$instance->panename,self::$instance->inlayname,self::$instance->command) = explode ('/', \ProcessWire\page()->url);
            $PaneClass = self::loadInlay(self::$instance->panename,self::$instance->inlayname);
        }

        // The <main> element integrates ProcessWire content via hx-boost, and only allows the html() method
        // To call other methods or elements, they must be placed in a Pane
        if (ClearView::is_htmx_boosted()) {
            self::$instance->command = 'html';
        } else if (empty(self::$instance->command)) {
            self::$instance->command = self::defaultMethod();
        }

        // Jump into ClearView
        if (empty(self::$instance->panename)) {
            throw new Exception("No panename");
        } else {
            Exception::outputComment("The Panename is " . json_decode(self::$instance->panename));
        }
        // Wire up the Pane Crystal with the correct ProcessWire page.
        // plugAllCrystals() auto-created it with null; replace it now that
        // we know the panename so Pane::name, Pane::title, etc. resolve
        // from the ProcessWire page.
        $pwPage = \ProcessWire\pages()->get('name=' . self::$instance->panename);
        if ($pwPage && $pwPage->id) {
            new \ClearView\Pane($pwPage, 'Pane', 'ClearView');
        }
        // Also store the name as a raw Mosaic variable for template
        // compatibility (legacy: some templates reference Pane::name
        // directly and the Pane Crystal resolves it).
        Mosaic::setVar("Pane::name", self::$instance->panename);
        try {
            Exception::outheader($template, \ProcessWire\config()->debug ? Config::TRACEMODE : null);
            new $PaneClass(self::$instance->panename,self::$instance->inlayname);
            self::$instance->creator->handleCommand(self::$instance->command);
        } catch (Throwable $e) {
            throw new ClearView\Exception($e);
        }
    }

    /**
     * Build a module search stack: Config::MODULES_LIST base + ProcessWire page hierarchy modules.
     *
     * Starts with the base module list from Config::MODULES_LIST (site, vendor),
     * then walks up the ProcessWire page tree collecting `modules` field values.
     * Page modules are inserted after 'site' so site always has priority.
     * 'vendor' is always the terminal fallback.
     *
     * @return array<string>
     */
    protected static function buildModuleStack(): array
    {
        if (self::$moduleStackCache !== null) {
            return self::$moduleStackCache;
        }
        $stack = Config::MODULES_LIST;
        try {
            $panename = Mosaic::getVar("Pane::name");
            if ($panename) {
                $page = \ProcessWire\pages()->get("name={$panename}");
                while ($page && $page->id) {
                    if (!empty($page->modules)) {
                        $modules = is_array($page->modules) ? $page->modules : [$page->modules];
                        // Insert page modules after 'site', before 'vendor'
                        foreach (array_reverse($modules) as $m) {
                            if ($m && !in_array($m, $stack, true)) {
                                $vendorIdx = array_search('vendor', $stack);
                                if ($vendorIdx !== false) {
                                    array_splice($stack, $vendorIdx, 0, $m);
                                } else {
                                    $stack[] = $m;
                                }
                            }
                        }
                    }
                    $page = $page->parent;
                }
            }
        } catch (\Throwable $e) {
            // Gracefully degrade to base module list on any page-walking error
        }
        return self::$moduleStackCache = array_values(array_unique($stack));
    }

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
    public static function loadInlay(string $panename, string $inlayname): string
    {
        // 0. Test harness: if InlayRegistry has a stub, return the stub class.
        if (self::inTesting() && \ClearView\Test\InlayRegistry::hasStub($panename, $inlayname)) {
            return \ClearView\Test\InlayRegistry::getClass($panename, $inlayname);
        }

        // 1. No inlay → load Pane directly
        if (empty($inlayname) || $inlayname === 'Pane') {
            return '\\ClearView\\Pane';
        }

        // 2. Inlay → search modules/<module>/panes/<panename>/<inlayname>.php
        $className = "{$panename}_{$inlayname}";
        foreach (self::buildModuleStack() as $module) {
            $path = __DIR__ . "/modules/{$module}/panes/{$panename}/{$inlayname}.php";
            if (file_exists($path)) {
                require_once($path);
                return "\\ClearView\\{$className}";
            }
        }
        throw new Exception("Cannot load inlay: {$panename}/{$inlayname}");
    }

    /**
     * Check if we're running in a test environment
     *
     * @return bool true if we're in a test/cli environment
     */
    public static function inTesting(): bool
    {
        return (php_sapi_name() === 'cli' || defined('STDIN'));
    }

    /**
     * Get/Set the current Pane handling this request. Set by the Pane
     * Used to retrieve the current rendering context for accessing inlay, methods, or data during processing.
     * Why: Provides access to the active rendering object for Facet and Mosaic operations.
     *
     * @param Shard The object that should be the master Pane
     * @return mixed The creator Pane object or to retrieve
     */
    public static function CurrentPane(?Shard $newCreator = null): mixed
    {
        if (!is_null($newCreator)) {
            self::$instance->creator = $newCreator;
        }
        return self::$instance->creator;
    }

    /**
     * Gets the current Panename handling this request
     * Why: Provides access to the requests initial panename
     *
     * @return string pane name
     */
    public static function id(): string
    {
        return self::$instance->panename;
    }

    /**
     * Gets the original Inlayname of the request
     *
     * @return string inlayname
     */
    public static function inlay(): string
    {
        return self::$instance->inlayname;
    }

    /**
     * Gets the original Method name of the request
     *
     * @return string method name
     */
    public static function method(): string
    {
        return self::$instance->command;
    }

    /**
     * Protected constructor for the singleton.

    /**
     * Protected constructor for the singleton.
     *
     * Initializes internal buffers and stacks for JavaScript, OOB elements. Only called
     * internally via init().
     *
     * Why: Encapsulates singleton creation to control instantiation and initialize state.
     *
     * @return void
     */
    protected function __construct()
    {
        $this->scripts = [];                    // JavaScript buffer to send to users
        $this->async = "";                      // Sent first (possibly via websocket)
        $this->oobBuffer = '';                  // Delayed output
        $this->debugBuffer = '';                // Pane-scoped debug layer
        $this->command = '';                    // Current command/method name
        $this->creator = null;                  // default creator for start-up
    }

    /**
     * Adds JavaScript to be executed on the client.
     *
     * Used to append JavaScript code to the scripts buffer, which is later rendered in a <script> tag. The code
     * is processed through Facet for template expansion.
     *
     * Why: Enables dynamic client-side scripting for interactivity or updates.
     *
     * @param string $string The JavaScript code to add.
     * @return void
     */
    public static function javascript($string): void
    {
        self::$instance->scripts[] = Facet::_($string);
    }

    /**
     * Sends asynchronous JavaScript to be executed on the client.
     *
     * Used to wrap JavaScript code in an async IIFE (Immediately Invoked Function Expression) and append it to
     * the async scripts buffer.  Each async line is sent separately.
     *
     * Why: Supports asynchronous client-side operations, such as fetching data or updating DOM elements.
     *
     * @param string $string The JavaScript code to execute asynchronously.
     * @return void
     */
    public static function asyncjs($string): void
    {
        self::$instance->async .= "(async () => { " . Facet::_($string) . " })();\n";
    }

    /**
     * Check if the request is made via HTMX
     */
    public static function is_htmx_request(): bool
    {
        return isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
    }

    /**
     * Check if the request is from a boosted link
     */
    public static function is_htmx_boosted(): bool
    {
        return isset($_SERVER['HTTP_HX_BOOSTED']) && $_SERVER['HTTP_HX_BOOSTED'] === 'true';
    }

    /**
     * Sends an out-of-band (OOB) element.
     *
     * Used to append HTML elements to the OOB buffer for later rendering, typically for HTMX-driven updates
     * outside the main response. The element is processed through Facet for template expansion.
     *
     * Why: Enables dynamic updates to non-target DOM elements via HTMX OOB swapping.
     *
     * @param string $elem The HTML element to send as OOB.
     * @return void
     */
    public static function sendOOB($elem): void
    {
        self::$instance->oobBuffer .= Facet::_($elem) . "\n";
    }

    /**
     * Appends a message to the pane-scoped debug layer buffer.
     *
     * Used instead of global console.log. Each message is wrapped in a
     * <div> and appended to the pane's #<name>-debug element at dump time.
     *
     * Why: Moves debug output from the browser console to pane-scoped DOM
     * layers, so each pane owns its diagnostic output.
     *
     * @param string $msg The debug message (already formatted).
     * @return void
     */
    public static function debugLayer(string $msg): void
    {
        if (!isset(self::$instance->debugBuffer)) {
            return;
        }
        self::$instance->debugBuffer .= "<div>{$msg}</div>\n";
    }

    /**
     * Dumps out-of-band data, scripts, and debug buffer.
     *
     * Used to output the OOB buffer and trigger Mosaic's OOB variable dump, then clear the OOB buffer and
     * render scripts via dumpScripts(). Debug and script output now targets pane-scoped
     * #<panename>-debug and #<panename>-script layers instead of global elements.
     *
     * Why: Synchronizes client-side state with server-side changes via HTMX,
     * using pane-scoped layer addresses.
     *
     * @return void
     */
    public static function dumpOOBdata(): void
    {
        $panename = Mosaic::getVar("Pane::name") ?? self::$instance->panename;
        echo self::$instance->oobBuffer;
        $scripts = self::$instance->async . "\n" . implode("\n",self::$instance->scripts);
        self::writeTo("{$panename}" . Config::LAYER_SUFFIX_SCRIPT, "<script hx-disable>{$scripts}</script>\n");
        if (self::$instance->debugBuffer) {
            self::appendTo("{$panename}" . Config::LAYER_SUFFIX_DEBUG, self::$instance->debugBuffer);
        }
        self::$instance->oobBuffer = self::$instance->scripts = self::$instance->async = null;
        self::$instance->debugBuffer = '';
    }

    /**
     * Preloads an image.
     *
     * Used to send a Link header to preload an image, improving page load performance.
     *
     * Why: Optimizes client-side rendering by prioritizing image loading.
     *
     * @param string $src The image source URL.
     * @return void
     */
    public static function preloadImage($src)
    {
        Exception::debug('EVENT', "Preloading {$src}");
        header("Link: rel=preload; <{$src}>; as=image");
    }

    /**
     * Gets rendered Hanna code.
     *
     * Used to process Hanna code (a ProcessWire feature for embedding dynamic content) through the
     * TextformatterHannaCode module, with template expansion via Facet.
     *
     * Why: Integrates ProcessWire’s dynamic content capabilities into ClearView templates.
     *
     * @param string $hannacode The Hanna code to render.
     * @return string The rendered HTML output.
     */
    public static function hanna($hannacode)
    {
        return ProcessWire\modules()->get('TextformatterHannaCode')->render(Facet::_($hannacode));
    }

    /**
     * Sends a message to another inlay.
     *
     * Used to trigger a command in a different inlay by sending a POST request to a constructed URL. Currently
     * marked as untested and outdated (FIXME).
     *
     * Why: Supports cross-inlay communication for dynamic interactions (e.g., updating another Pane).
     *
     * @param string $command The command to send.
     * @param string $inlay The target inlay name.
     * @param array|null $args The arguments to include in the POST request.
     * @return mixed The response from the POST request, or false on failure.
     */
    public static function sendMsgTo($command, $inlay, $args = null): mixed
    {
        Exception::debug('EVENT', "Sending $command to $inlay");
        // FIXME: Totally untested and outdated.
        $pageurl = Mosaic::getVar('Page::url');
        $response = ClearView::sendPost("{$pageurl}{$command}/", $args);
        if ($response !== false) { // FIXME: This combo is likely wrong
            echo $response;
        }
        return $response;
    }

    /**
     * Sends a POST request.
     *
     * Used to perform an HTTP POST request via ProcessWire’s WireHttp, typically for triggering server-side
     * actions or cross-inlay communication.
     *
     * Why: Enables server-side interactions for dynamic updates or messaging.
     *
     * @param string $url The URL to send the POST request to.
     * @param array $args The data to include in the POST request.
     * @return void
     */
    public static function sendPost($url, $args): void
    {
        Exception::debug('EVENT', "Triggering {$url}");
        echo (new ProcessWire\WireHttp())->post($url, $args);
    }

    /**
     * Includes a PHP view file from the module stack, then the vendor /views/ directory.
     *
     * Tries modules/<module>/views/<viewName>.php through the module stack first,
     * then falls back to the base vendor views:
     * modules/vendor/views/{{Page::name}}/<viewName>.php and
     * modules/vendor/views/Default/<viewName>.php.
     *
     * @param string $viewName The name of the view file (without .php extension).
     * @throws Exception If the view file is not found.
     */
    public static function loadPHPView(string $viewName): void
    {
        // 1. Module stack: modules/<module>/views/<viewName>.php
        foreach (self::buildModuleStack() as $module) {
            $path = __DIR__ . "/modules/{$module}/views/{$viewName}.php";
            if (file_exists($path)) {
                if (!(include $path)) {
                    throw new Exception("Can't load the PHP View {$path}");
                }
                Exception::debug('TRACE', "Loaded $path as View");
                return;
            }
        }
        // 2. Base: modules/vendor/views/{{Page::name}}/<viewName>.php
        $filePath = __DIR__ . "/modules/vendor/views/{{Page::name}}/{$viewName}.php";
        if (!file_exists($filePath)) {
            if (Mosaic::getVar("Pane::name") !== 'Default') {
                $filePath = __DIR__ . "/modules/vendor/views/Default/{$viewName}.php";
                if (!file_exists($filePath)) {
                    throw new Exception("View file $filePath not found: {$filePath}");
                }
            } else {
                throw new Exception("View filke $filePath not found: {$filePath}");
            }
            Exception::error("View file not found: {$filePath}");
        }
        if (!(include $filePath)) {
            throw new Exception ("Can't load the PHP View {$filePath} ");
        }
        Exception::debug('TRACE',"Loaded $filePath as View");
    }

    /**
     * Loads a glyph view file from the module stack, then base vendor glyphs.
     *
     * Searches modules/<module>/glyphs/<viewName>.php through the module stack
     * first, then falls back to the modules/vendor/glyphs/ directory.
     *
     * @param string $viewName The name of the glyph (without .php extension).
     * @return string|null the loaded class path or null if not found
     */
    public static function loadGlyph(string $viewName): ?string
    {
        foreach (self::buildModuleStack() as $module) {
            $path = __DIR__ . "/modules/{$module}/glyphs/{$viewName}.php";
            if (file_exists($path)) {
                require_once($path);
                return "\\ClearView\\Element\\$viewName";
            }
        }
        $filePath = __DIR__ . "/modules/vendor/glyphs/{$viewName}.php";
        if (!file_exists($filePath)) {
            Exception::debug("Glyph file not found: {$filePath}");
            return null;
        }
        require_once($filePath);
        return "\\ClearView\\Element\\$viewName";
    }

    /**
     * Loads a view file, captures its output, and returns it as a Shard object.
     *
     * Renders the PHP view through Facet::record(), parses the captured HTML with
     * jsonmangler::fromhtml(), and wraps the result in a Shard.  The view name is
     * passed as $context to fromhtml() enabling nested default-view resolution:
     * when <head> loads views/head.php, child elements inside that view may
     * auto-resolve to views/<pane>/head/<child>.php.
     *
     * @param string $view The name of the view file (without .php extension).
     * @param string|null $from Source marker (Shard::VIEW for view-loaded Shards).
     * @return Shard The Shard object representing the view's content.
     * @throws Exception If the view file is not found.
     */
    public static function loadView(string $view, ?string $from = null): Shard
    {
        $data = jsonmangler::fromhtml(
            (new Facet())
                    ->record()
                    ->loadPHPView($view)
                    ->close(),
            $view  // context: enables nested default-view resolution
        );
        unset($data['__loadExternal']);
        return Shard::loadShard(
            $data,
            inlay: "__$view",
            from: ($from === Shard::VIEW) ? Shard::VIEW : Shard::HTML,
        );
    }

    /**
     * Checks if a method exists in a class, searching the module stack for panes/.
     *
     * @param string $method The method name to check.
     * @param string $classname The class name (without namespace).
     * @return bool True if the method exists, false otherwise.
     */
    public static function methodExists($method, $classname): bool
    {
        $panename = Mosaic::getVar("Pane::name");

        // 1. No inlay class → check Pane directly
        if (empty($classname) || $classname === 'Pane') {
            return method_exists('\\ClearView\\Pane', $method);
        }

        // 2. Search modules/<module>/panes/<panename>/<classname>.php
        $fullClass = "{$panename}_{$classname}";
        foreach (self::buildModuleStack() as $module) {
            $path = __DIR__ . "/modules/{$module}/panes/{$panename}/{$classname}.php";
            if (file_exists($path)) {
                require_once($path);
                return method_exists("\\ClearView\\{$fullClass}", $method);
            }
        }
        return false;
    }

    /**
     * Writes HTML to an element.
     *
     * Used to output HTML content to a specific DOM element via OOB swapping, replacing its inner content.
     *
     * Why: Supports targeted DOM updates for dynamic rendering via HTMX.
     *
     * @param string $id The DOM element ID to write to.
     * @param string $text The HTML content to write.
     * @return void
     */
    public static function writeTo($id, $text)
    {
        echo "<div id=\"{$id}\" hx-swap-oob=\"true\">{$text}</div>\n";
    }

    /**
     * Appends HTML to an element.
     *
     * Used to append HTML content to a specific DOM element via OOB swapping, adding to its existing content.
     *
     * Why: Enables incremental DOM updates for dynamic rendering via HTMX.
     *
     * @param string $id The DOM element ID to append to.
     * @param string $elem The HTML content to append.
     * @return void
     */
    public static function appendTo($id, $elem)
    {
        echo "<div id=\"{$id}\" hx-swap-oob=\"beforeend\">{$elem}</div>\n";
    }

    /**
     * Calls a method with an HTMX trigger.
     *
     * Used to generate a hidden input field that triggers a POST request to a method on a specified event
     * (e.g., 'load'), including the current Mosaic pane’s data.  Can be used to connect any given event
     * to a ClearView method callback
     *
     * Why: Enables event-driven method invocation for dynamic interactions via HTMX.
     *
     * @param string $method The method name to call.
     * @param string $on The event to trigger on (default: 'load').
     * @param string|null $url The URL for the POST request (defaults to page URL with method and inlay).
     * @return void
     */
    public static function callMethod($method, $on = "load", $url = null)
    {
        $extdata = Mosaic::getVar('Input::nextinlay');
        if (isset($extdata)) {
            $extdata = "{$extdata}/";
        } else {
            $extdata = '';
        }
        if (method_exists(self::$instance->creator, $method)) {
            $posturl = $url ?? (Mosaic::getVar('Page::url') . "{$method}/{$extdata}");
            Exception::debug('EVENT', "Added callback for {$method} on {$on}");
            new Facet(
            "<input
                type='hidden'
                id='on{$on}$method'
                name='{$on}{$method}'
                hx-include='#{{Pane::name}}'
                hx-target='this'
                hx-post='{$posturl}'
                hx-trigger='{$on}'
            >");
        } else {
            Exception::debug('EVENT', "No method {$method} in {{inlay}}");
        }
    }

    /**
     * This __callStatic wrapper returns Crystals statically for ClearView::Crystalname()
     */
    public static function __callStatic($method,$args)
    {
        if (empty($args)) {
            if ($method === 'Facet') {
                return Facet::me();
            }
            return Mosaic::index('ClearView',$method);
        } else {
            return Mosaic::index('ClearView',$method)->from($args);
        }
    }
}
