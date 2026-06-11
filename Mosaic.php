<?php

namespace ClearView;

use ClearView\Facet;
use ClearView\Exception;
use ClearView\Sanitizer;
use ClearView\QueryParser;
use ProcessWire;

/**
 * Manages a collection of Shards, handling storage, retrieval, and manipulation of variables and elements.
 *
 * The Mosaic class is a singleton that serves as the central storage for Shards in ClearView, organizing them
 * by inlay and ID to form a structured data model. It provides methods for adding, retrieving, updating, and
 * deleting Shards, as well as searching and rendering them as HTML inputs for client-side synchronization via
 * HTMX. Mosaic interacts with Facet for template expansion, Shard for data encapsulation, and Crystal for
 * ProcessWire API access. It supports variable watching, sanitizers, and field searches, and tracks changes for
 * efficient updates.
 *
 * @see \ClearView\Shard
 * @see \ClearView\Facet
 * @see \ClearView\Crystal
 */
class Mosaic
{
    /** @var array Main storage array for mosaic data, indexed by shard addresses (inlay-id) */
    private $mosaic = [];

    /** @var array List of shard addresses that have changed or been added for update tracking */
    private $checkList = [];

    /** @var bool Flag to prevent change tracking until after loading the Mosaic */
    private $trackChanges = false;

    /** @var Mosaic|null Singleton instance of Mosaic */
    private static $instance = null;

    /**
     * Prevents cloning of the singleton instance.
     * Used to enforce the singleton pattern, ensuring only one Mosaic instance exists.
     * Why: Maintains a single source of truth for shard storage across the application.
     * @return void
     */
    public function __clone()
    {
    }

    /**
     * Prevents unserializing of the singleton instance.
     * Used to enforce the singleton pattern, preventing restoration of Mosaic from serialized data.
     * Why: Ensures the Mosaic instance is created fresh via init() to avoid stale data.
     * @return void
     */
    public function __wakeup()
    {
    }

    /**
     * Initializes the Mosaic singleton.
     * Called during system startup to create the singleton Mosaic instance. Throws an exception if already initialized.
     * Why: Sets up the central shard storage for ClearView, ensuring a single instance manages all data.
     * @return $this
     */
    public static function init()
    {
        if (self::$instance) {
            throw new Exception("Mosaic reinitialized!");
        }
        return self::$instance = new static();
    }

    /**
     * Protected constructor for singleton pattern.
     *
     * @return void
     */
    protected function __construct()
    {
    }

    /**
     * Loads mosaic data from input.
     * Called during request processing to populate the Mosaic with Shards from client input (e.g., form submissions).
     * Parses input keys in 'inlay-id' format to load or add Shards, and handles additional variables under the last
     * inlay. Enables change tracking after loading.
     * Why: Synchronizes client-side data with server-side Mosaic storage for state persistence.
     * @param $input Input data to process, typically from ProcessWire’s input (e.g., POST data).
     * @return void
     */
    public static function loadMosaic($input): void
    {
        Exception::debug('VAR',"Slurping input data: " . Facet::_($input));
        Exception::debug('VAR','    ****    Slurping Up STORED Variables    ****');
        $currentInlay = self::getVar("Input::inlayname") ?? ClearView::inlay();

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
                        // self::checkShard($shard);
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
        self::$instance->trackChanges = true; // Start tracking changes
        self::setVar("Shared::lastInlay", $currentInlay);
    }

    /**
     * Gets the short class name of an object.
     * Used to extract the short name of a class (without namespace) for logging or identification.
     * Why: Simplifies debugging and shard identification by using concise class names.
     * @param object $classobj The object to get the class name from.
     * @return string The short class name (e.g., 'Shard' for 'ClearView\Shard').
     */
    public static function classname(object $classobj): string
    {
        return (new \ReflectionClass($classobj))->getShortName();
    }

    /**
     * Creates a unique address for a shard.
     * Generates a unique address in 'inlay-id' format for storing or retrieving Shards in the Mosaic.
     * Why: Provides a consistent key for indexing Shards in the mosaic array.
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
     * Used to determine if a Shard is already stored client-side by checking the checkList.
     * Why: Helps optimize updates by identifying Shards that need insertion or modification.
     * @param object $shard The Shard to check (must have an address property).
     * @return bool True if the Shard is stored on the client, false otherwise.
     */
    public static function isShardStored($shard)
    {
        return array_key_exists($shard->address, self::$instance->checkList);
    }

