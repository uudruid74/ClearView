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

class Pane implements \ArrayAccess
{
    /** @var Pane|null Current pane instance (rendering context). */
    private static ?Pane $currentPane = null;

    /** @var string Current command being executed. */
    public string $command = '';

    /** @var Mosaic The Mosaic singleton for this pane. */
    protected Mosaic $mosaic;

    /** @var Element|null The body Element from ProcessWire body field. */
    protected ?Element $body = null;

    /** @var string The pane name. */
    protected string $panename;

    /** @var string The inlay name. */
    protected string $inlayname;

    /**
     * Get/Set the current Pane handling this request.
     */
    public static function CurrentPane(?Pane $newCreator = null): ?Pane
    {
        if ($newCreator !== null) {
            self::$currentPane = $newCreator;
        }
        return self::$currentPane;
    }

    /**
     * Returns the default method name for a given request method.
     */
    public static function defaultMethod(?string $method = null): string
    {
        $map = ['POST' => 'html', 'CLI' => 'open', 'GET' => 'open', 'PUT' => 'put', 'DELETE' => 'delete'];
        return $map[$method ?? \ProcessWire\input()->requestMethod()] ?? 'open';
    }

    /**
     * Check if we're running in a test environment.
     */
    public static function inTesting(): bool
    {
        return (php_sapi_name() === 'cli' || defined('STDIN'));
    }

    /**
     * Check if the request is made via HTMX.
     */
    public static function is_htmx_request(): bool
    {
        return isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
    }

    /**
     * Check if the request is from a boosted link.
     */
    public static function is_htmx_boosted(): bool
    {
        return isset($_SERVER['HTTP_HX_BOOSTED']) && $_SERVER['HTTP_HX_BOOSTED'] === 'true';
    }

    /**
     * Initializes the pane.
     */
    public function __construct($panename, $inlayname = 'Default')
    {
        Pane::CurrentPane($this);
        $this->panename = $panename;
        $this->inlayname = $inlayname;
        $this->mosaic = Mosaic::init();
    }

    /**
     * Returns the body Element, created from the ProcessWire body field.
     */
    public function body(): Element
    {
        if ($this->body === null) {
            $this->body = new Element([
                'id'    => $this->panename . 'Pane',
                'inlay' => $this->inlayname,
                'name'  => $this->panename,
            ]);
        }
        return $this->body;
    }

    /**
     * Fills the Mosaic with an array of values.
     */
    public function fill(array $values): void
    {
        Mosaic::fill($values);
    }

    /**
     * Gets a Mosaic variable.
     */
    public function getVar(string $expression)
    {
        return Mosaic::getVar($expression);
    }

    /**
     * Gets the inlay name for this pane.
     */
    public function inlay(): string
    {
        return $this->inlayname;
    }

    /**
     * Gets the id for this pane (used by Facet::me() fallback).
     */
    public function id(): string
    {
        return $this->panename . 'Pane';
    }

    // ── ArrayAccess ──────────────────────────────────────────────

    public function offsetGet($key): mixed
    {
        return Mosaic::getVar($key);
    }

    public function offsetSet($key, $value): void
    {
        Mosaic::setVar($key, $value);
    }

    public function offsetExists($key): bool
    {
        return Mosaic::getVar($key) !== null;
    }

    public function offsetUnset($key): void
    {
        Mosaic::delVar($key);
    }

    // ── Lifecycle methods ────────────────────────────────────────

