<?php
namespace ClearView;
use ClearView\ClearView;
use ClearView\Facet;
use ClearView\Element;
use ClearView\Mosaic;
use ClearView\Exception;
use ProcessWire;

class Pane extends Element
{
    /**
     * Initializes the pane.
     */
    public function __construct($panename,$inlayname='Default')
    {
        ClearView::CurrentPane($this);
        parent::__construct([
            'id'        => $panename . 'Pane',
            'inlay'     => $inlayname,
            'name'      => $panename
        ]);
    }

    /**
     * Default full-page render. Opens the container tag, renders element
     * contents, outputs the body template, and closes. Fires paneopen event.
     */
    public function open(): void
    {
        (new Facet($this))
            ->open("{{Pane::open}}")
            ->render()
            ->html("{{Pane::body^^View::" . $this->name . "}}")
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
        (new Facet($this))
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
        (new Facet($this))
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
        $paneName = $this->getField('name') ?? 'unknown';
        if (isset($params)) {
            $payload = is_array($params)
                ? array_merge(['pane' => $paneName], $params)
                : ['pane' => $paneName, 'value' => $params];
            header('HX-Trigger: ' . json_encode([$event => $payload]));
        } else {
            header('HX-Trigger: ' . json_encode([$event => ['pane' => $paneName]]));
        }
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
            $command = ClearView::defaultMethod();
        }

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
            (new Facet($this))
                ->forward($command) // the command to be executed
//                ->dumpEverything()  // Dump entire Mosaic
                ->dumpVars()        // Update the Mosaic
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
        ClearView::dumpOOBdata();
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
        $redir = Mosaic::getVar($name,"Page");
        if (isset($redir)) {
            echo $redir;
        } else {
            echo $this->doesNotUnderstand($name);
        }
    }
}