    /**
     * Indexes into the mosaic storage, always returning a Shard or null.
     * Used to retrieve a Shard by its inlay and ID, converting stored JSON to a Shard if necessary.
     * Why: Provides direct access to Shards in the Mosaic for variable retrieval or manipulation.
     * @param string $inlay The inlay name to search in.
     * @param string $id The Shard ID.
     * @return Shard|null The Shard if found, null otherwise.
     */
    public static function index(string $inlay, string $id): ?Shard
    {
        $address = self::makeAddress(['id' => $id, 'inlay' => $inlay]);
        $shard = self::$instance->mosaic[$address] ?? null;
        if ($shard && is_string($shard)) {
            // Convert JSON strings to Shards on load
            $shard = Shard::loadShard($shard, id: $id, inlay: $inlay);
            self::$instance->mosaic[$address] = $shard;
        }
        return $shard;
    }

    /**
     * Checks if an inlay and ID exist in the mosaic.
     * Used to verify the existence of a Shard in the Mosaic before retrieval or modification.
     * Why: Prevents errors when accessing non-existent Shards.
     * @param string $inlay The inlay name to check.
     * @param string $id The Shard ID.
     * @return bool True if the Shard exists, false otherwise.
     */
    public static function exists(string $inlay, string $id): bool
    {
        $address = self::makeAddress(['id' => $id, 'inlay' => $inlay]);
        return array_key_exists($address, self::$instance->mosaic);
    }

    /**
     * Retrieves a variable from the Mosaic.
     * This is the primary method for retrieving a variable (Shard) from the Mosaic. It accepts a string expression
     * that can include an inlay name, a shard ID, a field, and sanitizers. It uses a cascading lookup logic,
     * starting with a specific address, then checking for a Crystal, and finally searching by ID or field.
     * Why: Centralizes variable retrieval, providing a consistent and powerful API for accessing all Shards.
     * @param string $expression The variable expression to retrieve (e.g., 'MyForm::myId.myField').
     * @param string|null $inlay The inlay name to search in (optional).
     * @return mixed The retrieved value, sanitized if applicable, or null if not found.
     */
    public static function getVar(string $expression, ?string $inlay = null)
    {
        return QueryParser::parseAndResolve($expression, inlay:$inlay);
    }

