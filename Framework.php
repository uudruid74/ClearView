<?php
namespace ClearView;
use ClearView\ClearView;
use ClearView\Facet;
use ClearView\Element;
use ClearView\Mosaic;
use ClearView\Exception;
use ClearView\Inlay;
use ClearView\Crystal;
use ClearView\Config;
use ProcessWire;

/**
 * Framework — the ClearView request lifecycle engine.
 * Boots Mosaic, resolves URL parameters, loads inlay classes, and
 * dispatches commands.  Implements ArrayAccess for pane-scoped variable
 * access with existence-check routing: writes land where the variable
 * was first found, reads check current inlay first then "Pane" inlay.
 * Replaces the old Pane request-handler class.  The "Pane" is now a
 * crystal (crystals/Pane.php) for {{Pane::headline}} template lookups.
 */
class Framework implements \ArrayAccess
{
    /** @var Framework|null The active Framework instance for this request. */
    public static ?Framework $instance = null;

    /** @var Mosaic The Mosaic instance for this request. */
    public Mosaic $mosaic;

    /** Check if we're running in a test environment. */
    public static function inTesting(): bool
    {
        return (php_sapi_name() === 'cli' || defined('STDIN'));
    }

    /** Check if the request is made via HTMX. */
    public static function is_htmx_request(): bool
    {
        return isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
    }

    /** Check if the request is from a boosted link. */
    public static function is_htmx_boosted(): bool
    {
        return isset($_SERVER['HTTP_HX_BOOSTED']) && $_SERVER['HTTP_HX_BOOSTED'] === 'true';
    }

    // ── Framework instance accessor ──────────────────────────────

    /**
     * Returns the active Framework instance for this request.
     * There is exactly one Framework per request; subclasses like
     * TestRig set themselves as the instance during construction.
     * @return self Description.
     */
    public static function instance(): self
    {
        return self::$instance;
    }

    // ── Module list ──────────────────────────────────────────────

    /**
     * Returns the ordered module list for resource lookups.
     * Base implementation returns Config::MODULES_LIST.  PaneAttr::modules
     * (from the <pane modules="..."> attribute) is prepended when set.
     * Subclasses override to inject framework-specific modules (e.g.
     * TestRig prepends 'testjig' for null crystals).
     * @return array<string>
     */
    public function getModuleList(): array
    {
        $modules = Config::MODULES_LIST;

        // Merge per-pane module override if PaneAttr is loaded
        try {
            $paneModules = Mosaic::getVar('PaneAttr::modules');
            if ($paneModules && is_string($paneModules) && strlen($paneModules) > 0) {
                $paneList = array_map('trim', explode(',', $paneModules));
                $modules = array_merge($paneList, $modules);
            }
        } catch (\Throwable $e) {
            // PaneAttr not loaded yet during bootstrap — use Config defaults
        }

        // Always ensure 'vendor' is the terminal fallback
        if (!in_array('vendor', $modules, true)) {
            $modules[] = 'vendor';
        }
        return array_values(array_unique($modules));
    }

    /**
     * The public version calls through so we can override getModuleList
     */
    public function Modules(): array
    {
	return self::$instance->getModuleList();
    }

    // ── ArrayAccess — existence-check routing ──────────────────────

    /**
     * Get a variable with existence-check routing.
     * 1. Contains :: {{ or . → delegate to Mosaic::getVar (expression routing).
     * 2. Exists in current inlay → return that.
     * 3. Exists in "Pane" inlay → return that.
     * 4. Otherwise → null.
     * @param mixed $key Description.
     * @return mixed Description.
     */
    public function offsetGet($key): mixed
    {
        if (str_contains((string)$key, '::') || str_contains((string)$key, '{{') || str_contains((string)$key, '.')) {
            return Mosaic::getVar($key);
        }

        $currentInlay = Mosaic::getVar('Input::inlayname');

        // Check current inlay first
        if ($currentInlay && Mosaic::exists($currentInlay, $key)) {
            $shard = Mosaic::index($currentInlay, $key);
            if ($shard) {
                return $shard->getField('value') ?? $shard;
            }
        }

        // Fall back to Pane inlay
        if (Mosaic::exists('Pane', $key)) {
            $shard = Mosaic::index('Pane', $key);
            if ($shard) {
                return $shard->getField('value') ?? $shard;
            }
        }

        // Last resort — try Mosaic::getVar for crystal routing
        return Mosaic::getVar($key);
    }

    /**
     * Set a variable with existence-check routing.
     * 1. Contains :: {{ or . → delegate to Mosaic::setVar.
     * 2. Exists in current inlay → update there.
     * 3. Exists in "Pane" inlay → update there.
     * 4. Otherwise → store in "Pane" inlay (pane-scoped by default).
     * @param mixed $key Description.
     * @param mixed $value Description.
     */
    public function offsetSet($key, $value): void
    {
        if (str_contains((string)$key, '::') || str_contains((string)$key, '{{') || str_contains((string)$key, '.')) {
            Mosaic::setVar($key, $value);
            return;
        }

        $currentInlay = Mosaic::getVar('Input::inlayname');

        if ($currentInlay && Mosaic::exists($currentInlay, $key)) {
            Mosaic::setVar($key, $value, $currentInlay);
            return;
        }

        if (Mosaic::exists('Pane', $key)) {
            Mosaic::setVar($key, $value, 'Pane');
            return;
        }

        // Default: store under Pane inlay
        Mosaic::setVar($key, $value, 'Pane');
    }

    public function offsetExists($key): bool
    {
        return $this->offsetGet($key) !== null;
    }

