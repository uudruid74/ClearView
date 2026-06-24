<?php

namespace ClearView;

use ClearView\Exception;
use ClearView\Mosaic;
use ClearView\Facet;
use ClearView\ClearView;
use ClearView\Pane;
use ClearView\Element;

/**
 * Core data object representing a node in the ClearView hierarchy.
 * @implements \Stringable
 * @implements \ArrayAccess
 * @implements \JsonSerializable
 * @implements \Iterator
 */
class Shard implements \Stringable, \ArrayAccess, \JsonSerializable, \Iterator
{
    /**
     * @var array<string,mixed> Data storage for fields like glyph, value, children.
     */
    protected $data = [];

    /**
     * @var string Primary field name for the Shard's main value (e.g., 'value').
     */
    protected $primaryField = 'value';

    /**
     * @var string Unique address in the Mosaic for this Shard.
     */
    public $address;

    /**
     * @var int Current position for Iterator interface.
     */
    private int $iteratorPosition = 0;

    protected bool $canonicalId = false;

    /** Input type for HTML strings. */
    public const HTML = 'html';

    /** Input type for JSON strings. */
    public const JSON = 'json';

    /** Input type for mangled JSON strings. */
    public const MANGLED = 'mangled';

    /** Input type for view-based loading. */
    public const VIEW = 'view';

    /**
     * Constructs a Shard from input data.
     * @param mixed $obj Input data (array, string, or null).
     * @param string|null $primaryField Primary field name (e.g., 'value').
     * @param string|null $named Optional name for the Shard.
     */
    public function __construct($obj = null, ?string $primaryField = null, ?string $named = null)
    {

        $obj = is_array($obj) ? $obj : ['value' => $obj];
        $loadView = $this->__loadExternal ?? $obj['__loadExternal'] ?? null;
        if (!empty($loadView)) {
            Exception::debug('GLYPH',"Loading external data from loadExternal=" . $loadView);
	    Mosaic::initArray($obj, self::toArray(Mosaic::getVar($loadView)));
        }
	// Text-only nodes are anonymous — no Mosaic storage
	if (array_key_exists('text',$obj) && array_key_exists('__pF', $obj) && $obj['__pF'] == 'text') {
	    // anonymous — no name, no Mosaic storage
	} else {
	    // If id="#" → expand to canonical form on output.
	    // Store the name as the Mosaic key so References can
	    // resolve via Mosaic index($inlay, $name).
	    if (($obj['id'] ?? null) === '#') {
	        if (empty($obj['name'])) {
	            $obj['name'] = $this->createid($obj);
	        }
	        $obj['id'] = $obj['name'];
	        $this->canonicalId = true;
	    }
	    $obj['name'] = $obj['name'] ?? $this->name ?? $named;
	    $obj['id'] = $obj['id'] ?? $this->id ?? $this->createid($obj);
	}
        if (isset($obj['__pF'])) {
            $primaryField = $obj['__pF'];
            unset($obj['__pF']);
        } elseif ($primaryField !== null) {
            $this->primaryField = $primaryField;
        }
        $this->setRawFields($obj);
        if (!$this->isAnonymous()) {
            $this->address = $obj['__address'] = Mosaic::makeAddress($this);
            Mosaic::addShard($this);
        }
        $this->init();
    }

    /**
     * Converts a Shard or scalar value to an array for Mosaic merging.
     * Shards return their field data directly. Scalar values are parsed
     * through fromhtml() without any wrapper element — fragments and
     * other glyphs are rendered as-is, not mangled into <article> tags.
     * @param mixed $shard Shard instance or scalar value.
     * @return array The field data or parsed array.
     */
    private static function toArray ($shard)
    {
        if ($shard instanceof Shard) {
            return $shard->getFieldData() ?? [];
        } else {
            return jsonmangler::fromhtml((string)$shard);
        }
    }

    /**
     * Generates a unique ID for the Shard.
     * @param array $object Input data containing id, name, or glyph.
     * @return string The generated ID.
     */
    public function createid(array $object): string
    {
        if (isset($object['id']) && $object['id'] !== '#') {
            return $object['id'];
        }
        // Use explicit name as the id when available
        if (!empty($object['name'])) {
            return $object['name'];
        }
        // id="#" with no name: generate a synthetic name for Mosaic addressing
        if (isset($object['id']) && $object['id'] === '#') {
            $glyph = $object['glyph'] ?? 'Shard';
            return '_' . $glyph . '_' . bin2hex(random_bytes(2));
        }
        // Truly unnamed with no id: return empty — this Shard stays anonymous
        return '';
    }

