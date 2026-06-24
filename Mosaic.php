<?php

namespace ClearView;

use ClearView\Facet;
use ClearView\Exception;
use ClearView\Sanitizer;
use ClearView\QueryParser;
use ProcessWire;

/**
 * Request-scoped store for Shards — fully static singleton.
 * Shards are indexed by inlay-id, deflated into hidden inputs on the client,
 * and re-inflated on every request. The Mosaic is the first thing created
 * during bootstrap, fully initialized before any Pane acts.
 * All methods are static and call through Mosaic::$instance.
 * The instance is set by Mosaic::load() or the constructor.
 * @see \ClearView\Shard
 * @see \ClearView\Facet
 * @see \ClearView\Crystal
 * @see \ClearView\Pane
 */
class Mosaic
{
    /** @var Mosaic|null The current request/test-scope Mosaic instance */
    private static ?Mosaic $instance = null;

    /** @var array Main storage array for mosaic data, indexed by shard addresses (inlay-id) */
    private array $mosaic = [];

    /** @var array List of shard addresses that have changed or been added for update tracking */
    private array $checkList = [];

    /** @var bool Flag to prevent change tracking until after loading the Mosaic */
    private bool $trackChanges = false;

    /** @var Facet|null Current request-scope Facet singleton */
    private ?Facet $facetsingleton = null;

    /**
     * Returns the current Mosaic instance.
     * @return Mosaic|null
     */
    public static function instance(): ?Mosaic
    {
        return self::$instance;
    }

    /**
     * Static factory — creates and fully initializes a Mosaic.
     * This is the single entry point for Mosaic creation. It handles crystal
     * loading, input data inflation, CLI data injection, and snapshot
     * restoration in the correct order. Once complete, Mosaic::instance()
     * returns the fully-initialized Mosaic.
     * @param array $options {
     *     loadCrystals: true|false     — load all Crystal subclasses (default: true)
     *     loadInputData: true          — inflate shards from GET/POST data
     *     loadCliData: array           — key-value pairs to inject directly
     *     loadSnapShot: "name"         — restore from views/<name>.php snapshot
     * }
     * @return Mosaic The fully initialized instance (also Mosaic::instance())
     * @throws Exception on invalid option combinations
     */
    public static function load(array $options = []): Mosaic
    {
        $loadCrystals  = $options['loadCrystals'] ?? true;
        $loadInputData = $options['loadInputData'] ?? false;
        $loadCliData   = $options['loadCliData'] ?? [];
        $loadSnapShot  = $options['loadSnapShot'] ?? null;

        // ── Validation ──────────────────────────────────────────
        if ($loadInputData && !$loadCrystals) {
            throw new Exception("loadInputData requires loadCrystals");
        }
        if ($loadSnapShot && !$loadCrystals) {
            throw new Exception("loadSnapShot requires loadCrystals");
        }

        // ── Create and register ─────────────────────────────────
        $mosaic = new Mosaic();
        self::$instance = $mosaic;

        // ── Load crystals ───────────────────────────────────────
        if ($loadCrystals) {
            Crystal::loadAll($mosaic);
        }

        // ── Create Facet singleton ──────────────────────────────
        $mosaic->facetsingleton = new Facet();

        // ── Load input data ─────────────────────────────────────
        if ($loadInputData) {
            $input = self::getVar('Input::all');
            self::loadMosaic($input);
        }

        // ── Inject CLI data ─────────────────────────────────────
        foreach ($loadCliData as $key => $value) {
            self::setVar($key, $value);
        }

        // ── Restore snapshot ────────────────────────────────────
        if ($loadSnapShot) {
            self::loadSnapshot($loadSnapShot);
        }

        return $mosaic;
    }

    /**
     * Loads a snapshot view file and inflates its hidden inputs into the Mosaic.
     * @param string $name Snapshot name (corresponds to views/<name>.php)
     * @return void
     */
    private static function loadSnapshot(string $name): void
    {
        $path = __DIR__ . "/views/{$name}.php";
        if (!file_exists($path)) {
            throw new Exception("Snapshot not found: {$path}");
        }
        // Include the view file — it outputs hidden <input> tags
        ob_start();
        include $path;
        $html = ob_get_clean();

        // Parse hidden inputs into key-value pairs
        $data = [];
        if (preg_match_all('/<input[^>]+name="([^"]+)"[^>]+value="([^"]*)"/', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $data[$m[1]] = $m[2];
            }
        }
        self::loadMosaic($data);
    }

