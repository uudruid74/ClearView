<?php

namespace ClearView;

use ClearView\Crystal;
use ClearView\Mosaic;
use ClearView\Facet;
use ClearView\Exception;
use ClearView\Config;
use ClearView\Shared;
use ProcessWire;

/**
 * Crystal that holds request-scoped output behavior.
 *
 * Owns the OOB HTML buffer, script/async JS buffers, pane-scoped debug
 * output, and HTMX detection helpers. Instantiated by Crystal::loadAll()
 * and accessed via $this['ClearView'] on Pane/Shard instances.
 *
 * ClearView is no longer a singleton controller. Routing, pane/inlay
 * loading, view loading, and module-stack walking moved to Pane, Inlay,
 * Element, and Page.
 *
 * @see \ClearView\Crystal
 * @see \ClearView\Mosaic
 * @see \ClearView\Pane
 */
class ClearView extends Crystal
{
    /** @var array Buffer for JavaScript code to be sent to the client */
    protected array $scripts = [];

    /** @var string Non-blocking JS code is sent first */
    protected string $async = '';

    /** @var string Buffer for out-of-band (OOB) HTML elements */
    protected string $oobBuffer = '';

    /** @var string Buffer for pane-scoped debug messages */
    protected string $debugBuffer = '';

    /** @var array Buffer for HX-Trigger events (emitted as headers before body output) */
    protected array $hxEvents = [];

    /**
     * Initializes buffers.
     */
    public function __construct($pwObject = null, $name = null, $inlay = 'ClearView')
    {
        parent::__construct($pwObject, $name, $inlay);
        $this->scripts = [];
        $this->async = '';
        $this->oobBuffer = '';
        $this->debugBuffer = '';
        $this->hxEvents = [];
    }

    /**
     * Adds JavaScript to be executed on the client.
     */
    public function javascript(string $string): void
    {
        $this->scripts[] = Facet::_($string);
    }

    /**
     * Sends asynchronous JavaScript to be executed on the client.
     */
    public function asyncjs(string $string): void
    {
        $this->async .= "(async () => { " . Facet::_($string) . " })();\n";
    }

    /**
     * Sends an out-of-band (OOB) element.
     */
    public function sendOOB($elem): void
    {
        $this->oobBuffer .= Facet::_($elem) . "\n";
    }

    /**
     * Appends a message to the pane-scoped debug layer buffer.
     */
    public function debugLayer(string $msg): void
    {
        if (!isset($this->debugBuffer)) {
            return;
        }
        $this->debugBuffer .= "<div>{$msg}</div>\n";
    }

    /**
     * Buffers an HX-Trigger header to be emitted before body output.
     *
     * Calls to triggerevent() are deferred here; dumpOOBdata()
     * sends the headers first, then outputs the body.
     *
     * @param string $header Full HX-Trigger header line (e.g. 'HX-Trigger: {...}').
     */
    public function bufferTriggerEvent(string $header): void
    {
        $this->hxEvents[] = $header;
    }

    /**
     * Dumps out-of-band data, scripts, and debug buffer.
     */
    public function dumpOOBdata(): void
    {
        // Emit buffered HX-Trigger headers before any body output.
        foreach ($this->hxEvents as $headerLine) {
            header($headerLine);
        }
        $this->hxEvents = [];

        $panename = Mosaic::getVar("Pane::name") ?? 'Default';
        echo $this->oobBuffer;
        $scripts = $this->async . "\n" . implode("\n", $this->scripts);
        $this->writeTo("{$panename}" . Config::LAYER_SUFFIX_SCRIPT, "<script hx-disable>{$scripts}</script>\n");
        if ($this->debugBuffer) {
            $this->appendTo("{$panename}" . Config::LAYER_SUFFIX_DEBUG, $this->debugBuffer);
            if (Shared::isDebugConsole()) {
                echo "<dialog class=\"" . Config::CLASS_DEBUGCONSOLE
                    . "\" id=\"{$panename}" . Config::LAYER_SUFFIX_DEBUGCONSOLE
                    . "\" hx-swap-oob=\"true\" open>"
                    . $this->debugBuffer . "</dialog>\n";
            }
        }
        $this->oobBuffer = '';
        $this->scripts = [];
        $this->async = '';
        $this->debugBuffer = '';
    }

    /**
     * Writes HTML to an element via OOB swap.
     */
    private function writeTo(string $id, string $text): void
    {
        echo "<div id=\"{$id}\" hx-swap-oob=\"true\">{$text}</div>\n";
    }

    /**
     * Appends HTML to an element via OOB swap.
     */
    private function appendTo(string $id, string $elem): void
    {
        echo "<div id=\"{$id}\" hx-swap-oob=\"beforeend\">{$elem}</div>\n";
    }

    /**
     * Preloads an image.
     */
    public function preloadImage($src): void
    {
        Exception::debug('EVENT', "Preloading {$src}");
        header("Link: rel=preload; <{$src}>; as=image");
    }

    /**
     * Gets rendered Hanna code.
     */
    public static function hanna($hannacode): string
    {
        return ProcessWire\modules()->get('TextformatterHannaCode')->render(Facet::_($hannacode));
    }
}
