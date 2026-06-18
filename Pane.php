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
    /** @var Mosaic The Mosaic instance for this pane. */
    public Mosaic $mosaic;

    /**
     * Returns the default method name for a given request method.
     */
    public static function defaultMethod(?string $method = null): string
    {
        $map = ['POST' => 'post', 'CLI' => 'open', 'GET' => 'html', 'PUT' => 'put', 'DELETE' => 'delete'];
        return $map[$method] ?? 'html';
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
     * Fills the Mosaic with an array of values.
     */
    public function fill(array $values): void
    {
        $this->mosaic->fill($values);
    }

    // ── ArrayAccess ──────────────────────────────────────────────

    public function offsetGet($key): mixed
    {
        return $this->mosaic->getVar($key);
    }

    public function offsetSet($key, $value): void
    {
        $this->mosaic->setVar($key, $value);
    }

    public function offsetExists($key): bool
    {
        return $this->mosaic->getVar($key) !== null;
    }

    public function offsetUnset($key): void
    {
        $this->mosaic->delVar($key);
    }

    // ── Lifecycle methods ────────────────────────────────────────

    /**
     * Initializes the ClearView framework from the request.
     *
     * @param string $template The ProcessWire page template name.
     * @return void
     * @throws Exception on errors.
     */
    public function __construct(string $template)
    {
        $this->mosaic = $mosaic = new Mosaic();
        Crystal::loadAll();

        $panename = 'Default';
        $inlayname = 'ClearView';
        $command = '';

        if ($template == 'Default') {
            $panename = $template;
            $inlayname = 'Pane';
            $PaneClass = '\\ClearView\\Main';
        } else {
            $segments = array_values(array_filter(explode('/', trim(Page::page()->url, '/')), 'strlen'));
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

        /**
         * Consider the following variables to be off-limits to new code
         * They are provided for emergency use as transition, especially the bottom few!
         */
        ClearView::Mosaic($mosaic);
        ClearView::panename($panename);
        ClearView::inlayname($inlayname);
        ClearView::method($command);
        ClearView::paneobj($this);
        
        // Load and resolve the body Element (after crystals are wired)
        $this['Pane::body'] = PaneCrystal::load($panename, $inlayname);
        
        // We probably want to kill the direct ProcessWire attachment some day
        Exception::outheader($template, \ProcessWire\config()->debug ? Config::TRACEMODE : null);            
        
        return new $PaneClass();
    }

    /**
     * Moves loadMosaic into Pane as a non-static method.
     *
     * The Pane owns its Mosaic; loadMosaic delegates accordingly.
     */
    public function loadMosaic($input): void
    {
        $this->mosaic->loadMosaic($input);
    }

    /**
     * Default full-page render. Opens the container tag, renders element
     * contents, outputs the body template, and closes. Fires paneopen event.
     */
    public function open(): void
    {
        self::html();
    }

    /**
     * Default HTML method. Renders Pane::body. Detects inlay changes
     * by comparing against $this['Shared::prevInlay'] and fires inlaychange.
     * @param string|null $template Optional template to render instead of Pane::body.
     */
    public function html(?string $template = null): void
    {
        $currentInlay = $this['ClearView::inlayname'];
        if ($this['Shared::prevInlay'] !== null && $this['Shared::prevInlay'] !== $currentInlay) {
            $this->triggerevent('inlaychange', ['inlay' => $currentInlay]);
        }
        $this['Shared::prevInlay'] = $currentInlay;

        (new Facet($this['Pane::body']))
            ->html()
            ->close();
    }
    /**
     * Renders the launcher element (e.g., a button that opens the pane).
     */
    public function launcher(): void
    {
        (new Facet($this['Pane::launcher']))
            ->html()
            ->close();
    }

    /**
     * Triggers closepane event with optional delay.
     * @param mixed $delay Optional delay value for the event payload.
     */
    public function close($delay = null): void
    {
        $this->triggerevent('closepane', [ 'delay' => $delay]);
    }

    /**
     * Redirects to a URL via HX-Location JSON payload.
     * @param string|null $url The URL.
     */
    public function redirect($url = null): void
    {
        $url = $url ?? $this['Page::url'];
        header("HX-Location: " . json_encode(["path" => $url]));
    }

    /**
     * Reloads the page.
     */
    public function reloadPage(): void
    {
        $this->redirect();
    }

    /**
     * Triggers an htmx event. Pane name is always included in the JSON payload.
     * @param string $event The event to trigger.
     * @param mixed $params Optional event parameters.
     */
    public function triggerevent(string $event, $params = null): self
    {
    	$this->sendHtmxHeader('HX-Trigger', $event, $params);
        return $this;
    }
    
    /**
     * Sends a special header in the server response.
     * @param string $header The header to write
     * @param string $data The primary data point
     * @param string $params Additional parameters
     */
    public function sendHtmxHeader(string $header, $event, $params): self
    {
        Exception::debug('EVENT', "Triggering {$event}");
        if (isset($params) && is_array($params)) {
            // Assumes $events values are already arrays
            $events = array_map(fn($d) => array_merge($d, ['Pane' => $this['ClearView::panename']]), $params);
            header("HX-Trigger: " . json_encode($events));   
        } else {
            header("HX-Trigger: {$event}");
        }
        return $this;
    }

    /**
     * Sets the HX-Retarget header to redirect an HTMX response to a
     * different target element than the one that triggered the request.
     *
     * @param string $target CSS selector for the new swap target w/#
     */
    public function retargetResult(string $target, $params=null): void
    {
    	$this->sendHtmxHeader('HX-Retarget', $target, $params);
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
     */
    public function handleCommand(): void
    {
        $command = $this['ClearView::method'] ?: self::defaultMethod();

        // Slurp up variables first
        $this->loadMosaic($this['Input::all']);

        if (method_exists($this, $command)) {
            $reflectionMethod = new \ReflectionMethod($this, $command);

            // PaneKey security
            $providedToken = $this['Pane::Key'];
            $expectedToken = $this['Session::PaneKey'];
            if ($providedToken !== $expectedToken) {
                throw new Exception('Invalid PaneKey: $providedToken vs $expectedToken');
            }
            // No private methods, even if the panekey matches
            if ($reflectionMethod->isPrivate() || str_starts_with($command, '_')) {
                throw new \Exception('Access denied: Cannot call private or underscored methods.');
            }

            // Execute the method if all checks pass.
            Exception::debug('EVENT', "Executing {$command} from {{uppercase\\Input::requestMethod}} {{Input::url}}");
            (new Facet($this))
                ->forward($command)
                ->create(['glyph' => 'mosaic'])
                ->close();
        } else {
            // Page-field fallback: lookup $command as a field on the ProcessWire Page
            $pageField = $this["Page::$command"];
            if ($pageField !== null) {
                (new Facet($pageField))
                	->html()
                	->close();
            } else {
                $this->doesNotUnderstand($command);
            }
        }
        // Send buffered variable and script updates
        $this['ClearView']->dumpOOBdata();
    }

    /**
     * Handles unknown commands.
     */
    public function doesNotUnderstand($name = null): void
    {
        $name = $name ?? ClearView::method();
        throw new Exception("I don't know how to '$name', from {{Input::url}}");
    }

    /**
     * Catch unknown method calls for consistency
     */
    public function __call($name, $arguments): void
    {
        $redir = $this["ClearView::pagename"];
        if (isset($redir)) {
            echo $redir;
        } else {
            $this->doesNotUnderstand($name);
        }
    }
}