    /**
     * Returns the current request-scope Facet singleton.
     * Created during Mosaic::load().
     * @return Facet|null
     */
    public static function facet(): ?Facet
    {
        return self::$instance?->facetsingleton;
    }

    /**
     * Public constructor — use Mosaic::load() instead.
     * Sets self::$instance so that legacy code creating `new Mosaic()`
     * still wires the singleton. Crystal::loadAll() returns the instance
     * which Pane stores for backward compatibility.
     */
    public function __construct()
    {
        self::$instance = $this;
    }

    /**
     * Loads mosaic data from input.
     * Parses input keys in 'inlay-id' format to load or add Shards,
     * and handles additional variables under the last inlay.
     * Enables change tracking after loading.
     * @param $input Input data to process, typically from ProcessWire's input.
     * @return void
     * @param mixed $input Description.
     */
    public static function loadMosaic($input): void
    {
        $i = self::$instance;
        Exception::debug('VAR',"Slurping input data: " . Facet::_($input));
        Exception::debug('VAR','    ****    Slurping Up STORED Variables    ****');
        $currentInlay = self::getVar('Input::inlayname')
            ?? null
            ?? 'ClearView';

        if (!is_null($input)) {
            foreach ($input as $key => $value) {
                if ($key === null) {
                    Exception::debug('VAR','Skipped null key');
                    continue;
                }
                if (is_string($key) && str_contains($key, "-")) {
                    list($inlay, $id) = explode("-", $key, 2);
                    Exception::debug('VAR',"Slurping $inlay, $id");
                    if ($inlay === $currentInlay) {
                        $shard = Shard::loadShard($value, id: $id, inlay: $inlay);
                    } else {
                        self::addShard($value, id: $id, inlay: $inlay);
                    }
                }
            }
            $inlay = self::getVar("Shared::lastInlay");
            Exception::debug('VAR',"LastInlay was $inlay");

            if ($inlay !== null) {
                Exception::debug('VAR','    ****    Slurping Up ADDed Variables    ****');
                foreach ($input as $key => $value) {
                    if ($key === null || !is_string($key) || str_contains($key, "-")) {
                        continue;
                    }
                    if (is_string($value) && strlen($value) > 0) {
                        Exception::debug('VAR',"Adding [$inlay][$key] = [$value]");
                        $shard = Shard::loadShard($value, id: $key, inlay: $inlay);
                        self::checkShard($shard);
                    } elseif ($value !== null) {
                        Exception::debug('VAR',"Skipped non-string value for key={$key}: " . Facet::_($value));
                    }
                }
            }
        }
        $i->trackChanges = true; // Start tracking changes
        self::setVar("Shared::lastInlay", $currentInlay);

        // ── PaneAttr extraction ────────────────────────────
        // hx-vals from <pane> arrive as regular POST params. Keys without
        // '-' that aren't internal prefixes become PaneAttr attributes.
        $sharedVars = null;
        $attrs = [];
        foreach ($input as $key => $value) {
            if ($key === null || !is_string($key)) continue;
            if (str_contains($key, "-")) continue;
            if (str_starts_with($key, "Pane::") ||
                str_starts_with($key, "Input::") ||
                str_starts_with($key, "Session::") ||
                str_starts_with($key, "ClearView::")) continue;
            if ($key === 'shared') {
                $sharedVars = $value;
                continue;
            }
            if (is_string($value) && strlen($value) > 0) {
                $attrs[$key] = $value;
            }
        }
        if (!empty($attrs)) {
            // Store under PaneAttr inlay as 'attributes' shard
            Shard::loadShard(array_merge(['id' => 'attributes', 'inlay' => 'PaneAttr'], $attrs));
            Exception::debug('VAR', "PaneAttr::attributes set: " . json_encode($attrs));
        }
        if ($sharedVars !== null) {
            $varNames = is_array($sharedVars)
                ? $sharedVars
                : array_map('trim', explode(',', (string)$sharedVars));
            foreach ($varNames as $varName) {
                $varName = trim($varName);
                if (isset($attrs[$varName])) {
                    self::setVar("Shared::$varName", $attrs[$varName]);
                    Exception::debug('VAR', "Shared::$varName = {$attrs[$varName]}");
                }
            }
        }
    }

    /**
     * Gets the short class name of an object.
     * @param object $classobj
     * @return string
     */
    public static function classname(object $classobj): string
    {
        return (new \ReflectionClass($classobj))->getShortName();
    }