    /**
     * Sets a variable in the mosaic, handling template expansion and field specifications.
     * Used to store a value in a Shard, creating a new Shard if it doesn’t exist. Supports field access via '.field'
     * syntax and Crystal access via 'inlay::var' syntax. Tracks changes for updates.
     * Why: Provides a unified interface for storing data in the Mosaic or Crystals.
     * @param string $varname The variable name, possibly with ".field" or "inlay::var".
     * @param string $val The value to set (converts Shards to strings via __toString()).
     * @param string|null $inlay The inlay name to store in (defaults to current Facet inlay).
     * @return mixed The set Shard or value returned by Crystal’s setVar().
     */
    public static function setVar(string $varname, $val, ?string $inlay = null)
    {
        if ($varname === null || strlen($varname) === 0) {
            return;
        }
        $varname = trim($varname);

        // Check for Mosaic variable (a Crystal)
        if (strpos($varname, "::") !== false) {
            list($inlay, $varname) = explode("::", $varname, 2);
            $crystal = self::getVar("ClearView::{$inlay}");
            if (!is_null($crystal) && $crystal instanceof Crystal) {
                return $crystal->setVar($varname, $val);
            }
        }
        // Check for field on current inlay
        $field = null;
        $inlay = $inlay ?? Facet::inlay();
        if (strpos($varname, ".") !== false) {
            list($varname, $field) = explode(".", $varname, 2);
        }

        $shard = self::index($inlay, $varname);
        if ($shard) {
            $shard->$field = $val; // Uses __set()
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
     * Sets multiple variables in the mosaic.
     * Used to store multiple values at once, applying setVar() to each key-value pair.
     * Now supports setVars('somearray', [...]) to set somearray.children directly.
     * Why: Simplifies batch updates to Mosaic data.
     * @param array $array Key-value pairs to set, with keys possibly using ".field" syntax.
     * @param string|null $inlay The inlay name to store in (defaults to current Facet inlay).
     * @return void
     */
    public static function setVars($vars, $value = null): void
    {
        if (is_string($vars) && is_array($value)) {
            // Special case: setVars('somearray', [...]) sets somearray.children
            $shard = self::getVar($vars) ?? new Shard(['id' => $vars, 'inlay' => Facet::inlay()]);
            $shard->setField('children', $value);
            self::setVar($vars, $shard);
        } else {
            // Existing logic: treat $vars as key-value pairs
            foreach ((array)$vars as $varname => $val) {
                if ($val !== null) {
                    self::setVar($varname, (string)$val);
                }
            }
        }
    }

    /**
     * Initializes a variable if it doesn’t exist, handling template expansion and field specifications.
     * Used to set a variable only if it’s not already present in the Mosaic, preventing overwrites.
     * @param string $var The variable name, possibly with ".field".
     * @param string $value The value to set (converts Shards to strings via __toString()).
     * @param string|null $inlay The inlay name to store in (defaults to current Facet inlay).
     * @return void
     */
    public static function initVar(string $var, string $value, ?string $inlay = null): void
    {
        $inlay = $inlay ?? ClearView::inlay();

        if (!self::exists($inlay, $var)) {
            self::setVar($var, $value, $inlay);
        }
    }

    /**
     * Initializes values in the destination array only for keys that don’t exist.
     * @param array &$dest The destination array to modify.
     * @param array $defaults Key-value pairs to set if the key is not already present.
     * @return void
     */
    public static function initArray(array &$dest, array $defaults): void
    {
        foreach ($defaults as $key => $value) {
            if (!array_key_exists($key, $dest)) {
                $dest[$key] = $value;
            } else {
                /* Special merged fields */
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
     * Initializes multiple variables if they don’t exist.
     * Used to set multiple default values at once, applying initVar() to each key-value pair.
     * Why: Simplifies batch initialization of Mosaic data.
     * @param array $array Key-value pairs to initialize, with keys possibly using ".field" syntax.
     * @param string|null $inlay The inlay name to store in (defaults to current Facet inlay).
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
     * Used to remove multiple Shards at once, supporting both array and single string inputs.
     * Why: Simplifies batch deletion of Mosaic data.
     * @param array|string $varname The variable name(s) to delete, possibly with "inlay::var".
     * @param string|null $inlay The inlay name to delete from (defaults to current Facet inlay).
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
     * Used to remove a Shard by its name, handling Crystal variables via 'inlay::var' syntax and generating OOB
     * HTML for client-side removal.
     * Why: Supports cleanup of Mosaic data and synchronization with the client.
     * @param string $varname The variable name, possibly with "inlay::var".
     * @param string|null $inlay The inlay name to delete from (defaults to current Facet inlay).
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
        $address = self::makeAddress(['id' => $varname, 'inlay' => $inlay ?? Facet::inlay()]);
        $shard = self::$instance->mosaic[$address] ?? null;
        if ($shard) {
            $shard->delVar();
        }
    }

    /**
     * Dumps all mosaic data for debugging.
     * Used to log the entire Mosaic or a specific object’s data to the JavaScript console for debugging.
     * Why: Aids developers in inspecting Mosaic state during development.
     * @param mixed|null $obj The object to dump (defaults to entire Mosaic).
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
     * Used to render all Shards in the Mosaic as hidden input fields within a div for client-side synchronization
     * via HTMX.
     * Why: Enables client-side persistence of Mosaic state across requests.
     * @return void
     */
    public static function outputMosaic(): void
    {
        Exception::debug('VAR', 'Starting outputMosaic');
        $facet = (new Facet())
            ->open("<div id=\"{{Pane::name}}" . Config::LAYER_SUFFIX_MOSAIC . "\" class=\"{{Config::class_mosaic}}\">");
        foreach (array_keys(self::$instance->checkList) as $address) {
            if (!str_starts_with($address,'ClearView') && !str_starts_with($address,'__')) {
                self::storeShard(self::$instance->mosaic[$address], $facet);
            } else {
                Exception::debug('VAR',"Skipping $address");
            }
        }
        $facet->close();
    }

    /**
     * Updates the mosaic with changed or added shards.
     * Used to synchronize changed or new Shards with the client by generating OOB HTML for insertions or updates.
     * Why: Ensures client-side state reflects server-side changes efficiently.
     * @return void
     */
    public static function updateMosaic(): void
    {
        Exception::debug('VAR', 'Starting updateMosaic');
        foreach (self::$instance->checkList as $address) {
            $shard = self::$instance->mosaic[$address] ?? null;
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
     * Used to render a Shard as a hidden input field for client-side storage, typically within outputMosaic().
     * Why: Enables persistence of Shard data on the client for synchronization.
     * @param Shard $shard The Shard to store.
     * @param string $address Address in the Mosaic
     * @return void
     */
    public static function storeShard(Shard $shard, Facet $facet): void
    {
        $address = $shard->address;
        $facet->using($shard)->out("<input type='hidden' name='{$address}' value='{{Glyph::deflate()}}'>");
    }

    /**
     * Generates OOB HTML to insert a new shard.
     * Used to append a new Shard to the client-side Mosaic as a hidden input field via OOB swapping.
     * Why: Supports dynamic addition of Shards without full page reloads.
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
     * Used to update an existing Shard’s value on the client via OOB swapping, replacing the hidden input field.
     * Why: Ensures client-side Shard data reflects server-side changes efficiently.
     * @param Shard $shard The Shard to update.
     * @param string $newValue The new deflated value (defaults to Shard’s deflate() output).
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
     * Used to delete a Shard from the client-side Mosaic via OOB swapping, removing the hidden input field.
     * Why: Supports dynamic removal of Shards without full page reloads.
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
     * Used to store a new Shard in the Mosaic, either from JSON data or an existing Shard object.
     * Why: Supports dynamic addition of data to the Mosaic during processing or initialization.
     * @param mixed $json The JSON data or Shard to add.
     * @param string|null $id The Shard ID (defaults to a unique ID).
     * @param string|null $inlay The inlay name (defaults to current Facet inlay).
     * @return void
     */
    public static function addShard($json, ?string $id = null, ?string $inlay = null): void
    {
        if (empty($json->address)) {
            $inlay = $inlay ?? ClearView::inlay();
            $id = $id ?? uniqid('__array_');
            $json->address = self::makeAddress(['id'=>$id, 'inlay'=>$inlay]);
        }
        Exception::debug("SHARD","Adding shard at " . $json->address);
        self::$instance->mosaic[$json->address] = $json;
        //self::checkShard($json);
    }

    /**
     * Deletes a shard from the mosaic.
     * Used to remove a Shard from the Mosaic storage and client-side, generating OOB HTML for deletion.
     * Why: Supports cleanup of Mosaic data and client synchronization.
     * @param object $shard The Shard to delete (must have an address property).
     * @return void
     */
    public static function delShard($shard)
    {
        self::removeShard($shard);
        unset(self::$instance->mosaic[$shard->address]);
        unset(self::$instance->checkList[$shard->address]);
    }

    /**
     * Checks a Shard and adds it to the checkList if not already present.
     * Used to mark a Shard as changed or added for later update during updateMosaic().
     * Why: Tracks modifications to optimize client-side updates.
     * @param object $shard The Shard to check (must have an address property).
     * @return void
     */
    public static function checkShard($shard)
    {
        if (self::$instance->trackChanges) {
            self::$instance->checkList[$shard->address] = true;
        }
    }

    /**
     * Finds a single Shard by field and value.
     * Used to search the Mosaic for a Shard matching a specific field and value (e.g., 'type=button').
     * Supports comparison operators for flexible queries.
     * Why: Enables global or inlay-specific searches to locate Shards for rendering or processing.
     * @param string|null $field The field name to search (e.g., 'name').
     * @param mixed $value The value to match.
     * @param string $inlay The inlay to filter by (optional, defaults to current Facet inlay).
     * @param string $op The comparison operator (e.g., '=', '!=', '*=') (default: '=').
     * @return string|null The Shard ID if found, null otherwise.
     */
    public static function findShard(string $field, $value, ?string $inlay = null, string $op = '='): ?string
    {
        $inlay = $inlay ?? Facet::inlay();
        foreach (self::$instance->mosaic as $address => $element) {
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
     * Used to search the Mosaic for all Shards matching a specific field and value (e.g., 'type=button').
     * Supports comparison operators for flexible queries.
     * Why: Enables batch retrieval of Shards for rendering or processing.
     * @param string|null $field The field name to search (e.g., 'name').
     * @param mixed $value The value to match.
     * @param string $inlay The inlay name to filter by (optional, defaults to current Facet inlay).
     * @param string $op The comparison operator (e.g., '=', '!=', '*=') (default: '=').
     * @return array An array of matching Shards, indexed by ID.
     */
    public static function findShards(string $field, $value, ?string $inlay = null, string $op = '='): array
    {
        $inlay = $inlay ?? Facet::inlay();
        $found = [];
        foreach (self::$instance->mosaic as $address => $element) {
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
