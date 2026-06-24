<?php
namespace ClearView;
use ClearView\Crystal;

/**
 * Manages static and runtime configuration for the ClearView framework.
 *
 * TODO: Allow ClearView to create a site-settings page in the admin-UI.
 * This could be automatic, or via a method you could invoke via a CLI.
 * On start-up, if this page exists, return it wrapped in a ClearView Page
 * object and return it as the Config Crystal.  The page API does the rest.
 *
 * Config extends Crystal to provide both static configuration constants and a runtime configuration array
 * accessible via templates (e.g., `{{Config::layername_clearview}}`). It defines constants for key identifiers
 * (e.g., layer names, form IDs, CSS classes) used during PHP initialization and stores corresponding values in
 * a static `$config` array for runtime access. As a Crystal, Config integrates with Mosaic for template variable
 * resolution, allowing configuration values to be embedded directly in HTML output. The class supports
 * debugging modes (`fail_mode`) and trace flags (`tracemode`) for low-level diagnostics, and provides defaults
 * for dynamic settings like haptic feedback strength.
 *
 * Key features:
 * - Defines constants prefixed with `_CV_` for static initialization (e.g., `LAYERNAME_CLEARVIEW`, `ID_MAIN_FORM`).
 * - Maintains a `$config` array for runtime access to configuration values.
 * - Supports template access via `{{Config::key}}` through Crystal’s integration with Mosaic.
 * - Configures debugging behavior with `fail_mode` (HTML comments vs. console output) and `tracemode` flags.
 * - Provides defaults for UI components (e.g., form close delay, CSS classes) and system settings (e.g., stack limit).
 *
 * Usage:
 * - Access constants directly in PHP (e.g., `Config::LAYERNAME_CLEARVIEW`).
 * - Retrieve runtime values via `Config::getVar('layername_clearview')` or templates (`{{Config::layername_clearview}}`).
 * - Configure `fail_mode` for debug output style and `tracemode` for trace verbosity.
 *
 * Dependencies: Requires ProcessWire for context, Crystal for template integration, and Mosaic for variable
 * resolution. Assumes `jsonmangler` for data handling in related components.
 *
 * @see \ClearView\Crystal
 * @see \ClearView\Mosaic
 * @see \ClearView\ClearView
 */

class Config extends Crystal
{
    /**
     * Prefix for ClearView IDs and some other values.
     * Used to namespace ClearView-specific identifiers to avoid collisions with other systems.
     */
    public const CLEARVIEW_PREFIX = '_CV_';

    /**
     * Prefix for ProcessWire titles.
     * Used to namespace ClearView-specific identifiers to avoid collisions with other systems.
     */
    public const TITLE_PREFIX = 'ClearView';

    /** @var string Path to remote ProcessWire installation for Hanna Code imports */

    /**
     * The name of the field holding a reference to a processwire object
     */
    public const PAGE_PWOBJECT = "__pwObject";

    /**
     * The name of the field holding the Pane's shared processwire page
     */
    public const PAGE_PWPANE = "__pwPane";

    /**
     * Layer name for the main ClearView framework.
     * Identifies the primary layer for ClearView rendering operations.
     */
    public const LAYERNAME_CLEARVIEW = self::CLEARVIEW_PREFIX . 'ClearView';

    /**
     * Layer name for modal alerts.
     * Identifies the layer for modal dialogs or alerts, prefixed with `CLEARVIEW_PREFIX`.
     */
    public const LAYERNAME_MODAL = self::CLEARVIEW_PREFIX . 'alerts';

    /**
     * Layer name for script storage.
     * Identifies the layer for storing inline or external scripts, prefixed with `CLEARVIEW_PREFIX`.
     */
    public const LAYERNAME_SCRIPTS = self::CLEARVIEW_PREFIX . 'scripts';

    /**
     * Layer suffix for the pane container element.
     * Appended to the pane name to form the container ID (e.g., 'Default-container').
     */
    public const LAYER_SUFFIX_CONTAINER = '-container';

    /**
     * Layer suffix for the main pane element.
     * Appended to the pane name to form the pane ID (e.g., 'Default-pane').
     */
    public const LAYER_SUFFIX_PANE = '-pane';

    /**
     * Layer suffix for the Mosaic variable storage layer.
     * Appended to the pane name to form the Mosaic ID (e.g., 'Default-mos').
     */
    public const LAYER_SUFFIX_MOSAIC = '-mos';

