<?php

namespace ClearView;

use ClearView\ClearView;
use ClearView\Mosaic;
use ClearView\Exception;
use ClearView\Shard;
use ClearView\Pane;

/**
 * Manages HTML rendering and template processing for Shards in ClearView.
 *
 * Facet is a rendering engine that wraps Shards, handling HTML tag management, template expansion, and output
 * buffering for client-side synchronization via HTMX. It maintains a static tag stack to track open/closed
 * elements and objects, supports nested out-of-band (OOB) updates and recording via reference counts, and
 * processes template expressions (e.g., `{{inlay::var}}`) using Mosaic and ClearView. Facet instances chain
 * operations, render Shards, and cascade method calls to the current element, ClearView, or Mosaic. It
 * integrates with ProcessWire for dynamic content and debugging, and supports session data access via the
 * Session Crystal (`ClearView::Session.key`). The public `onClose()` method allows registering custom closure
 * methods or HTML tags, which are executed or output when the stack is popped, enabling powerful recursive
 * rendering or element-specific finishing logic. A static `data[]` array stores global variables for fallback
 * resolution in template processing. The `jsonmangler` library is a hard dependency for inflating and
 * deflating Shards in template processing.
 *
 * @see \ClearView\Shard
 * @see \ClearView\Mosaic
 * @see \ClearView\ClearView
 * @see \ClearView\Session
 */
class Facet
{
    /** @var array Stack for tracking open HTML tags, objects, or method markers (e.g., ->stopOOB) */
    private static $tagstack = [];

    /** @var int The stack position when this Facet instance was created */
    private $position;

    /** @var string|null The string or method to call after calling the next close() */
    private ?string $afterClose;

    /** @var int Reference count for active out-of-band (OOB) buffers */
    private static $oobCount = 0;

    /** @var int Reference count for active recording buffers */
    private static $recordCount = 0;

    /** @var int Reference count for elements being rendered within a container */
    private static $containedCount = 0;

    /** @var array Global variables for template resolution fallback */
    private static $data = [];

    /**
     * Initializes a Facet instance, optionally opening a tag or using an object.
     *
     * Sets up a new Facet instance, recording its stack position and optionally processing an opening tag
     * or object. The instance tracks its position in the tag stack for proper closing.
     *
     * @param mixed|null $open The opening HTML tag, Shard, or object to render (optional).
     * @param array|null $match Conditions to check before processing (optional).
     * @param array|null $unless Conditions to check for false before processing (optional).
     */
    public function __construct($open = null, ?array $match = null, ?array $unless = null)
    {
        $this->position = count(self::$tagstack);
        $this->afterClose = null;
        Exception::debug('FACET', $this->_p("***** Facet created *****"));
        if (isset($open)) {
            if (is_object($open)) {
                $this->using($open);
            } else {
                $this->open($open, null, $match, $unless);
            }
        }
    }

    /**
     * Gets the current target element or creator.
     *
     * Retrieves the top object on the tag stack, or the ClearView creator if the stack is empty or contains
     * no objects. The target is used for method calls and field access during rendering.
     *
     * @return object The current Shard, object, or ClearView creator.
     */
    public static function me()
    {
        if (empty(self::$tagstack)) {
            return ClearView::paneobj();
        }
        $targetIdx = count(self::$tagstack) - 1;
        while ($targetIdx >= 0 && !is_object(self::$tagstack[$targetIdx])) {
            $targetIdx--;
        }
        if ($targetIdx >= 0) {
            return self::$tagstack[$targetIdx];
        }
        Exception::debug('FACET', "Facet::me() returning **NULL** (no object found)");
        return null;
    }

    /**
     * Gets the ID of the current element.
     *
     * Retrieves the ID of the current target element via me()->id().
     *
     * @return string The ID of the current element.
     */
    public function id()
    {
        return self::me()->id() ?? Mosaic::getVar('Input::panename');
    }

    /**
     * Gets the inlay of the current element.
     *
     * Retrieves the inlay of the current target element via me()->inlay().
     *
     * @return string The inlay of the current element.
     */
    public static function inlay()
    {
        return self::me()->inlay() ?? Mosaic::getVar('Input::inlayname');
    }