    public function offsetUnset($key): void
    {
        $currentInlay = Mosaic::getVar('Input::inlayname');
        if ($currentInlay && Mosaic::exists($currentInlay, $key)) {
            Mosaic::delVar($key, $currentInlay);
            return;
        }
        Mosaic::delVar($key, 'Pane');
    }

    // ── Lifecycle methods ────────────────────────────────────────

    /**
     * Initializes the ClearView framework from the request.
     * @return void
     */
    public function __construct(string $template)
    {
        // Register as the active Framework BEFORE Mosaic::load() so
        // Crystal::loadAll() can call $this->Modules() for the module list.
        self::$instance = $this;

        // Load Mosaic via the static factory — crystals are initialized,
        // Input crystal is ready with panename/inlayname/methodname.
        $this->mosaic = Mosaic::load();

        $PaneClass = Inlay::load($this['Input::panename'], $this['Input::inlayname']);

        // Debug header — tracemode comes from Config, not ProcessWire
        Exception::outheader(Config::TRACEMODE);

        return new $PaneClass();
    }

    /** Default full-page render. */
    public function open(): void
    {
        self::html();
    }

    /** Default HTML method. */
    public function html(?string $template = null): void
    {
        (new Facet($this['Pane::body']))
	    ->render("Pane::open", 
		match: [[ $this['Input::methodname'] == 'open']])
            ->html('Pane::body')
	    ->triggerevent('paneopen', 
	        match: [[ $this['Input::methodname'] == 'open']])
	    ->triggerevent('inlaychange', ['inlay' => $currentInlay], 
		unless: [[ $this['Shared::prevInlay'] === $this['Input::inlayname'] ]])
            ->close();
        $this['Shared::prevInlay'] = $currentInlay;
    }

    /** Renders the launcher element. */
    public function launcher(): void
    {
	(new Facet($this))
            ->html('Pane::launcher')
            ->close();
    }

    /** Triggers closepane event. */
    public function close($delay = null): void
    {
        $this->triggerevent('closepane', ['delay' => $delay]);
    }

    /** Redirects to a URL via HX-Location JSON payload. */
    public function redirect($url = null): void
    {
        $url = $url ?? $this['Page::url'];
        header("HX-Location: " . json_encode(["path" => $url]));
    }

    /** Reloads the page. */
    public function reloadPage(): void
    {
        $this->redirect();
    }

    /** Triggers an htmx event. Calls through instance for subclass overrides. */
    public static function triggerevent(string $event, $params = null): void
    {
        self::instance()->onTriggerevent($event, $params);
    }

    /** Instance override point for triggerevent. */
    public function onTriggerevent(string $event, $params = null): void
    {
        $this->onSendHtmxHeader('HX-Trigger', $event, $params);
    }

    /** Sends a special header in the server response. Calls through instance. */
    public static function sendHtmxHeader(string $header, $event, $params): void
    {
        self::instance()->onSendHtmxHeader($header, $event, $params);
    }

    /** Instance override point for sendHtmxHeader. */
    public function onSendHtmxHeader(string $header, $event, $params): void
    {
        Exception::debug('EVENT', "Triggering {$event}");
        if (isset($params) && is_array($params)) {
            $events = array_map(fn($d) => array_merge($d, ['Pane' => $this['Input::panename']]), $params);
            header("HX-Trigger: " . json_encode($events));
        } else {
            header("HX-Trigger: {$event}");
        }
    }

    /** Sets the HX-Retarget header. Calls through instance. */
    public static function retargetResult(string $target, $params = null): void
    {
        self::instance()->onRetargetResult($target, $params);
    }

    /** Instance override point for retargetResult. */
    public function onRetargetResult(string $target, $params = null): void
    {
        self::instance()->onSendHtmxHeader('HX-Retarget', $target, $params);
    }

    /** Wrapper around Exception::debug() for inlays. */
    public function debug($msg, $depth = 2): self
    {
        Exception::debug('PANE', $msg, $depth);
        return $this;
    }

    /** Dispatches commands based on URL segments. */
    public function handleCommand(): void
    {
        $command = $this['Input::methodname'] ?: self::defaultMethod();

        // Slurp up variables first
        Mosaic::loadMosaic($this['Input::all']);

        if (method_exists($this, $command)) {
            $reflectionMethod = new \ReflectionMethod($this, $command);

            // PaneKey security
            $providedToken = $this['Shared::PaneKey'];
            $expectedToken = $this['Session::PaneKey'];
            if ($providedToken !== $expectedToken) {
                throw new Exception('Invalid PaneKey: $providedToken vs $expectedToken');
            }
            if ($reflectionMethod->isPrivate() || str_starts_with($command, '_')) {
                throw new \Exception('Access denied: Cannot call private or underscored methods.');
            }

            Exception::debug('EVENT', "Executing {$command} from {{uppercase\\Input::requestMethod}} {{Input::url}}");
            (new Facet($this))
                ->forward($command)
                ->html(['glyph' => 'mosaic'])
                ->close();
        } else {
            $pageField = $this["Page::$command"];
            if ($pageField !== null) {
                (new Facet()
                    ->html($pageField))
                    ->close();
            } else {
                $this->doesNotUnderstand($command);
            }
        }
        // Send buffered variable and script updates
	ClearView::dumpOOBdata();
    }

    /** Handles unknown commands. */
    public function doesNotUnderstand($name = null): void
    {
        $name = $name ?? $this['Input::methodname'];
        throw new Exception("I don't know how to '$name', from {{Input::url}}");
    }

    /** Catch unknown method calls. */
    public function __call($name, $arguments): void
    {
        $this->doesNotUnderstand($name);
    }
}