    /** Renders the children of the Shard. */


    public function renderChildren(): void
    {
        Exception::debug('TRACE',"renderChildren called ");
        $children = $this->data['children'] ?? null;
        if (!isset($children)) {
            return;
        }
        if (is_string($children)) {
            $children = self::loadShard($children);
        }
        foreach ($children as $item) {
            if (is_object($item)) {
                $item->html();
            } else {
                $item = self::loadShard($item);
                if (is_string($item)) {
                    echo Facet::_($item);
                } else {
                    $item->html();
                }
            }
        }
    }

    /** Renders the Shard as HTML. */


    public function html(): void
    {
        (new Facet($this))
            ->debug("Shard::html called for {{id}}")
            ->html();
    }

    /**
     * Returns the Shard's HTML as a string.
     * @return string The rendered HTML.
     */
    public function getHtml(): string
    {
        return (new Facet($this))
            ->record()
            ->html()
            ->close();
    }

    /**
     * Placeholder for adding styles.
     * @return Shard This Shard instance.
     */
    public function style()
    {
        return $this;
    }

    /**
     * Placeholder for adding scripts.
     * @return Shard This Shard instance.
     */
    public function script()
    {
        return $this;
    }

    /**
     * Loads a Shard from input data.
     * @param mixed $obj Input data (array, string, object).
     * @param string|null $id Optional ID.
     * @param string|null $inlay Optional inlay context.
     * @param string|null $glyph Optional glyph name.
     * @param string|null $from Input type (HTML, JSON, MANGLED, VIEW).
     * @return Shard The loaded Shard.
     * @throws Exception On invalid input or recursion in VIEW mode.
     */
    public static function loadShard($obj, ?string $id = null, ?string $inlay = null, ?string $glyph = null, ?string $from = null): Shard
    {
        if (is_string($obj) && $from !== null) {
            switch ($from) {
                case self::HTML:
                    $obj = jsonmangler::fromhtml($obj);
                    break;
                case self::JSON:
                    $obj = json_decode($obj, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new Exception("Invalid JSON input");
                    }
                    break;
                case self::MANGLED:
                    $obj = jsonmangler::unmangle($obj);
                    break;
                case self::VIEW:
                    // Prevent recursion by not calling loadView() if from is VIEW
                    break;
                default:
                    throw new Exception("Invalid from type: $from");
            }
        }

        if (is_scalar($obj)) {
            $obj = jsonmangler::unmangle($obj);
        }

        if (!is_array($obj)) {
            throw new Exception("Invalid input type for loadShard: " . gettype($obj));
        }

        $obj['id'] = $obj['id'] ?? $id;
        $obj['inlay'] = $obj['inlay'] ?? $inlay;

        $determinedGlyph = $obj['glyph'] ?? null;
        $determinedGlyph = $determinedGlyph ?? $glyph;

        $primaryField = $obj['__pF'] ?? null;
        if (isset($obj['__pF'])) {
            unset($obj['__pF']);
        }

        if ($determinedGlyph) {
            $classPath = Element::loadGlyph($determinedGlyph);
            if (!$classPath) {
                // Use the default 'glyph' class for unknown elements
                $determinedGlyph = 'glyph';
                $classPath = Element::loadGlyph($determinedGlyph);
            }
            Exception::debug('GLYPH',"Creating classPath $classPath");
            $shard = new $classPath($obj, primaryField: $primaryField);
        } else {
            $shard = new Shard($obj, primaryField: $primaryField);
        }
        $shard->canonicalizeChildren();
        return $shard;
    }