    /**
     * Processes a template string or object, condensing whitespace and handling nested expressions.
     *
     * Expands template strings (e.g., `{{inlay::var}}`) or converts objects/arrays to JSON via jsonmangler.
     * Collapses whitespace and processes nested `{{...}}` expressions recursively. Falls back to the static
     * `data[]` array if a variable is not found via the current element or Mosaic.
     *
     * @param mixed $string The template string, object, or array to process.
     * @param array|null $locals Local variables for template variable lookup (optional).
     * @return string|null The processed template string or mangled JSON, or null if input is unset.
     */
    public static function _($string, $locals = null)
    {
        if (!isset($string)) {
            return null;
        }
        if (is_object($string) || is_array($string)) {
            $string = jsonmangler::mangle($string);
        }
        // Collapse whitespace
        $string = preg_replace('/\s+/', ' ', $string);
        // Process nested {{...}} pairs
        if (str_contains($string, '{{')) {
            return QueryParser::processTemplate($string, $locals, self::me()->offsetGet('ClearView::inlay'));
        }
        return $string;
    }

    /**
     * Checks if rendering conditions are met.
     *
     * Evaluates rendering conditions, including field presence, equality checks, OOB state, recording state,
     * and contained state. Supports single boolean conditions, equality checks, or triadic comparisons
     * (value, operator, expected) via the unified QueryParser::compare() method. The `unless` parameter inverts
     * the logic, requiring conditions to evaluate to false.
     *
     * @param array|null $match The conditions to check for true (optional).
     * @param array|null $unless The conditions to check for false (optional).
     * @param bool $unlessContained If true, skips rendering if the element is contained (optional).
     * @param bool $isOOB If true, renders only if OOB is active and not contained (optional).
     * @param bool $isRecording If true, renders only if recording is active (optional).
     * @return bool True if all conditions pass, false otherwise.
     */
    private function checkQualifiers(?array $match = null, ?array $unless = null, bool $unlessContained = false, bool $isOOB = false, bool $isRecording = false): bool
    {
        if ($unlessContained && self::isContained()) {
            return false;
        }
        if ($isOOB && (!self::isOOB() || self::isContained())) {
            return false;
        }
        if ($isRecording && !self::isRecording()) {
            return false;
        }
        if ($match !== null) {
            foreach ($match as $condition) {
                if (!is_array($condition)) {
                    return false;
                }
                if (count($condition) === 1) {
                    if (!(bool)$condition[0]) {
                        return false;
                    }
                } elseif (count($condition) === 2) {
                    if ($condition[0] !== $condition[1]) {
                        return false;
                    }
                } elseif (count($condition) === 3) {
                    $value = $condition[0];
                    $operator = $condition[1];
                    $expected = $condition[2];
                    if (!QueryParser::compare($value, $operator, $expected)) {
                        return false;
                    }
                } else {
                    return false;
                }
            }
        }
        if ($unless !== null) {
            foreach ($unless as $condition) {
                if (!is_array($condition)) {
                    return false;
                }
                if (count($condition) === 1) {
                    if ((bool)$condition[0]) {
                        return false;
                    }
                } elseif (count($condition) === 2) {
                    if ($condition[0] === $condition[1]) {
                        return false;
                    }
                } elseif (count($condition) === 3) {
                    $value = $condition[0];
                    $operator = $condition[1];
                    $expected = $condition[2];
                    if (QueryParser::compare($value, $operator, $expected)) {
                        return false;
                    }
                } else {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Gets a field value from the static data array.
     *
     * Retrieves a value from the static `data[]` array, returning a default if the field is not set.
     *
     * @param string $key The field name to retrieve.
     * @param mixed $default The default value to return if the field is not set.
     * @return mixed The field value or default.
     */
    public static function getField($key, $default = null)
    {
        return self::$data[$key] ?? $default;
    }

    /**
     * Sets a field value in the static data array.
     *
     * Sets a value in the static `data[]` array. If the field already exists, pushes its old value and a
     * `==fieldname` tag to restore it on stack pop. If the field is new, pushes a `0=fieldname` tag to
     * unset it on stack pop.
     *
     * @param string $key The field name to set.
     * @param mixed $value The value to set.
     * @return self For method chaining.
     */
    public function setField($key, $value)
    {
        if (array_key_exists($key, self::$data)) {
            $this->onClose("==$key");
            $this->onClose(self::$data[$key]);
        } else {
            $this->onClose("0=$key");
        }
        self::$data[$key] = $value;
        return $this;
    }

    /**
     * Increments a field value in the static data array.
     *
     * Increments a numeric field in `data[]` (or initializes it to 1 if unset) and pushes a `--fieldname`
     * tag to decrement it on stack pop.
     *
     * @param string $field The field name to increment.
     * @return self For method chaining.
     */
    public function incField($field)
    {
        self::$data[$field] = (self::$data[$field] ?? 0) + 1;
        $this->onClose("--$field");
        return $this;
    }

    /**
     * Decrements a field value in the static data array.
     *
     * Decrements a numeric field in `data[]` (or initializes it to -1 if unset) and pushes a `++fieldname`
     * tag to increment it on stack pop.
     *
     * @param string $field The field name to decrement.
     * @return self For method chaining.
     */
    public function decField($field)
    {
        self::$data[$field] = (self::$data[$field] ?? 0) - 1;
        $this->onClose("++$field");
        return $this;
    }

    /**
     * Opens an HTML tag, renders a Shard, or uses an object, pushing to tag stack if not self-closing.
     *
     * Starts rendering an HTML tag, Shard, or object. For Shards, calls `render()` to keep tags open. Handles
     * self-closing tags (e.g., `<input>`) and pushes closing tags or objects to the stack for later closing.
     *
     * @param mixed $open The opening tag, Shard, or object to render.
     * @param string|null $close The closing tag (optional, auto-derived if null).
     * @param array|null $match Conditions to check for true before rendering (optional).
     * @param array|null $unless Conditions to check for false before rendering (optional).
     * @param bool $unlessContained If true, skips rendering if the element is contained (optional).
     * @param bool $isOOB If true, renders only if OOB is active and not contained (optional).
     * @param bool $isRecording If true, renders only if recording is active (optional).
     * @return self For method chaining.
     * @throws ClearView::Exception If the opening tag is invalid.
     */
    public function open($open, $close = null, ?array $match = null, ?array $unless = null, bool $unlessContained = false, bool $isOOB = false, bool $isRecording = false)
    {
        $open = self::_($open);
        if (!$this->checkQualifiers($match, $unless, $unlessContained, $isOOB, $isRecording)) {
            return $this;
        }
        if (is_array($open)) {
            $open = Shard::loadShard($open);
        }
        if ($open instanceof Shard) {
            $open->render();
            return $this;
        }
        if ($close === null) {
            if (preg_match('/<([a-zA-Z0-9]+)/', $open, $matches)) {
                if (in_array($matches[1], ['input', 'img', 'br'])) {
                    $this->out($open, $match, $unless, $unlessContained, $isOOB, $isRecording);
                    return $this;
                }
                $close = '</' . $matches[1] . '>';
            } else {
                throw new Exception("Invalid opening tag: $open");
            }
        }
        $this->using($close);
        $this->out($open, $match, $unless, $unlessContained, $isOOB, $isRecording);
        return $this;
    }

    /**
     * The "forward" command just performs the named method on the target object immediately
     * @param $command The command to execute
     * @param $arguments A list of arguments
     * @return $this for chaining
     */
    public function forward ($command,...$args)
    {
        self::me()->{$command}(...$args);
        return $this;
    }

    /**
     * Formats the Facet instance for debugging.
     * Generates a debug string showing the current stack position, and an optional message.
     * @param string|null $msg Optional message to append.
     * @return string Formatted debug string (e.g., `[pos/count] msg`).
     */
    public function _p($msg = null)
    {
        $pos = $this->position ?? '-';
        $count = count(self::$tagstack);
        return "[ {$pos}/{$count} ] {$msg}";
    }

    /**
     * Static version of _p() for debugging.
     * Generates a debug string for the current element without an instance context.
     * @param string|null $msg Optional message to append.
     * @return string Formatted debug string (e.g., `[-/count] msg`).
     */
    public static function print($msg = null)
    {
        $pos = '-';
        $count = count(self::$tagstack);
        return "[ {$pos}/{$count} ] {$msg}";
    }

    /**
     * Pushes an object or string to the tag stack.
     *
     * Sets the current rendering context to an object or pushes a closing tag (e.g., `</div>`) to the stack.
     * Acts as syntactic sugar for pushing to `$tagstack`, similar to `onClose()`.
     *
     * @param string|object $close The object or closing tag to push.
     * @return self For method chaining.
     */
    public function using($close)
    {
        self::$tagstack[] = $close ?? '';
        return $this;
    }

    /**
     * Registers a method or HTML to be processed when the stack is popped to this position.
     *
     * Pushes a closing HTML tag (e.g., `</div>`) or a method call (prefixed with `->`, e.g., `->stopOOB`) to
     * the tag stack. When popped via `popto()` or `close()`, HTML is output, and `->method` triggers a method
     * call on the Facet or forwarded via `__call()`.
     *
     * @param string $method The HTML tag or method name (prefixed with `->` for methods).
     * @return self For method chaining.
     */
    public function onClose(string $method)
    {
        self::$tagstack[] = $method;
        return $this;
    }

    /**
     * Registers a method or HTML to be processed after the next close().
     *
     * Sets a string or method to be executed after the next `close()` call, before returning to the caller.
     *
     * @param string $method The HTML tag or method name to process after closing.
     * @return self For method chaining.
     */
    public function afterClose(string $method)
    {
        $this->afterClose = $method;
        return $this;
    }

    /**
     * Static version of using().
     *
     * Pushes an object or closing tag to the tag stack without an instance.
     *
     * @param string|object $close The object or closing tag to push.
     * @return mixed The pushed value.
     */
    public static function use($close)
    {
        self::$tagstack[] = $close ?? '';
        return $close;
    }

    /**
     * Pops the tag stack back to a specific position, processing closing tags or method markers.
     *
     * Closes open tags or restores the stack to a previous state, outputting closing tags or handling method
     * markers (`->method`), field operations (`++field`, `--field`, `0=field`, `==field`). Returns non-$this
     * results (e.g., recorded strings) if applicable.
     *
     * @param int $position The stack position to restore to.
     * @return mixed The Facet instance or a method result (e.g., recorded string).
     */
    private function popto($position)
    {
        while (count(self::$tagstack) > $position) {
            $poppedTag = array_pop(self::$tagstack);
            if (is_string($poppedTag)) {
                $result = $this->handleTag($poppedTag);
                if ($result !== $this) {
                    return $result;
                }
            }
        }
        return $this;
    }

    /**
     * Handles a popped tag from the stack.
     *
     * Processes a popped tag, outputting HTML, calling a method for `->method`, or handling field operations
     * for `++field`, `--field`, `0=field`, or `==field`.
     *
     * @param string $poppedTag The tag or operation to handle.
     * @return mixed The Facet instance or method result.
     */
    public function handleTag($poppedTag)
    {
        if (strlen($poppedTag) >= 2) {
            $op = substr($poppedTag, 0, 2);
            $field = substr($poppedTag, 2);
            switch ($op) {
                case '->':
                    $method = $field;
                    if (method_exists($this, $method)) {
                        return $this->$method();
                    }
                    break;
                case '++':
                    self::$data[$field] = (self::$data[$field] ?? 0) + 1;
                    break;
                case '--':
                    self::$data[$field] = (self::$data[$field] ?? 0) - 1;
                    break;
                case '0=':
                    unset(self::$data[$field]);
                    break;
                case '==':
                    $oldValue = array_pop(self::$tagstack);
                    self::$data[$field] = $oldValue;
                    break;
                default:
                    echo $poppedTag;
                    break;
            }
        } else {
            echo $poppedTag;
        }
        return $this;
    }

    /**
     * Starts out-of-band (OOB) output buffering.
     *
     * Initiates OOB buffering, incrementing the OOB reference count and registering `->stopOOB` via
     * `onClose()` to be called when the stack is popped.
     *
     * @return self For method chaining.
     */
    public function oob()
    {
        ob_start();
        self::$oobCount++;
        $this->onClose('->stopOOB');
        return $this;
    }

    /**
     * Stops out-of-band (OOB) output buffering.
     *
     * Terminates an OOB buffer, decrementing the OOB reference count, capturing the buffer contents, and
     * sending them to ClearView::sendOOB() for HTMX delivery.
     *
     * @return self For method chaining.
     */
    public function stopOOB()
    {
        $contents = ob_get_contents();
        ob_end_clean();
        self::$oobCount--;
        $this['Crystal::ClearView']->sendOOB($contents);
        return $this;
    }

    /**
     * Starts recording output for later retrieval.
     *
     * Initiates recording output, incrementing the recording reference count and registering `->stopRecording`
     * via `onClose()` to be called when the stack is popped.
     *
     * @return self For method chaining.
     */
    public function record()
    {
        ob_start();
        self::$recordCount++;
        $this->onClose('->stopRecording');
        return $this;
    }

    /**
     * Stops recording output and returns the captured content.
     *
     * Terminates a recording buffer, decrementing the recording reference count and returning the captured
     * content as a string.
     *
     * @return string The captured output.
     */
    public function stopRecording()
    {
        $contents = ob_get_contents();
        ob_end_clean();
        self::$recordCount--;
        return $contents;
    }

    /**
     * Checks if any out-of-band (OOB) buffering is active.
     *
     * Determines if any OOB buffers are currently open, based on the OOB reference count. Returns false if
     * the element is contained to prevent nested OOB rendering.
     *
     * @return bool True if any OOB buffers are active and not contained, false otherwise.
     */
    public static function isOOB()
    {
        return self::$oobCount > 0 && !self::isContained();
    }

    /**
     * Checks if any output recording is active.
     *
     * Determines if any recording buffers are currently open, based on the recording reference count.
     *
     * @return bool True if any recording buffers are active, false otherwise.
     */
    public static function isRecording()
    {
        return self::$recordCount > 0;
    }

    /**
     * Checks if the current element is contained within another element.
     *
     * Determines if the element is being rendered as part of a container's contents, based on the contained
     * reference count.
     *
     * @return bool True if the element is contained, false otherwise.
     */
    public static function isContained()
    {
        return self::$containedCount > 0;
    }

    // ── Mosaic lifecycle management ─────────────────────────

    /**
     * Swaps the current Mosaic for a new one, pushing the old onto the
     * Facet tag stack for automatic restoration on close().
     *
     * @param array $options Mosaic::load() options (loadCrystals, loadInputData, etc.)
     * @return self For method chaining.
     */
    public function loadMosaic(array $options = []): self
    {
        $oldMosaic = Mosaic::instance();
        self::$tagstack[] = $oldMosaic;
        $this->onClose('->restoreMosaic');
        Mosaic::load($options);
        return $this;
    }

    /**
     * Restores the previous Mosaic instance from the tag stack.
     * Called automatically via onClose('->restoreMosaic').
     *
     * @return self For method chaining.
     */
    public function restoreMosaic(): self
    {
        // The old Mosaic was pushed before restoreMosaic marker;
        // popto has already processed restoreMosaic, now the old
        // Mosaic is the next item on the stack.
        $oldMosaic = array_pop(self::$tagstack);
        if ($oldMosaic instanceof Mosaic) {
            // Set the static instance via reflection on private property
            $ref = new \ReflectionClass(Mosaic::class);
            $prop = $ref->getProperty('instance');
            $prop->setAccessible(true);
            $prop->setValue(null, $oldMosaic);
        }
        return $this;
    }

    /**
     * Snapshots the current Mosaic state to a view file.
     *
     * Renders all Shards as hidden <input> tags and writes them to
     * views/<name>.php. The resulting file is a valid ClearView view
     * that can be loaded later via Mosaic::load(['loadSnapShot' => $name]).
     *
     * @param string $name Snapshot name (saved to views/<name>.php)
     * @return self For method chaining.
     */
    public function snapshot(string $name): self
    {
        ob_start();
        Mosaic::outputMosaic();
        $html = ob_get_clean();
        $path = __DIR__ . "/views/{$name}.php";
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $html);
        return $this;
    }

    /**
     * Outputs a template or string.
     *
     * Renders a template string or value, applying template expansion via `self::_()`. Checks rendering
     * conditions before outputting.
     *
     * @param mixed $input The template string to output.
     * @param array|null $match Conditions to check for true (optional).
     * @param array|null $unless Conditions to check for false (optional).
     * @param bool $unlessContained If true, skips rendering if the element is contained (optional).
     * @param bool $isOOB If true, renders only if OOB is active and not contained (optional).
     * @param bool $isRecording If true, renders only if recording is active (optional).
     * @return self For method chaining.
     */
    public function out($input, ?array $match = null, ?array $unless = null, bool $unlessContained = false, bool $isOOB = false, bool $isRecording = false)
    {
        if (!$this->checkQualifiers($match, $unless, $unlessContained, $isOOB, $isRecording)) {
            return $this;
        }
        if (!isset($input)) {
            return $this;
        }
        echo self::_($input) . "\n";
        return $this;
    }

    /**
     * Renders a Shard or object as HTML by calling html().
     *
     * @param object $input The Shard or object to render.
     * @param array|null $match Conditions to check for true (optional).
     * @param array|null $unless Conditions to check for false (optional).
     * @param bool $unlessContained If true, skips rendering if the element is contained (optional).
     * @param bool $isOOB If true, renders only if OOB is active and not contained (optional).
     * @param bool $isRecording If true, renders only if recording is active (optional).
     * @return self For method chaining.
     */
    public function create($input, ?array $match = null, ?array $unless = null, bool $unlessContained = false, bool $isOOB = false, bool $isRecording = false)
    {
        if (!$this->checkQualifiers($match, $unless, $unlessContained, $isOOB, $isRecording)) {
            return $this;
        }
        $stack_position = count(self::$tagstack);
        if (is_array($input)) {
            $found = Shard::loadShard($input);
        } elseif (is_object($input)) {
            $found = $input;
        }
        if (isset($found)) {
            Exception::debug('EVENT', $this->_p('Calling html on {{name}}'));
            $found->html();
        } elseif (is_array($input)) {
            echo self::_($input['text'] ?? ($input['value'] ?? null))."\n";
        } else {
            echo self::_($input)."\n";
        }
        return $this->popto($stack_position);;
    }


    /**
     * Renders the current element and its contents.
     *
     * Calls `render()`, `style()`, and `script()` on the current element, then renders its contents (if any)
     * as a collection of Shards. Increments the contained count before rendering contents and decrements it
     * after.
     *
     * @return self For method chaining.
     */
    public function render()
    {
        $target = self::me();
        if (is_object($target) && method_exists($target, 'render')) {
            Exception::debug('FACET', $this->_p("Calling render on {{name}}"));
            $target->render();
            $target->style();
            $target->script();
        }
        $stop = self::$data['stopped'] ?? null;
        if (empty($stop) || $stop !== 'true') {
            self::$containedCount++;
            $target->renderChildren();
            self::$containedCount--;
        } else {
            Exception::debug('FACET',"Facet has been stopped [$stop]");
        }
        return $this;
    }

    /**
     * Call ->stop() instead of ->close() to prevent rendering children
     * @return $this for method chaining
     */
    public function stop()
    {
        // this is autocleared via tag-stack
        $this->setField('stopped','true');
        return $this;
    }

    /**
     * Closes tags back to the instance's position.
     *
     * Restores the tag stack to the Facet instance’s initial position, processing closing tags and method
     * markers (e.g., `->stopOOB`) via `popto()`.
     *
     * @return mixed The Facet instance or a method result.
     * @throws CleaView::Exception If the position is invalid.
     */
    public function close()
    {
        $count = count(self::$tagstack);
        $position = min($this->position, $count);
        if ($position < 0) {
            throw new Exception("Invalid position: $position");
        }
        $return = $this->popto($position);
        if (isset($this->afterClose)) {
            $this->handleTag($this->afterClose);
            $this->afterClose = null;
        }
        return $return;
    }

    /**
     * Buffer a debug trace message for the current pane.
     *
     * @param string $msg The debug message.
     * @return self For method chaining.
     */
    public function debug(string $msg): self
    {
        Exception::debug('DEBUG', $this->_p($msg));
        return $this;
    }

    /**
     * Buffer a debug breakpoint message for the current pane.
     *
     * @return self For method chaining.
     */
    public function debug_break(): self
    {
        Exception::debug('BREAK', $this->_p('Breakpoint'));
        return $this;
    }

    /**
     * Forwards unknown method calls to the target element, ClearView, or Mosaic.
     *
     * Chains method calls to the current element (via `me()`), ClearView, or Mosaic, with debugging for
     * traceability. Handles special case for `debug()` and supports custom closure methods via `onClose()`.
     *
     * @param string $name The method name being called.
     * @param array $arguments The arguments to pass.
     * @return self For method chaining.
     */
    public function __call($name, $arguments)
    {
        $target = self::me();
        if (is_object($target) && method_exists($target, $name)) {
            if ($name === 'debug') {
                $arguments = [...$arguments, 3];
            }
            Exception::debug($this->_p("Calling ->$name"));
            $target->$name(...$arguments);
        } elseif (method_exists('ClearView\\ClearView', $name)) {
            $args = empty($arguments) ? [] : $arguments;
            Exception::debug($this->_p("Calling ClearView->$name"));
            ClearView::$name(...$args);
        } else {
            if (method_exists($target, $name)) {
                Exception::debug($this->_p("Cascading->$name"));
                $target->$name(...$arguments);
            } else {
                throw new Exception("Facet: No such method as $name");
            }
        }
        return $this;
    }
}