    /**
     * Initializes the ClearView framework from the request.
     *
     * Called from _main.php. Creates the Mosaic, loads crystals,
     * resolves panename/inlayname/command from URL, loads the Pane
     * class from the module stack, and dispatches the command.
     *
     * @param string $template The ProcessWire page template name.
     * @return void
     * @throws Exception on errors.
     */
    public static function init(string $template): void
    {
        Mosaic::init();
        Crystal::loadAll();

        $panename = 'Default';
        $inlayname = 'ClearView';
        $command = '';

        if ($template == 'Default') {
            $panename = $template;
            $inlayname = 'Pane';
            $PaneClass = '\\ClearView\\Main';
        } else {
            $segments = array_values(array_filter(explode('/', trim(\ProcessWire\page()->url, '/')), 'strlen'));
            $count = count($segments);
            if ($count >= 1) $panename  = $segments[0];
            if ($count >= 2) $inlayname  = $segments[1];
            if ($count >= 3) $command    = $segments[2];
            if (empty($panename))  $panename = 'Default';
            if (empty($inlayname)) $inlayname = 'ClearView';
            $PaneClass = Inlay::load($panename, $inlayname);
        }

        if (self::is_htmx_boosted()) {
            $command = 'html';
        } elseif (empty($command)) {
            $command = self::defaultMethod();
        }

        if (empty($panename)) {
            throw new Exception("No panename");
        }
        Exception::outputComment("The Panename is " . json_decode($panename));

        // Wire up the Pane Crystal with the correct ProcessWire page.
        $pwPage = \ProcessWire\pages()->get('name=' . $panename);
        if ($pwPage && $pwPage->id) {
            new \ClearView\PaneCrystal($pwPage, $panename, 'Pane');
        }
        Mosaic::setVar("Pane::name", $panename);
        Mosaic::setVar("Input::inlayname", $inlayname);

        try {
            Exception::outheader($template, \ProcessWire\config()->debug ? Config::TRACEMODE : null);
            $pane = new $PaneClass($panename, $inlayname);
            $pane->command = $command;
            $pane->handleCommand($command);
        } catch (\Throwable $e) {
            throw new \ClearView\Exception($e);
        }
    }

    /**
     * Default full-page render. Opens the container tag, renders element
     * contents, outputs the body template, and closes. Fires paneopen event.
     */
    public function open(): void
    {
        (new Facet($this->body()))
            ->open("{{Pane::open}}")
            ->render()
            ->html("{{Pane::body^^View::" . $this->panename . "}}")
            ->close();
        $this->triggerevent('paneopen');
    }

    /**
     * Renders the launcher element (e.g., a button that opens the pane).
     * Reads Pane::launcher field. Sets hx-target to #layerstack,
     * hx-swap to beforeend, and method to open.
     */
    public function launcher(): void
    {
        (new Facet($this->body()))
            ->open("{{Pane::launcher}}", null, null, null, false, true)
            ->close();
    }

    /**
     * Triggers closepane event with optional delay.
     * @param mixed $delay Optional delay value for the event payload.
     */
    public function close($delay = null): void
    {
        $this->triggerevent('closepane', $delay);
    }

    /**
     * Default HTML method. Renders Pane::body. Detects inlay changes
     * by comparing against Shared::$prevInlay and fires inlaychange.
     * @param string|null $template Optional template to render instead of Pane::body.
     */
    public function html(?string $template = null): void
    {
        $currentInlay = $this->inlay();
        if (Shared::$prevInlay !== null && Shared::$prevInlay !== $currentInlay) {
            $this->triggerevent('inlaychange', ['inlay' => $currentInlay]);
        }
        Shared::$prevInlay = $currentInlay;

        $body = $template ?? "{{Pane::body}}";
        (new Facet($this->body()))
            ->open($body)
            ->render()
            ->close();
    }

    /**
     * Redirects to a URL.
     * @param string $url The URL.
     */
    public static function redirect($url): void
    {
        $url = $url ?? Mosaic::getVar('Page::url');
        header("HX-Location: {$url}");
    }

    /**
     * Reloads the page.
     */
    public static function reloadPage(): void
    {
        self::redirect();
    }