    /**
     * Layer suffix for the pane-scoped debug output layer.
     * Appended to the pane name to form the debug ID (e.g., 'Default-debug').
     */
    public const LAYER_SUFFIX_DEBUG = '-debug';

    /**
     * Layer suffix for the pane-scoped debug console dialog.
     * Appended to the pane name to form the debug console ID (e.g., 'Default-debugconsole').
     */
    public const LAYER_SUFFIX_DEBUGCONSOLE = '-debugconsole';

    /**
     * CSS class for the debug console dialog element.
     */
    public const CLASS_DEBUGCONSOLE = 'debugconsole';

    /**
     * Enable the per-pane debug console dialog.
     * When true, dumpOOBdata() emits a <dialog class="debugconsole"> with buffered messages.
     */
    public const DEBUG_CONSOLE = false;

    /**
     * Layer suffix for the pane-scoped script layer.
     * Appended to the pane name to form the script ID (e.g., 'Default-script').
     */
    public const LAYER_SUFFIX_SCRIPT = '-script';

    /**
     * ID for the layer stack container.
     * Used as the target for dynamically added panes and dialogs
     * appended to the end of the document body.
     */
    public const LAYERSTACKID = self::CLEARVIEW_PREFIX . 'layerstack';

    /**
     * ID for the main form element.
     * Unique identifier for the primary form, prefixed with `CLEARVIEW_PREFIX`.
     */
    public const ID_MAIN_FORM = self::CLEARVIEW_PREFIX . 'mainform';

    /**
     * Sanitizer list ending in '\' to use on page saves
     */
    public const SANI_PAGE_SAVE = 'noScripts\\noBraces\\';

    /**
     * ID for the Default main content pane.
     * Unique identifier for the primary form, prefixed with `CLEARVIEW_PREFIX`.
     */
    public const ID_MAIN_BODY = self::CLEARVIEW_PREFIX . 'mainbody';

    /**
     * ID for the form tab body.
     * Unique identifier for the tab body container, prefixed with `CLEARVIEW_PREFIX`.
     */
    public const ID_FORM_TABBODY = self::CLEARVIEW_PREFIX . 'tabbody';

    /**
     * ID for the form tab bar.
     * Unique identifier for the tab bar container, prefixed with `CLEARVIEW_PREFIX`.
     */
    public const ID_FORM_TABBAR = self::CLEARVIEW_PREFIX . 'tabbar';

    /**
     * Prefix for individual tab IDs.
     * Prefix for generating tab-specific IDs, prefixed with `CLEARVIEW_PREFIX`.
     */
    public const ID_FORM_TABPREFIX = self::CLEARVIEW_PREFIX . 'tab_';

    /**
     * Identifier for the system session.
     * Unique identifier for session storage, prefixed with `CLEARVIEW_PREFIX`.
     */
    public const SYSTEM_SESSION = self::CLEARVIEW_PREFIX . 'system';

    /**
     * CSS class for close buttons.
     * Class name for styling close buttons in forms or modals.
     */
    public const CLASS_CLOSE_BUTTON = 'close';

    /**
     * CSS class for tab bars.
     * Class name for styling tab bar containers.
     */
    public const CLASS_TABBAR = 'tabbar';

    /**
     * CSS class for tab bodies.
     * Class name for styling tab body containers.
     */
    public const CLASS_TABBODY = 'tabbody';

    /**
     * CSS class for variable storage elements.
     * Class name for elements storing Mosaic variables (e.g., hidden inputs).
     */
    public const CLASS_MOSAIC= 'mosaic';

    /**
     * ID for close buttons.
     * Unique identifier for close buttons in forms or modals.
     */
    public const ID_CLOSE_BUTTON = 'closebutton';

    /**
     * Default form close animation delay (in milliseconds).
     * Specifies the delay for form close animations, used in UI transitions.
     */
    public const FORM_CLOSE_DELAY = '850';

    /**
     * Debug mode for failure output.
     *
     * When true, debug information is output as HTML comments for low-level diagnostics. When false, debug
     * output is sent to the browser console with colorized formatting.
     */
    public const FAIL_MODE = false;

    /**
     * Maximum stack depth to prevent recursion.
     * Limits the tag stack size to prevent infinite recursion in rendering operations.
     */
    public const STACK_LIMIT = 127;