    /**
     * Canonicalizes children: stores named and id="#" children in Mosaic
     * and replaces their tree slots with References. Unnamed children
     * (without id="#") are left as-is (never stored in Mosaic).
     * Recurses into unnamed children's own children arrays so nested named
     * elements are also canonicalized.
     */
    public function canonicalizeChildren(): void
    {
        $children = $this->data['children'] ?? null;
        if (!$children || !is_array($children)) {
            return;
        }
        $inlay = $this->data['inlay'] ?? Mosaic::getVar('Input::inlayname');

        foreach ($children as $i => &$child) {
            if (!is_array($child)) {
                continue;
            }
            // Skip already-canonicalized references — they carry their own
            // _refInlay.  Re-processing them would lose _refInlay when the
            // 'inlay' key was stripped by jsonmangler during a
            // deflate/inflate round-trip through Mosaic.
            if (($child['glyph'] ?? null) === 'reference') {
                continue;
            }
            $hasName = !empty($child['name']);
            $isAutoId = ($child['id'] ?? null) === '#';
            if ($hasName || $isAutoId) {
                // id="#" with no name → generate a stable synthetic name
                if (!$hasName && $isAutoId) {
                    $child['name'] = $this->createid($child);
                }
                // Ensure the child inherits the parent inlay if not set
                $child['inlay'] = $child['inlay'] ?? $inlay;
                // Create a Shard (stores it in Mosaic)
                $childShard = self::loadShard($child);
                // Replace the tree slot with a Reference.
                // Reference is stored with anon inlay so it never
                // registers in Mosaic (would overwrite the target).
                // The real inlay is tucked into _refInlay for resolution.
                $this->data['children'][$i] = [
                    'glyph' => 'reference',
                    'name' => $child['name'],
                    '_refInlay' => $childShard->inlay(),
                ];
            } elseif (!empty($child['children'])) {
                // Unnamed child with nested children — recurse inline
                // without creating a Shard (unnamed items skip Mosaic).
                $this->canonicalizeInline($child['children'], $inlay);
            }
        }
    }

    /**
     * Recursively canonicalizes an inline children array without creating
     * Shards that would be registered in Mosaic. Named and id="#" children
     * found at any depth are stored in Mosaic and replaced with References.
     * @param array &$children Reference to the children array to process.
     * @param string $inlay    The parent inlay to inherit.
     * @param mixed $children Description.
     */
    private function canonicalizeInline(array &$children, string $inlay): void
    {
        foreach ($children as $i => &$child) {
            if (!is_array($child)) {
                continue;
            }
            // Skip already-canonicalized references (same reason as
            // canonicalizeChildren — prevents _refInlay loss on round-trip).
            if (($child['glyph'] ?? null) === 'reference') {
                continue;
            }
            $hasName = !empty($child['name']);
            $isAutoId = ($child['id'] ?? null) === '#';
            if ($hasName || $isAutoId) {
                if (!$hasName && $isAutoId) {
                    $child['name'] = $this->createid($child);
                }
                $child['inlay'] = $child['inlay'] ?? $inlay;
                $childShard = self::loadShard($child);
                $children[$i] = [
                    'glyph' => 'reference',
                    'name' => $child['name'],
                    '_refInlay' => $childShard->inlay(),
                ];
            } elseif (!empty($child['children'])) {
                $this->canonicalizeInline($child['children'], $inlay);
            }
        }
    }

    /**
     * Searchesildren for matching field values.
     * @param string $field Field to search.
     * @param mixed $value Value to match.
     * @param string $operator Comparison operator (e.g., '=', '*=').
     * @param string|null $returnField Optional field to return.
     * @return array Matching values or Shards.
     */
    protected function searchChildren(string $field, $value, string $operator, ?string $returnField = null): array
    {
        $matches = [];
        if ($this->getChildType() === 'string') {
            foreach ($this->data['children'] ?? [] as $item) {
                if ($field === 'value' && QueryParser::compare($item, $value, $operator)) {
                    $matches[] = $returnField ? $item : $item;
                }
            }
        } else {
            foreach ($this->data['children'] ?? [] as $item) {
                $itemValue = $item->getField($field);
                if ($itemValue === null) {
                    continue;
                }
                if (QueryParser::compare($itemValue, $value, $operator)) {
                    $matches[] = $returnField ? $item->getField($returnField) : $item->getVar($field);
                }
            }
        }
        return $matches;
    }

    /**
     * Gets a Mosaic variable.
     * @param string $expression Variable expression.
     * @return mixed The variable value.
     */
    public function getVar(string $expression)
    {
        return Mosaic::getVar($expression);
    }

