<?php

namespace ClearView;

use ClearView\Facet;
use ClearView\Exception;
use ClearView\Sanitizer;
use ClearView\QueryParser;
use ProcessWire;

/**
 * Request-scoped store for Shards, owned by the current Pane.
 *
 * Shards are indexed by inlay-id, deflated into hidden inputs on the client,
 * and re-inflated on every request. The Mosaic instance represents the current
 * request's single client Mosaic — not a global aggregate of all panes.
 *
 * Each Pane owns its Mosaic via `$this->mosaic = new Mosaic()`.
 * Access the current instance via `ClearView::Mosaic()`.
 *
 * @see \ClearView\Shard
 * @see \ClearView\Facet
 * @see \ClearView\Crystal
 * @see \ClearView\Pane
 */
class Mosaic implements \ArrayAccess
{
    /** @var array Main storage array for mosaic data, indexed by shard addresses (inlay-id) */
    private $mosaic = [];

    /** @var array List of shard addresses that have changed or been added for update tracking */
    private $checkList = [];

    /** @var bool Flag to prevent change tracking until after loading the Mosaic */
    private $trackChanges = false;

    /**
     * Public constructor — each Pane creates its own Mosaic instance.
     */
    public function __construct()
    {
    }

    /**
     * Loads mosaic data from input.
     *
     * Parses input keys in 'inlay-id' format to load or add Shards,
     * and handles additional variables under the last inlay.
     * Enables change tracking after loading.
     *
     * @param $input Input data to process, typically from ProcessWire's input.
     * @return void
     */
    public function loadMosaic($input): void
    {
        Exception::debug('VAR',"Slurping input data: " . Facet::_($input));
        Exception::debug('VAR','    ****    Slurping Up STORED Variables    ****');
        $currentInlay = $this->getVar('Input::inlayname') ?? Facet::inlay() ?? 'ClearView';

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
                        $this->addShard($value, id: $id, inlay: $inlay);
                    }
                }
            }
            $inlay = $this->getVar("Shared::lastInlay");
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
                        $this->checkShard($shard);
                    } elseif ($value !== null) {
                        Exception::debug('VAR',"Skipped non-string value for key={$key}: " . Facet::_($value));
                    }
                }
            }
        }
        $this->trackChanges = true; // Start tracking changes
        $this->setVar("Shared::lastInlay", $currentInlay);

        // ── Shared::attributes extraction ────────────────────────────
        // hx-vals from <pane> arrive as regular POST params. Keys without
        // '-' that aren't internal prefixes are Shared::attributes.
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
            $this->setVar("Shared::attributes", $attrs);
            Exception::debug('VAR', "Shared::attributes set: " . json_encode($attrs));
        }
        if ($sharedVars !== null) {
            $varNames = is_array($sharedVars)
                ? $sharedVars
                : array_map('trim', explode(',', (string)$sharedVars));
            foreach ($varNames as $varName) {
                $varName = trim($varName);
                if (isset($attrs[$varName])) {
                    $this->setVar("Shared::$varName", $attrs[$varName]);
                    Exception::debug('VAR', "Shared::$varName = {$attrs[$varName]}");
                }
            }
        }
    }

    /**
     * Gets the short class name of an object.
     *
     * @param object $classobj
     * @return string
     */
    public function classname(object $classobj): string
    {
        return (new \ReflectionClass($classobj))->getShortName();
    }

    /**
     * Creates a unique address for a shard.
     *
     * @param Shard|array $input Shard object or array with 'id' and 'inlay' keys.
     * @return string The unique address in the format "inlay-id".
     */
    public function makeAddress($input): string
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
     *
     * @param object $shard The Shard to check (must have an address property).
     * @return bool
     */
    public function isShardStored($shard): bool
    {
        return array_key_exists($shard->address, $this->checkList);
    }

    /**
     * Indexes into the mosaic storage, always returning a Shard or null.
     *
     * @param string $inlay The inlay name to search in.
     * @param string $id The Shard ID.
     * @return Shard|null
     */
    public function index(string $inlay, string $id): ?Shard
    {
        $address = $this->makeAddress(['id' => $id, 'inlay' => $inlay]);
        $shard = $this->mosaic[$address] ?? null;
        if ($shard && is_string($shard)) {
            $shard = Shard::loadShard($shard, id: $id, inlay: $inlay);
            $this->mosaic[$address] = $shard;
        }
        return $shard;
    }

    /**
     * Checks if an inlay and ID exist in the mosaic.
     *
     * @param string $inlay The inlay name to check.
     * @param string $id The Shard ID.
     * @return bool
     */
    public function exists(string $inlay, string $id): bool
    {
        $address = $this->makeAddress(['id' => $id, 'inlay' => $inlay]);
        return array_key_exists($address, $this->mosaic);
    }

    /**
     * Retrieves a variable from the Mosaic.
     *
     * @param string $expression The variable expression to retrieve.
     * @param string|null $inlay The inlay name to search in (optional).
     * @return mixed
     */
    public function getVar(string $expression, ?string $inlay = null)
    {
        return QueryParser::parseAndResolve($expression, inlay:$inlay);
    }

    /**
     * Sets a variable in the mosaic.
     *
     * @param string $varname The variable name, possibly with ".field" or "inlay::var".
     * @param string $val The value to set.
     * @param string|null $inlay The inlay name to store in.
     * @return mixed
     */
    public function setVar(string $varname, $val, ?string $inlay = null)
    {
        if ($varname === null || strlen($varname) === 0) {
            return;
        }
        $varname = trim($varname);

        if (strpos($varname, "::") !== false) {
            list($inlay, $varname) = explode("::", $varname, 2);
            $crystal = $this->getVar("ClearView::{$inlay}");
            if (!is_null($crystal) && $crystal instanceof Crystal) {
                return $crystal->setVar($varname, $val);
            }
        }
        $field = null;
        $inlay = $inlay ?? Facet::inlay();
        if (strpos($varname, ".") !== false) {
            list($varname, $field) = explode(".", $varname, 2);
        }

        $shard = $this->index($inlay, $varname);
        if ($shard) {
            $shard->$field = $val;
            $this->checkShard($shard);
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
     *
     * @param array $values Key-value pairs to set.
     * @param string|null $inlay Inlay context.
     * @return void
     */
    public function fill(array $values, ?string $inlay = null): void
    {
        foreach ($values as $varname => $val) {
            if ($val !== null) {
                $this->setVar($varname, (string)$val, $inlay);
            }
        }
    }

    // ─── ArrayAccess implementation ───────────────────────────────────────

    public function offsetGet($key): mixed
    {
        return $this->getVar((string)$key);
    }

    public function offsetSet($key, $value): void
    {
        $this->setVar((string)$key, $value);
    }

    public function offsetExists($key): bool
    {
        return $this->getVar((string)$key) !== null;
    }

    public function offsetUnset($key): void
    {
        $this->delVar((string)$key);
    }

    /**
     * Initializes a variable if it doesn't exist.
     *
     * @param string $var The variable name.
     * @param string $value The value to set.
     * @param string|null $inlay The inlay name.
     * @return void
     */
    public function initVar(string $var, string $value, ?string $inlay = null): void
    {
        $inlay = $inlay ?? Facet::inlay();

        if (!$this->exists($inlay, $var)) {
            $this->setVar($var, $value, $inlay);
        }
    }

    /**
     * Initializes values in the destination array only for keys that don't exist.
     *
     * @param array &$dest The destination array to modify.
     * @param array $defaults Key-value pairs to set if the key is not already present.
     * @return void
     */
    public function initArray(array &$dest, array $defaults): void
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
     *
     * @param array $array Key-value pairs to initialize.
     * @param string|null $inlay The inlay name.
     * @return void
     */
    public function initVars(array $array, ?string $inlay = null): void
    {
        foreach ($array as $varname => $value) {
            if ($value !== null) {
                $this->initVar($varname, (string)$value, $inlay);
            }
        }
    }

    /**
     * Deletes multiple variables from the mosaic.
     *
     * @param array|string $varname The variable name(s) to delete.
     * @param string|null $inlay The inlay name.
     * @return void
     */
    public function delVars($varname, ?string $inlay = null): void
    {
        if (is_array($varname)) {
            foreach ($varname as $var) {
                $this->delVar($var, $inlay);
            }
        } else {
            $this->delVar($varname, $inlay);
        }
    }

    /**
     * Deletes a variable from the mosaic.
     *
     * @param string $varname The variable name.
     * @param string|null $inlay The inlay name.
     * @return void
     */
    public function delVar(string $varname, ?string $inlay = null): void
    {
        if (strpos($varname, '::') !== false) {
            [$inlay, $varname] = explode('::', $varname, 2);
            if ($this->exists('ClearView', $inlay)) {
                $this->getVar("ClearView::$inlay")->delVar($varname);
                return;
            }
        }
        $address = $this->makeAddress(['id' => $varname, 'inlay' => $inlay ?? Facet::inlay()]);
        $shard = $this->mosaic[$address] ?? null;
        if ($shard) {
            $shard->delVar();
        }
    }

    /**
     * Dumps all mosaic data for debugging.
     *
     * @param mixed|null $obj The object to dump.
     * @return void
     */
    public function dumpEverything($obj = null): void
    {
        if ($obj instanceof Facet) {
            $obj = null;
        }
        Exception::outputComment("dumpEverything called");
        $encoded = json_encode($obj ?? $this->mosaic, JSON_PRETTY_PRINT);
        $id = is_object($obj) ? $obj->id() : 'MOSAIC';
        $lines = explode("\n", $encoded);
        Exception::outputComment("\n$id: " . implode("\n", $lines) . "\n");
    }

    /**
     * Outputs the entire mosaic as HTML inputs.
     *
     * @return void
     */
    public function outputMosaic(): void
    {
        Exception::debug('VAR', 'Starting outputMosaic');
        $facet = (new Facet())
            ->open("<div id=\"{{Pane::name}}" . Config::LAYER_SUFFIX_MOSAIC . "\" class=\"{{Config::class_mosaic}}\" hx-preserve=\"true\">");
        foreach (array_keys($this->checkList) as $address) {
            if (!str_starts_with($address,'ClearView') && !str_starts_with($address,'__')) {
                $this->storeShard($this->mosaic[$address], $facet);
            } else {
                Exception::debug('VAR',"Skipping $address");
            }
        }
        $facet->close();
    }

    /**
     * Updates the mosaic with changed or added shards.
     *
     * @return void
     */
    public function updateMosaic(): void
    {
        Exception::debug('VAR', 'Starting updateMosaic');
        foreach ($this->checkList as $address) {
            $shard = $this->mosaic[$address] ?? null;
            if ($shard) {
                $oldValue = $this->getVar("Input::$address");
                if ($oldValue === null) {
                    $this->insertShard($shard);
                } else {
                    $newValue = $shard->hasChanged($oldValue);
                    if ($newValue !== null) {
                        $this->updateShard($shard, $newValue);
                    }
                }
            }
        }
    }

    /**
     * Generates HTML to store a shard.
     *
     * @param Shard $shard The Shard to store.
     * @param Facet $facet
     * @return void
     */
    public function storeShard(Shard $shard, Facet $facet): void
    {
        $address = $shard->address;
        $facet->using($shard)->out("<input type='hidden' name='{$address}' value='{{Glyph::deflate()}}'>");
    }

    /**
     * Generates OOB HTML to insert a new shard.
     *
     * @param Shard $shard The Shard to insert.
     * @return void
     */
    public function insertShard(Shard $shard): void
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
     *
     * @param Shard $shard The Shard to update.
     * @param string|null $newValue The new deflated value.
     * @return void
     */
    public function updateShard(Shard $shard, ?string $newValue = null): void
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
     *
     * @param Shard $shard The Shard to remove.
     * @return void
     */
    public function removeShard(Shard $shard): void
    {
        $address = $shard->address;
        (new Facet($shard))
            ->oob()
            ->open("<div {{hx-swap-oob='delete:#{{Pane::name}}-mos}} [name={$address}]'>")
            ->close();
    }

    /**
     * Adds a shard to the mosaic.
     *
     * @param mixed $json The JSON data or Shard to add.
     * @param string|null $id The Shard ID.
     * @param string|null $inlay The inlay name.
     * @return void
     */
    public function addShard($json, ?string $id = null, ?string $inlay = null): void
    {
        if (empty($json->address)) {
            $inlay = $inlay ?? Facet::inlay();
            $id = $id ?? uniqid('__array_');
            $json->address = $this->makeAddress(['id'=>$id, 'inlay'=>$inlay]);
        }
        Exception::debug("SHARD","Adding shard at " . $json->address);
        $this->mosaic[$json->address] = $json;
    }

    /**
     * Deletes a shard from the mosaic.
     *
     * @param object $shard The Shard to delete.
     * @return void
     */
    public function delShard($shard): void
    {
        $this->removeShard($shard);
        unset($this->mosaic[$shard->address]);
        unset($this->checkList[$shard->address]);
    }

    /**
     * Checks a Shard and adds it to the checkList if not already present.
     *
     * @param object $shard The Shard to check.
     * @return void
     */
    public function checkShard($shard): void
    {
        if ($this->trackChanges) {
            $this->checkList[$shard->address] = true;
        }
    }

    /**
     * Finds a single Shard by field and value.
     *
     * @param string|null $field The field name to search.
     * @param mixed $value The value to match.
     * @param string $inlay The inlay to filter by.
     * @param string $op The comparison operator.
     * @return string|null The Shard ID if found, null otherwise.
     */
    public function findShard(string $field, $value, ?string $inlay = null, string $op = '='): ?string
    {
        $inlay = $inlay ?? Facet::inlay();
        foreach ($this->mosaic as $address => $element) {
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
     *
     * @param string|null $field The field name to search.
     * @param mixed $value The value to match.
     * @param string $inlay The inlay name to filter by.
     * @param string $op The comparison operator.
     * @return array An array of matching Shards, indexed by ID.
     */
    public function findShards(string $field, $value, ?string $inlay = null, string $op = '='): array
    {
        $inlay = $inlay ?? Facet::inlay();
        $found = [];
        foreach ($this->mosaic as $address => $element) {
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