    /**
     * Ordered list of active module directories.
     * Modules are tried in order for glyph/views/pane/crystal lookups.
     * 'site' is tried first (user overrides), 'vendor' last (pristine
     * ClearView code).  PaneAttr::modules from the <pane modules="...">
     * attribute is prepended at runtime by Framework::Modules().
     *
     * @see ClearView\Framework::Modules()
     * @var array<int, string>
     */
    public const MODULES_LIST = ['site', 'vendor'];

    /**
     * Lost the list of these - check Exception.php
     * You can change "Config::tracemode" in your PHP code to debug specific sections of code.
     * You can also change this in a Facet for auto-restore on tag-close.
     */
    public const TRACEMODE = ['ALL'];

    /**
     * Runtime configuration array.
     *
     * Stores configuration key-value pairs for runtime access via `getVar()` or templates. Keys use
     * underscores for compatibility with PHP constants. Includes all constant values plus dynamic settings
     * like `user_haptic-strength` and `tracemode`.
     *
     * @var array<string, mixed>
     */
    public static array $config = [
        'modules_list' => self::MODULES_LIST,
        'layername_clearview' => self::LAYERNAME_CLEARVIEW,
        'title_prefix' => self::TITLE_PREFIX,
        'page_pwobject' => self::PAGE_PWOBJECT,
        'page_pwPane' => self::PAGE_PWPANE,
        'layername_modal' => self::LAYERNAME_MODAL,
        'layername_scripts' => self::LAYERNAME_SCRIPTS,
        'layer_suffix_container' => self::LAYER_SUFFIX_CONTAINER,
        'layer_suffix_pane' => self::LAYER_SUFFIX_PANE,
        'layer_suffix_mosaic' => self::LAYER_SUFFIX_MOSAIC,
        'layer_suffix_debug' => self::LAYER_SUFFIX_DEBUG,
        'layer_suffix_debugconsole' => self::LAYER_SUFFIX_DEBUGCONSOLE,
        'layer_suffix_script' => self::LAYER_SUFFIX_SCRIPT,
        'layerstackid' => self::LAYERSTACKID,
        'sani_page_save' => self::SANI_PAGE_SAVE,
        'id_main_form' => self::ID_MAIN_FORM,
        'id_main_body' => self::ID_MAIN_BODY,
        'id_form_tabbody' => self::ID_FORM_TABBODY,
        'id_form_tabbar' => self::ID_FORM_TABBAR,
        'id_form_tabprefix' => self::ID_FORM_TABPREFIX,
        'system_session' => self::SYSTEM_SESSION,
        'class_close_button' => self::CLASS_CLOSE_BUTTON,
        'class_tabbar' => self::CLASS_TABBAR,
        'class_tabbody' => self::CLASS_TABBODY,
        'class_mosaic' => self::CLASS_MOSAIC,
        'class_debugconsole' => self::CLASS_DEBUGCONSOLE,
        'id_close_button' => self::ID_CLOSE_BUTTON,
        'user_haptic-strength' => 1, // Default multiplier for haptic feedback
        'form_close_delay' => self::FORM_CLOSE_DELAY,
        'fail_mode' => self::FAIL_MODE,
        'debug_console' => self::DEBUG_CONSOLE,
        'stack_limit' => self::STACK_LIMIT,
        'tracemode' => self::TRACEMODE, // Debug trace flags (e.g., ['ALL'], ['FACET'])
    ];

    /**
     * Retrieves a configuration variable.
     *
     * Used to access runtime configuration values from the `$config` array. Supports direct PHP access and
     * template rendering via `{{Config::key}}`. Returns null if the key is not found.
     *
     * Why: Provides a unified interface for accessing configuration values at runtime.
     *
     * Examples:
     * ```php
     * $layer = Config::getVar('layername_clearview'); // Returns 'ClearView'
     * $layer = Mosaic getVar("Config::layername_clearview");  // As above
     * $layer = Config::LAYERNAME_CLEARVIEW // Static init compatible
     * ```
     * Template usage:
     * ```html
     * <div {{class=Config::class_tabbar}}>...</div> <!-- Outputs class="tabbar" -->
     * ```
     *
     * @param string $varname The configuration key (using underscores, e.g., `layername_clearview`).
     * @return mixed The configuration value or null if not found.
     */
    public function getVar($varname = null)
    {
        if (!isset($varname)) {
            return $this;
        }
        return static::$config[$varname] ?? null;
    }
}