    /**
     * Gets multiple Mosaic variables.
     * @param string $expression Variable expression.
     * @return array The matching variables.
     */
    public function getVars(string $expression): array
    {
        return Mosaic::getVars($expression);
    }

    /**
     * Sets a Mosaic variable.
     * @param string $var Variable name.
     * @param mixed $value Variable value.
     * @return mixed The set value.
     */
    public function setVar(string $var, $value)
    {
        return Mosaic::setVar($var, $value);
    }

    /**
     * Initializes a Mosaic variable if unset.
     * @param string $var Variable name.
     * @param mixed $value Initial value.
     */
    public function initVar(string $var, $value): void
    {
	Mosaic::initVar($var, $value);
    }

    /**
     * Sets a field value via property access.
     * @param string $name Field name.
     * @param mixed $value Field value.
     */
    public function __set(string $name, $value): void
    {
        if ($name === "value") {
            $name = $this->primaryField;
        }
        $this->setField($name, $value);
    }

    /**
     * Returns the primary field's string value.
     * @return string The primary field value.
     */
    public function __toString(): string
    {
        return (string)($this->data[$this->primaryField] ?? '');
    }

    /**
     * Gets a field value, no queries.  May support later
     * @param string $field Field name.
     * @return mixed The field value.
     */
    public function getField(string $field)
    {
        return $this->data[$field] ?? $this->{$field} ?? null;
    }

    /**
     * Sets a field value.
     * @param string $var Field name.
     * @param mixed $val Field value.
     */
    public function setField(string $var, $val): void
    {
        $this->data[$var] = $val;
	Mosaic::checkShard($this);
    }

    /**
     * Sets a raw field value without processing.
     * @param string $field Field name.
     * @param mixed $value Field value.
     */
    protected function setRawField(string $field, $value): void
    {
        $this->data[$field] = $value;
    }

    /**
     * Sets multiple raw fields.
     * @param array $fields Field-value pairs.
     */
    protected function setRawFields(array $fields): void
    {
        foreach ($fields as $key => $value) {
            if ($value !== null) {
                $this->data[$key] = $value;
            }
        }
    }

    /**
     * Sets the primary field name.
     * @param string $fieldname Field name.
     */
    public function setPrimaryField(string $fieldname): void
    {
        $this->primaryField = $fieldname;
    }

    /**
     * Serializes the Shard to mangled JSON.
     * @return string The mangled JSON string.
     */
    public function deflate(): string
    {
        $data = $this->data;
        if ($this->primaryField !== 'value') {
            $data['__pF'] = $this->primaryField;
        }
        return jsonmangler::mangle($data);
    }

    /**
     * Creates a Shard from mangled JSON.
     * @param string $json Mangled JSON string.
     * @return Shard The inflated Shard.
     */
    public static function inflate(string $json): Shard
    {
        return new Shard(jsonmangler::unmangle($json));
    }

    /** Removes the Shard from Mosaic. */


    public function delVar()
    {
	Mosaic::delShard($this);
        unset($this->data);
    }

    /**
     * Gets a field value via array access.
     * @param mixed $key Field name.
     * @return mixed The field value.
     */
    public function offsetGet($key): mixed
    {
        $key = $key ?? $this->primaryField;
        $value = $this->getField($key);
        if ($key === 'children' && (is_string($value) || $value instanceof Shard)) {
            return [$value];
        }
        return $value;
    }

    /**
     * Sets a field value via array access.
     * @param mixed $key Field name.
     * @param mixed $value Field value.
     */
    public function offsetSet($key, $value): void
    {
        $key = $key ?? $this->primaryField;
        $this->setField($key, $value);
    }