    /**
     * Triggers an htmx event. Pane name is always included in the JSON payload.
     * @param string $event The event to trigger.
     * @param mixed $params Optional event parameters.
     */
    public function triggerevent($event, $params = null): self
    {
        Exception::debug('EVENT', "Triggering {$event}");
        $paneName = $this->panename;
        if (isset($params)) {
            $payload = is_array($params)
                ? array_merge(['pane' => $paneName], $params)
                : ['pane' => $paneName, 'value' => $params];
            $headerLine = 'HX-Trigger: ' . json_encode([$event => $payload]);
        } else {
            $headerLine = 'HX-Trigger: ' . json_encode([$event => ['pane' => $paneName]]);
        }
        // Buffer the header so it's emitted before body output in dumpOOBdata().
        $this['ClearView']->bufferTriggerEvent($headerLine);
        return $this;
    }

    /**
     * Sets the HX-Retarget header to redirect an HTMX response to a
     * different target element than the one that triggered the request.
     *
     * Used by <attr view="..."> when a layout change requires the response
     * to swap the outer <main> element instead of the inner <article>.
     *
     * @param string $target CSS selector for the new swap target.
     */
    public static function retargetResult(string $target): void
    {
        header("HX-Retarget: #{$target}");
    }

    /**
     * Wrapper around Exception::debug() for inlays
     * @return $this for chaining
     */
    public function debug($msg, $depth = 2): self
    {
        Exception::debug('PANE', $msg, $depth);
        return $this;
    }

    /**
     * Dispatches commands based on URL segments.
     * @param string|null $command The command to execute.
     */
    public function handleCommand(?string $command = '_doesNotUnderstand'): void
    {
        // Only apply request-method fallback if no command was specified in the URL.
        // init() already computed the right command; we only fill in a default here
        // when nothing was provided.
        if ($command === '_doesNotUnderstand') {
            $command = self::defaultMethod();
        }
        $this->command = $command;

        // Slurp up variables first
        Mosaic::loadMosaic($this->getVar('Input::all'));

        if (method_exists($this, $command)) {
            $reflectionMethod = new \ReflectionMethod($this, $command);

            // PaneKey security: <button>s and embedded <pane>s create Pane::Key and pass it as an URL parameter.
            $providedToken = $this->getVar("Pane::Key");         // CSRF token for this pane
            $expectedToken = $this->getVar("Session::PaneKey");  // returns different values based on Pane::name
            if ($providedToken !== $expectedToken) {
                throw new Exception('Invalid PaneKey: $providedToken vs $expectedToken');
            }
            // No private methods, even if the panekay matches
            if ($reflectionMethod->isPrivate() || str_starts_with($command, '_')) {
                throw new \Exception('Access denied: Cannot call private or underscored methods.');
            }

            // Execute the method if all checks pass.
            Exception::debug('EVENT', "Executing {$command} from {{uppercase\\Input::requestMethod}} {{Input::url}}");
            (new Facet())
                ->forward($command) // the command to be executed
                ->create(new \ClearView\Element\Mosaic())  // Render Mosaic glyph
                ->close();          // close the facet
        } else { // No such command!
            // Page-field fallback: lookup $command as a field on the ProcessWire Page
            $pageField = Mosaic::getVar($command, "Page");
            if ($pageField !== null) {
                echo $pageField;
            } else {
                $this->doesNotUnderstand();
            }
        }
        // Send buffered variable and script updates
        ($crystal = Mosaic::getVar('ClearView', 'ClearView')) ? $crystal->dumpOOBdata() : null;
    }

    /**
     * Handles unknown commands.
     */
    public function doesNotUnderstand($name = null): void
    {
        $name = $name ?? $this->command;
        throw new Exception("I don't know how to '$name', from {{Input::url}}");
    }

    /**
     * Catch unknown method calls for consistency
     */
    public function __call($name, $arguments): void
    {
        $redir = Mosaic::getVar($name, "Page");
        if (isset($redir)) {
            echo $redir;
        } else {
            echo $this->doesNotUnderstand($name);
        }
    }
}