    /**
     * Creates a unique address for a shard.
     * @param Shard|array $input Shard object or array with 'id' and 'inlay' keys.
     * @return string The unique address in the format "inlay-id".
     */
    public static function makeAddress($input): string
    {
        if ($input instanceof Shard) {
            $id = $input->id();
            $inlay = $input->inlay();
        } elseif (is_array($input)) {
            $id = $input['id'];
            $inlay = $input['inlay'];
        } else {
            throw new Exception("Invalid input for makeAddress: must be Shard or array with 'id' and 'inlay'");
        }
        return "$inlay-$id";
    }

    /**
     * Checks if a shard exists on the client.
     * @param object $shard The Shard to check (must have an address property).
     * @return bool
     */
    public static function isShardStored($shard): bool
    {
        return array_key_exists($shard->address, self::$instance->checkList);
    }

    /**
     * Indexes into the mosaic storage, always returning a Shard or null.
     * @param string $inlay The inlay name to search in.
     * @param string $id The Shard ID.
     * @return Shard|null
     */
    public static function index(string $inlay, string $id): ?Shard
    {
        $address = self::makeAddress(['id' => $id, 'inlay' => $inlay]);
        $shard = self::$instance->mosaic[$address] ?? null;
        if ($shard && is_string($shard)) {
            $shard = Shard::loadShard($shard, id: $id, inlay: $inlay);
            self::$instance->mosaic[$address] = $shard;
        }
        return $shard;
    }

    /**
     * Checks if an inlay and ID exist in the mosaic.
     * @param string $inlay The inlay name to check.
     * @param string $id The Shard ID.
     * @return bool
     */
    public static function exists(string $inlay, string $id): bool
    {
        $address = self::makeAddress(['id' => $id, 'inlay' => $inlay]);
        return array_key_exists($address, self::$instance->mosaic);
    }

    /**
     * Retrieves a variable from the Mosaic.
     * @param string $expression The variable expression to retrieve.
     * @param string|null $inlay The inlay name to search in (optional).
     * @return mixed
     */
    public static function getVar(string $expression, ?string $inlay = null)
    {
        return QueryParser::parseAndResolve($expression, inlay:$inlay);
    }

    /**
     * Sets a variable in the mosaic.
     * @param string $varname The variable name, possibly with ".field" or "inlay::var".
     * @param string $val The value to set.
     * @param string|null $inlay The inlay name to store in.
     * @return mixed
     */
    public static function setVar(string $varname, $val, ?string $inlay = null)
    {
        if ($varname === null || strlen($varname) === 0) {
            return;
        }
        $varname = trim($varname);

        if (strpos($varname, "::") !== false) {
            list($inlay, $varname) = explode("::", $varname, 2);
            $crystal = self::getVar("ClearView::{$inlay}");
            if (!is_null($crystal) && $crystal instanceof Crystal) {
                return $crystal->setVar($varname, $val);
            }
        }
        $field = null;
        $inlay = $inlay ?? self::getVar('Input::inlayname');
        if (strpos($varname, ".") !== false) {
            list($varname, $field) = explode(".", $varname, 2);
        }

        $shard = self::index($inlay, $varname);
        if ($shard) {
            $shard->$field = $val;
            self::checkShard($shard);
        } else {
            if ($varname == 'value') {
                $data = ['text' => $val, '__pF' => 'text'];
            } else {
                $data = ['id' => $varname, 'inlay' => $inlay, $field ?? 'value' => $val];
            }
            $shard = Shard::loadShard($data);
        }
        return $shard;
    }

    /**
     * Bulk write to Mosaic variables.
     * @param array $values Key-value pairs to set.
     * @param string|null $inlay Inlay context.
     * @return void
     */
    public static function fill(array $values, ?string $inlay = null): void
    {
        foreach ($values as $varname => $val) {
            if ($val !== null) {
                self::setVar($varname, (string)$val, $inlay);
            }
        }
    }

    /**
     * Initializes a variable if it doesn't exist.
     * @param string $var The variable name.
     * @param string $value The value to set.
     * @param string|null $inlay The inlay name.
     * @return void
     */
    public static function initVar(string $var, string $value, ?string $inlay = null): void
    {
        $inlay = $inlay ?? self::getVar('Input::inlayname');

        if (!self::exists($inlay, $var)) {
            self::setVar($var, $value, $inlay);
        }
    }

