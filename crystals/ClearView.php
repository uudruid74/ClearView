<?php

namespace ClearView;

use ClearView\Crystal;
use ClearView\Mosaic;
use ClearView\Facet;
use ClearView\Exception;
use ClearView\Config;
use ClearView\Pane;
use ProcessWire;

/**
 * Crystal that holds request-scoped output behavior and static accessors.
 *
 * Holds the OOB/script/debug buffers. The 4 static accessors
 * (panename, inlayname, method, paneobj) get/set their values on
 * the first ClearView crystal instance — pass a value to set, omit
 * to read. Always returns the current value.
 *
 * Instantiated by Crystal::loadAll().
 *
 * @see \ClearView\Crystal
 * @see \ClearView\Mosaic
 * @see \ClearView\Pane
 */
class ClearView extends Crystal
{
    /** @var ClearView|null First instance — anchor for static accessors */
    private static ?ClearView $instance = null;

    /** @var array Buffer for JavaScript code sent to the client */
    private array $scripts = [];

    /** @var string Non-blocking JS code */
    private string $async = '';

    /** @var string Out-of-band HTML buffer */
    private string $oobBuffer = '';

    /** @var string Pane-scoped debug layer */
    private string $debugBuffer = '';

    // ── Lifecycle ─────────────────────────────────────────────

    public function __construct($pwObject = null, $name = null, $inlay = 'ClearView',$mos)
    {
        parent::__construct($pwObject, $name, $inlay,$mos);
        self::$instance ??= $this;
        $this->scripts     = [];
        $this->async       = '';
        $this->oobBuffer   = '';
        $this->debugBuffer = '';
    }

    // ── Static accessors (the 4 "stop-gap" values) ────────────

    /**
     * Get/set panename. Pass value to set; omit to read.
     * @return string
     * @deprecated Use Input::panename via Mosaic instead
     */
    public static function panename($value = null): string
    {
        if ($value !== null) {
            self::$instance->data['panename'] = $value;
        }
        return self::$instance->data['panename'] ?? 'Default';
    }

    /**
     * Get/set inlayname. Pass value to set; omit to read.
     * @return string
     */
    public static function inlayname($value = null): string
    {
        if ($value !== null) {
            self::$instance->data['inlayname'] = $value;
        }
        return self::$instance->data['inlayname'] ?? 'Pane';
    }

    /**
     * Get/set command (method). Pass value to set; omit to read.
     * @return string
     */
    public static function method($value = null): string
    {
        if ($value !== null) {
            self::$instance->data['command'] = $value;
        }
        return self::$instance->data['command'] ?? '';
    }

    /**
     * Get/set the current Pane object. Pass value to set; omit to read.
     * @return Pane|null
     */
    public static function paneobj($value = null): ?Pane
    {
        if ($value !== null) {
            self::$instance->data['paneobj'] = $value;
        }
        return self::$instance->data['paneobj'] ?? null;
    }

    /**
     * Set the current Mosaic object.
     * @return Mosaic|null
     */
    public static function Mosaic(): ?Mosaic
    {
        return self::$instance->mosaic ?? null;
    }

    // ── Output helpers ────────────────────────────────────────

    /** Adds JavaScript to be executed on the client. */
    public static function javascript(string $string): void
    {
        self::$instance->scripts[] = Facet::_($string);
    }

    /** Sends async JavaScript (IIFE-wrapped). */
    public static function asyncjs(string $string): void
    {
        self::$instance->async .= "(async () => { " . Facet::_($string) . " })();\n";
    }

    /** Sends an out-of-band (OOB) element. */
    public static function sendOOB($elem): void
    {
        self::$instance->oobBuffer .= Facet::_($elem) . "\n";
    }

    /** Adds a message to the pane-scoped debug layer buffer. */
    public function debugLayer(string $msg): void
    {
        if (!isset($this->debugBuffer)) {
            return;
        }
        $this->debugBuffer .= "<div>{$msg}</div>\n";
    }

    // ── Final dump ────────────────────────────────────────────

    /**
     * Dumps OOB data, scripts, and debug buffer.
     */
    public function dumpOOBdata(): void
    {
        echo $this->oobBuffer;

        $panename = self::panename();
        $scripts  = $this->async . "\n" . implode("\n", $this->scripts);
        $this->writeTo(
            $panename . Config::LAYER_SUFFIX_SCRIPT,
            "<script hx-disable>{$scripts}</script>\n"
        );

        if ($this->debugBuffer) {
            $this->appendTo(
                $panename . Config::LAYER_SUFFIX_DEBUG,
                $this->debugBuffer
            );

            // Per-Pane debug flags stored in the Shared inlay namespace
            $debugflags = $this['Shared::debugflags'] ?? null;
            if (is_array($debugflags) && (
                in_array('DEBUG_CONSOLE', $debugflags, true) ||
                in_array('ALL', $debugflags, true)
            )) {
                echo "<dialog class=\"" . Config::CLASS_DEBUGCONSOLE
                    . "\" id=\"{$panename}" . Config::LAYER_SUFFIX_DEBUGCONSOLE
                    . "\" hx-swap-oob=\"beforeend:#" . Config::LAYERSTACKID . "\" open>"
                    . $this->debugBuffer . "</dialog>\n";
            }
        }

        $this->oobBuffer   = '';
        $this->scripts     = [];
        $this->async       = '';
        $this->debugBuffer = '';
    }

    // ── DOM helpers ───────────────────────────────────────────

    private function writeTo(string $id, string $text): void
    {
        echo "<div id=\"{$id}\" hx-swap-oob=\"true\">{$text}</div>\n";
    }

    private function appendTo(string $id, string $elem): void
    {
        echo "<div id=\"{$id}\" hx-swap-oob=\"beforeend\">{$elem}</div>\n";
    }

    // ── Utilities ─────────────────────────────────────────────

    /** Checks if the request is made via HTMX. */
    public static function is_htmx_request(): bool
    {
        return isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';
    }

    /** Checks if the request is from a boosted link. */
    public static function is_htmx_boosted(): bool
    {
        return isset($_SERVER['HTTP_HX_BOOSTED']) && $_SERVER['HTTP_HX_BOOSTED'] === 'true';
    }

    /** Preloads an image via Link header. */
    public static function preloadImage($src): void
    {
        Exception::debug('EVENT', "Preloading {$src}");
        header("Link: rel=preload; <{$src}>; as=image");
    }

    /** Gets rendered Hanna code. */
    public static function hanna($hannacode): string
    {
        return ProcessWire\modules()->get('TextformatterHannaCode')->render(Facet::_($hannacode));
    }

    // ── Prism loader ───────────────────────────────────────────

    /**
     * Loads and instantiates a Prism from the module stack.
     *
     * Invoked as ClearView::PrismName([...]) — searches
     * modules/<module>/prisms/<PrismName>.php via the module stack
     * (same pattern as glyphs, inlays, and views).  Returns whatever
     * the Prism's constructor returns: null for display phase (|| return),
     * or a string choice for the switch/case re-entry.
     *
     * @throws Exception if the prism file is not found.
     */
    public static function __callStatic(string $name, array $args): mixed
    {
        foreach (Page::buildModuleStack() as $module) {
            $path = __DIR__ . "/../modules/{$module}/prisms/{$name}.php";
            if (file_exists($path)) {
                require_once $path;
                $class = "\\ClearView\\Prism\\{$name}";
                return new $class(...$args);
            }
        }
        throw new Exception("Prism '{$name}' not found in any module");
    }
}