    /**
     * Checks if a field exists.
     * @param mixed $key Field name.
     * @return bool True if the field exists.
     */
    public function offsetExists($key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Unsets a field.
     * @param mixed $key Field name.
     */
    public function offsetUnset($key): void
    {
        unset($this->data[$key]);
	Mosaic::checkShard($this);
    }

    /**
     * Returns the Shard's ID.
     * @return string The ID.
     */
    public function id(): string
    {
        return $this->data['id'] ?? '';
    }

    /**
     * Returns the Shard's inlay context.
     * @return string The inlay.
     */
    public function inlay(): string
    {
        return Mosaic::getVar('Input::inlayname');
    }

    /**
     * Returns true if this Shard has no name — cannot be stored in Mosaic.
     * @return bool
     */
    public function isAnonymous(): bool
    {
        return !isset($this->data['name']);
    }

    /** Renders the Shard's primary field value. */


    public function render()
    {
        echo Facet::_((string)$this); // outputs primaryField
    }

    /**
     * Initializes the Shard.
     * @return Shard This Shard instance.
     */
    public function init()
    {
        return $this;
    }

    /**
     * Checks if the Shard has changed.
     * @return mixed The current serialized value if changed, null otherwise.
     */
    public function hasChanged(): mixed
    {
        $oldValue = Mosaic::isShardStored($this) ? Mosaic::getVar("Input::" . $this->address) : null;
        $currentValue = $this->deflate();
        return ($currentValue !== $oldValue) ? $currentValue : null;
    }

    /**
     * Serializes the Shard to JSON with templating.
     * @return array The serialized data.
     */
    public function jsonSerialize(): array
    {
        return $this->rawJson(true);
    }

    /**
     * Returns JSON with optional templating.
     * @param bool $useTemplate Whether to apply Facet::_() templating.
     * @return array The JSON data.
     */
    public function json(bool $useTemplate = true): array
    {
        return $this->rawJson($useTemplate);
    }

    /**
     * Returns raw JSON without templating.
     * @param bool $useTemplate Whether to apply Facet::_() templating.
     * @return array The JSON data.
     */
    public function rawJson(bool $useTemplate = false): array
    {
        $output = $this->data;
        if (isset($output['children']) && is_array($output['children'])) {
            $output['children'] = array_map(function ($item) use ($useTemplate) {
                if ($item instanceof \JsonSerializable) {
                    return $item->json($useTemplate);
                }
                return $useTemplate && is_string($item) ? Facet::_($item) : $item;
            }, $output['children']);
        }
        foreach ($output as $key => $value) {
            if ($useTemplate && is_string($value)) {
                $output[$key] = Facet::_($value);
            }
        }
        return $output;
    }

    /**
     * Returns a simplified JSON representation of the Shard.
     * Outputs an associative array of the Shard's fields, with each field's value processed appropriately.
     * Arrays of Shards are processed recursively using Facet for stack management. Scalar values and string arrays
     * are included directly. The output contains only the fields, without an outer element name, suitable for forms.
     * @param bool $useTemplate Whether to apply Facet::_() templating to string values (default: false).
     * @return array The simplified JSON data.
     */
    public function simpleJson(bool $useTemplate = false): array
    {
        $output = [];
        foreach ($this->data as $key => $value) {
            if ($key === 'children') {
                $type = $this->getChildType();
                if ($type === 'string') {
                    // String arrays are included as-is, with optional templating
                    $output['children'] = array_map(
                        fn ($item) => $useTemplate ? Facet::_($item) : $item,
                        $value
                    );
                } elseif ($type !== null) {
                    // Shard arrays are processed recursively with Facet
                    $output['children'] = array_map(
                        function ($shard) use ($useTemplate) {
                            return (new Facet($shard))->simpleJson($useTemplate)->close();
                        },
                        $value
                    );
                } else {
                    $output['children'] = [];
                }
            } elseif (is_array($value)) {
                // Handle other array fields (e.g., address), assuming they might contain Shards
                $output[$key] = array_map(
                    function ($item) use ($useTemplate) {
                        if ($item instanceof Shard) {
                            // Recursive call for Shards using Facet
                            return (new Facet($item))->simpleJson($useTemplate)->close();
                        }
                        // Non-Shard items (e.g., strings) are included directly
                        return $useTemplate && is_string($item) ? Facet::_($item) : $item;
                    },
                    $value
                );
            } else {
                // Scalar values are included directly, with optional templating for strings
                $output[$key] = $useTemplate && is_string($value) ? Facet::_($value) : $value;
            }
        }
        return $output;
    }

    /**
     * Sets $this->debug to be of the Shard tag
     * @param string $msg Debug message.
     * @param int $depth Stack trace depth.
     * @return Shard This Shard instance.
     */
    public function debug(string $msg, int $depth = 2)
    {
        Exception::debug('SHARD', $msg, $depth);
        return $this;
    }

    /**
     * Sets multiple fields.
     * @param array $arr Field-value pairs.
     */
    public function setFields(array $arr): void
    {
        foreach ($arr as $key => $value) {
            if ($value !== null) {
                $this->setField($key, $value);
            }
        }
    }

    /**
     * Checks if a field exists.
     * @param string $var Field name.
     * @return bool True if the field exists.
     */
    public function hasField(string $var): bool
    {
        return array_key_exists($var, $this->data);
    }

    /**
     * Check if field is blank
     * @param string $var Field name
     * @return book True if null/empty
     */
    public function isEmpty(string $var): bool
    {
        return !array_key_exists($var, $this->data) || empty($this->data[$var]);
    }

    /**
     * Checks if a field is missing.
     * @param string $var Field name.
     * @return bool True if the field is missing.
     */
    public function missingField(string $var): bool
    {
        return !$this->hasField($var) || $this->isEmpty($var);
    }

    /**
     * Initializes a field if     *
     * @param string $var Field name.
     * @param mixed $value Initial value.
     */
    public function initField(string $var, $value): void
    {
        if (!isset($this->data[$var])) {
            $this->setField($var, $value);
        }
    }

    /**
     * Initializes a raw field if unset.
     * @param string $var Field name.
     * @param mixed $value Initial value.
     */
    protected function initRawField(string $var, $value): void
    {
        if (!isset($this->data[$var])) {
            $this->setRawField($var, $value);
        }
    }

    /**
     * Initializes multiple fields if unset.
     * @param array $array Field-value pairs.
     */
    public function initFields(array $array): void
    {
        foreach ($array as $varname => $value) {
            if ($value !== null) {
                $this->initRawField($varname, $value);
            }
        }
	Mosaic::checkShard($this);
    }

    /**
     * Bulk write to Mosaic variables.
     * Replaces the old setVars(). Delegates to Mosaic fill().
     * @param array $arr Variable-value pairs.
     * @param string|null $inlay Inlay context.
     */
    public function fill(array $arr, ?string $inlay = null): void
    {
        foreach ($arr as $key => $value) {
            if ($key === 'children') {
                $this->replaceChildren($value);
            } else {
		                Mosaic::setVar($key, $value, $inlay);
            }
        }
    }

    /**
     * Deletes multiple Mosaic variables.
     * @param mixed $arr Variables to delete.
     * @param string|null $inlay Inlay context.
     */
    public function delVars($arr, ?string $inlay = null): void
    {
	    Mosaic::delVars($arr, $inlay);
    }

    /**
     * Returns all field data.
     * @return array The field data.
     */
    public function getFieldData(): array
    {
        return $this->data;
    }

    /**
     * Adds content to the children field, enforcing type consistency.
     * If new content type differs from existing children, upgrades to Shards.
     * @param mixed $content Content to add (string, Shard, or array).
     * @return void
     */
    public function addChildren($content): void
    {
        $current = $this->data['children'] ?? [];
        $new = is_array($content) ? $content : [$content];
        if (!empty($current) && !empty($new)) {
            $existingType = $this->getChildType();
            $newFirst = reset($new);
            $newType = is_string($newFirst) ? 'string' : (is_object($newFirst) ? get_class($newFirst) : gettype($newFirst));
            if ($existingType !== $newType) {
                $new = array_map(fn($v) => is_string($v) ? Shard::loadShard($v) : $v, $new);
            }
        }
        $this->data['children'] = array_merge($current, $new);
    }

    /**
     * Replaces children, normalizing all items to Shards.
     * @param mixed $newChildren Single item or array of items.
     * @return void
     */
    public function replaceChildren(mixed $newChildren): void
    {
        $items = is_array($newChildren) ? $newChildren : [$newChildren];
        $this->data['children'] = array_map(
            fn($item) => $item instanceof Shard ? $item : Shard::loadShard($item),
            $items
        );
    }

    /**
     * Returns the child type by inspecting the children array.
     * @return string|null The type: 'string', class name, or null for no children.
     */
    public function getChildType(): ?string
    {
        $children = $this->data['children'] ?? null;
        if ($children === null || $children === []) {
            return null;
        }
        $first = $children[array_key_first($children)];
        if (is_string($first))  return 'string';
        if ($first instanceof Shard)  return get_class($first);
        if (is_array($first))   return 'array';
        return gettype($first);
    }

    /** Resets the iterator position. */


    public function rewind(): void
    {
        $this->iteratorPosition = 0;
    }

    /**
     * Returns the current iterator item.
     * @return mixed The current content item.
     */
    public function current(): mixed
    {
        $children = $this->data['children'] ?? null;
        if (!isset($children)) {
            return null;
        }
        return $children[$this->iteratorPosition] ?? null;
    }

    /**
     * Returns the current iterator key.
     * @return mixed The current key.
     */
    public function key(): mixed
    {
        return $this->iteratorPosition;
    }

    /** Advances the iterator. */


    public function next(): void
    {
        $this->iteratorPosition++;
    }

    /**
     * Checks if the current iterator position is valid.
     * @return bool True if valid.
     */
    public function valid(): bool
    {
        $children= $this->data['children'] ?? null;
        if (!isset($children)) {
            return false;
        }
        return isset($children[$this->iteratorPosition]);
    }

    /**
     * Searches the fields of the Shard based on a query.
     * @param string $query The query string for searching fields (wildcard or regex).
     * @return array An array of matching field names or values.
     */
    public function getFields(string $query): array
    {
        if (strpos($query, '/') === 0) {
            // Regex search
            return $this->iterateFields(
                callback: fn ($value, $key) => $key,
                delim: null,
                filter: $query
            );
        } else {
            // Wildcard search
            $regex = $this->wildcardToRegex($query);
            return $this->iterateFields(
                callback: fn ($value, $key) => $key,
                delim: null,
                filter: $regex
            );
        }
    }

    /**
     * Converts a wildcard pattern to a regex pattern.
     * @param string $wildcard The wildcard pattern to convert.
     * @return string The equivalent regex pattern.
     */
    private function wildcardToRegex(string $wildcard): string
    {
        $regex = preg_quote($wildcard, '/');
        $regex = str_replace('\*', '.*', $regex);
        return '/^' . $regex . '$/';
    }

    /**
     * Searches or indexes the '__children' field of the Shard.
     * @param string $query The query string for searching or indexing children
     * @return array An array of matching strings or Shards.
     */
    public function getChildren(string $query): array
    {
        $children = $this->data['children'] ?? [];
        if (empty($children)) {
            return [];
        }

        $type = $this->getChildType();
        if ($type === 'string') {
            if (strpos($query, '=') !== false) {
                [$_, $value] = explode('=', $query, 2);
                return array_filter($children, fn ($item) => $item == $value);
            } else {
                return array_filter($children, fn ($item) => strpos($item, $query) !== false);
            }
        } else {
            if (strpos($query, '=') !== false) {
                [$field, $value] = explode('=', $query, 2);
                if (strpos($field, '.') !== false) {
                    [$field, $subfield] = explode('.', $field, 2);
                } else {
                    $subfield = $this->primaryField;
                }
                return array_filter($children, function ($shard) use ($subfield, $value) {
                    $itemValue = $shard->getField($subfield);
                    return $itemValue == $value;
                });
            } else {
                return array_filter($children, function ($shard) use ($query) {
                    $itemValue = $shard->getField($this->primaryField);
                    return strpos($itemValue, $query) !== false;
                });
            }
        }
        return [];
    }

    /**
     * Iterates over the fields of the Shard, applying a callback to each matching field.
     * @param callable $callback The callback function to apply to each field (receives value and key).
     * @param string|null $delim The delimiter to join results (default: ' '). Null to return an array.
     * @param string|null $filter The regex pattern to filter field names (optional).
     * @return string|array The concatenated results or array of results.
     */
    public function iterateFields(callable $callback, ?string $delim = ' ', ?string $filter = null): mixed
    {
        $fields = $this->getFieldData();
        $results = [];

        foreach ($fields as $key => $value) {
            if ($filter !== null && !preg_match($filter, $key)) {
                continue;
            }
            $result = $callback($value, $key);
            if ($result !== null) {
                $results[] = $result;
            }
        }

        if ($delim === null) {
            return $results;
        }
        return implode($delim, $results);
    }
}