    /**
     * Initializes values in the destination array only for keys that don't exist.
     * @param array &$dest The destination array to modify.
     * @param array $defaults Key-value pairs to set if the key is not already present.
     * @return void
     * @param mixed $dest Description.
     */
    public static function initArray(array &$dest, array $defaults): void
    {
        foreach ($defaults as $key => $value) {
            if (!array_key_exists($key, $dest)) {
                $dest[$key] = $value;
            } else {
                switch ($key) {
                case 'children': $dest[$key] = array_merge($value,$dest[$key]); break;
                case 'class':
                case 'page-fields':
                    $dest[$key] .= ' ' . $value;
                    break;
                }
            }
        }
    }

    /**
     * Initializes multiple variables if they don't exist.
     * @param array $array Key-value pairs to initialize.
     * @param string|null $inlay The inlay name.
     * @return void
     */
    public static function initVars(array $array, ?string $inlay = null): void
    {
        foreach ($array as $varname => $value) {
            if ($value !== null) {
                self::initVar($varname, (string)$value, $inlay);
            }
        }
    }

    /**
     * Deletes multiple variables from the mosaic.
     * @param array|string $varname The variable name(s) to delete.
     * @param string|null $inlay The inlay name.
     * @return void
     */
    public static function delVars($varname, ?string $inlay = null): void
    {
        if (is_array($varname)) {
            foreach ($varname as $var) {
                self::delVar($var, $inlay);
            }
        } else {
            self::delVar($varname, $inlay);
        }
    }

    /**
     * Deletes a variable from the mosaic.
     * @param string $varname The variable name.
     * @param string|null $inlay The inlay name.
     * @return void
     */
    public static function delVar(string $varname, ?string $inlay = null): void
    {
        if (strpos($varname, '::') !== false) {
            [$inlay, $varname] = explode('::', $varname, 2);
            if (self::exists('ClearView', $inlay)) {
                self::getVar("ClearView::$inlay")->delVar($varname);
                return;
            }
        }
        $address = self::makeAddress(['id' => $varname, 'inlay' => $inlay ?? self::getVar('Input::inlayname')]);
        $shard = self::$instance->mosaic[$address] ?? null;
        if ($shard) {
            $shard->delVar();
        }
    }

    /**
     * Dumps all mosaic data for debugging.
     * @param mixed|null $obj The object to dump.
     * @return void
     */
    public static function dumpEverything($obj = null): void
    {
        if ($obj instanceof Facet) {
            $obj = null;
        }
        Exception::outputComment("dumpEverything called");
        $encoded = json_encode($obj ?? self::$instance->mosaic, JSON_PRETTY_PRINT);
        $id = is_object($obj) ? $obj->id() : 'MOSAIC';
        $lines = explode("\n", $encoded);
        Exception::outputComment("\n$id: " . implode("\n", $lines) . "\n");
    }

    /**
     * Outputs the entire mosaic as HTML inputs.
     * @return void
     */
    public static function outputMosaic(): void
    {
        $i = self::$instance;
        Exception::debug('VAR', 'Starting outputMosaic');
        $facet = (new Facet())
            ->open("<div id=\"{{Pane::name}}" . Config::LAYER_SUFFIX_MOSAIC . "\" class=\"{{Config::class_mosaic}}\" hx-preserve=\"true\">");
        foreach (array_keys($i->checkList) as $address) {
            if (!str_starts_with($address,'ClearView') && !str_starts_with($address,'__')) {
                self::storeShard($i->mosaic[$address], $facet);
            } else {
                Exception::debug('VAR',"Skipping $address");
            }
        }
        $facet->close();
    }

    /**
     * Updates the mosaic with changed or added shards.
     * @return void
     */
    public static function updateMosaic(): void
    {
        $i = self::$instance;
        Exception::debug('VAR', 'Starting updateMosaic');
        foreach ($i->checkList as $address) {
            $shard = $i->mosaic[$address] ?? null;
            if ($shard) {
                $oldValue = self::getVar("Input::$address");
                if ($oldValue === null) {
                    self::insertShard($shard);
                } else {
                    $newValue = $shard->hasChanged($oldValue);
                    if ($newValue !== null) {
                        self::updateShard($shard, $newValue);
                    }
                }
            }
        }
    }

    /**
     * Generates HTML to store a shard.
     * @param Shard $shard The Shard to store.
     * @param Facet $facet
     * @return void
     */
    public static function storeShard(Shard $shard, Facet $facet): void
    {
        $address = $shard->address;
        $facet->using($shard)->out("<input type='hidden' name='{$address}' value='{{Glyph::deflate()}}'>");
    }

