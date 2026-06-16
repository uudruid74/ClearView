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
     * Add the default render() for <pane> elements here, including the Session Token creation
     * A <pane> render just outputs a div with the same class and other attributes as the pane
     * but with an on-load parameter that loads the pane into the div.  The <pane> element
     * is for embedded panes, <dialog> for full screen dynamic panes.  The pane itself just
     * inserts into the given container.
     *
     * Change all ids to #name-container, #name-pane for the main element, #name-mos for the mosaic,
     * #name-debug for the debug layer, #name-script for the javascript layer.
     *
     * In the new scheme, Panes are elements, so the Form.php pane should have a render() that
     * outputs the <form> tag like an element.  The open() call creates a <dialog> element and
     * then outputs that element OOB to the end of the layerstack.  I think
     *   public function open(): void
     *   {
     *      (new Facet($this))->newLayer('dialog');
     *   }
     * should be implemented.  It would just output the dialog, render $this to a string,
     * output the string, then close the dialog, all OOB.  We'll add more types later.
     * This should also output the debug layer.  We'll put the javascript container after
     * the mosaic.  Outputting the mosaic outputs the javascript container.
     *
     * Facet builds the OOB string directly, like Mosaic does.
     *
     */

    /**
     * Override this in your subclasses
     */
    public function open(): void
    {
        (new Facet($this))
            ->html()
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
     * Triggers an htmx event.
     * @param string $event The event to trigger.
     * @param mixed $params Optional event parameters.
     */
    public static function triggerevent($event, $params = null): void
    {
        Exception::debug('EVENT', "Triggering {$event}");
        if (isset($params)) {
            header('HX-Trigger: ' .json_encode([
                $event => $params
            ]));
        } else {
            header("HX-Trigger: {$event}");
        }
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
     * @param array $urlsegments The URL segments.
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
            Exception::debug('EVENT', "Executing {$command} from {{uppercase\Input::requestMethod}} {{Input::url}}");
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