    /**
     * Generates OOB HTML to insert a new shard.
     * @param Shard $shard The Shard to insert.
     * @return void
     */
    public static function insertShard(Shard $shard): void
    {
        $address = $shard->address;
        (new Facet($shard))
            ->oob()
            ->open("<div {{hx-swap-oob='beforeend:#{{Pane::name}}-mos}}'>
                <input type='hidden' name='{$address}' value='{{Glyph::deflate()}}'>")
            ->close();
    }

    /**
     * Generates OOB HTML to update a shard.
     * @param Shard $shard The Shard to update.
     * @param string|null $newValue The new deflated value.
     * @return void
     */
    public static function updateShard(Shard $shard, ?string $newValue = null): void
    {
        $newValue = $newValue ?? $shard->deflate();
        $address = $shard->address;
        (new Facet($shard))
            ->oob()
            ->out("<input type='hidden' name='{$address}' value='{$newValue}' {{hx-swap-oob='innerHTML:#{{Pane::name}}-mos}} [name={$address}]'>")
            ->close();
    }

    /**
     * Generates OOB HTML to remove a shard.
     * @param Shard $shard The Shard to remove.
     * @return void
     */
    public static function removeShard(Shard $shard): void
    {
        $address = $shard->address;
        (new Facet($shard))
            ->oob()
            ->open("<div {{hx-swap-oob='delete:#{{Pane::name}}-mos}} [name={$address}]'>")
            ->close();
    }

    /**
     * Adds a shard to the mosaic.
     * @param mixed $json The JSON data or Shard to add.
     * @param string|null $id The Shard ID.
     * @param string|null $inlay The inlay name.
     * @return void
     */
    public static function addShard($json, ?string $id = null, ?string $inlay = null): void
    {
        $i = self::$instance;
        if (empty($json->address)) {
            $inlay = $inlay ?? self::getVar('Input::inlayname');
            $id = $id ?? uniqid('__array_');
            $json->address = self::makeAddress(['id'=>$id, 'inlay'=>$inlay]);
        }
        Exception::debug("SHARD","Adding shard at " . $json->address);
        $i->mosaic[$json->address] = $json;
    }

    /**
     * Deletes a shard from the mosaic.
     * @param object $shard The Shard to delete.
     * @return void
     */
    public static function delShard($shard): void
    {
        $i = self::$instance;
        self::removeShard($shard);
        unset($i->mosaic[$shard->address]);
        unset($i->checkList[$shard->address]);
    }

    /**
     * Checks a Shard and adds it to the checkList if not already present.
     * @param object $shard The Shard to check.
     * @return void
     */
    public static function checkShard($shard): void
    {
        $i = self::$instance;
        if ($i->trackChanges) {
            $i->checkList[$shard->address] = true;
        }
    }

    /**
     * Finds a single Shard by field and value.
     * @param string|null $field The field name to search.
     * @param mixed $value The value to match.
     * @param string $inlay The inlay to filter by.
     * @param string $op The comparison operator.
     * @return string|null The Shard ID if found, null otherwise.
     */
    public static function findShard(string $field, $value, ?string $inlay = null, string $op = '='): ?string
    {
        $i = self::$instance;
        $inlay = $inlay ?? self::getVar('Input::inlayname');
        foreach ($i->mosaic as $address => $element) {
            [$elemInlay, $id] = explode('-', $address, 2);
            if ($elemInlay === $inlay) {
                $val = $element->getField($field) ?? null;
                if (QueryParser::compare($val, $value, $op)) {
                    return $id;
                }
            }
        }
        return null;
    }

    /**
     * Finds multiple Shards by field and value.
     * @param string|null $field The field name to search.
     * @param mixed $value The value to match.
     * @param string $inlay The inlay name to filter by.
     * @param string $op The comparison operator.
     * @return array An array of matching Shards, indexed by ID.
     */
    public static function findShards(string $field, $value, ?string $inlay = null, string $op = '='): array
    {
        $i = self::$instance;
        $inlay = $inlay ?? self::getVar('Input::inlayname');
        $found = [];
        foreach ($i->mosaic as $address => $element) {
            [$elemInlay, $id] = explode('-', $address, 2);
            if ($elemInlay === $inlay) {
                $val = $element->getField($field) ?? null;
                if (QueryParser::compare($val, $value, $op)) {
                    $found[$id] = $element;
                }
            }
        }
        return $found;
    }
}
