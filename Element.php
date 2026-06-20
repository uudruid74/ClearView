<?php

namespace ClearView;

use ClearView\ClearView;
use ClearView\Mosaic;
use ClearView\Facet;
use ClearView\Shard;
use ClearView\Exception;
use ClearView\Page;
use ClearView\Pane;

/**
 * Represents an HTML element with dynamic rendering capabilities in ClearView.
 *
 * Element extends Shard to provide functionality for rendering HTML elements with HTMX attributes, CSS
 * variables, inline styles, scripts, and event handlers. It integrates with Facet for template processing,
 * Mosaic for variable management, and ClearView for JavaScript injection and creator interactions. Elements
 * support dynamic field access (e.g., `hx-` attributes, `class`, `data`) and special field handling via `::`
 * syntax (e.g., `css::var`, `class::add`). The class manages client-side interactions through HTMX triggers,
 * swaps, and targets, and supports out-of-band (OOB) updates via Facet’s OOB mechanism. It is designed for
 * use in ProcessWire-based applications, leveraging Mosaic for data synchronization and ClearView for
 * rendering orchestration.
 *
 * Key features:
 * - Generates HTMX attributes (`hx-post`, `hx-swap`, `hx-target`, `hx-trigger`) for dynamic updates.
 * - Manages CSS classes, inline styles, scripts, and data attributes with specialized methods.
 * - Supports event handlers via `on` field and JavaScript injection.
 * - Provides field access with special handling for `id`, `hx`, `hx-swap-oob`, `data`, and `interact`.
 * - Integrates with Facet for template expansion and Mosaic for variable resolution.
 *
 * Dependencies: Requires `jsonmangler` for Shard data mangling, HTMX for client-side interactions, and
 * ProcessWire for URL resolution (e.g., `{{Page::url}}`).
 *
 * @see \ClearView\Shard
 * @see \ClearView\Facet
 * @see \ClearView\Mosaic
 * @see \ClearView\ClearView
 */
class Element extends Shard
{
    /**
     * Initializes the element with properties from the provided object.
     *
     * Called to create a new Element instance, setting up properties from a scalar value or array via the
     * parent Shard constructor. Automatically registers a change notification trigger if a `name` field is
     * defined and a corresponding method exists on the ClearView creator.
     *
     * Why: Sets up the element’s initial state and integrates with ClearView’s change notification system.
     *
     * @param mixed $obj Scalar value (e.g., element name) or array of properties (e.g., ['id' => 'btn', 'class' => 'active']).
     */
    public function __construct($obj = null, ?string $primaryField = null, ?string $named = null, ?string $contentsType = null)
    {
        parent::__construct(...func_get_args());
        // Set up automatic change notification
        if (method_exists(ClearView::paneobj(), $this->getField('name') ?? 'change')) {
            $this->addtrigger('change');
        }
    }

    /**
     * Sets a CSS variable for the element.
     *
     * Used to dynamically set a CSS custom property (e.g., `--color`) on the element via JavaScript injection.
     * The variable is applied client-side using the element’s ID.
     *
     * Why: Enables dynamic styling of elements without server-side CSS updates.
     *
     * @param string $varname The CSS variable name (without `--` prefix).
     * @param string $value The value to set for the CSS variable.
     * @return self For method chaining.
     */
    public function setCSSVar(string $varname, string $value): self
    {
        $this['ClearView']->javascript("me('#{{id}}').style.setProperty('--{$varname}', '{$value}');");
        return $this;
    }

    /**
     * Logs a debug message with the GLYPH tag.
     *
     * Used to output a debug message to the ClearView exception logger, tagged as `GLYPH` for element-specific
     * tracing. Includes a configurable stack trace depth for context.
     *
     * Why: Facilitates debugging of element rendering and interactions.
     *
     * @param string $msg The debug message to log.
     * @param int $depth The stack trace depth (default: 2).
     * @return self For method chaining.
     */
    public function debug(string $msg, int $depth = 2): self
    {
        Exception::debug('GLYPH', $msg, $depth);
        return $this;
    }

    /**
     * Renders the Element as HTML.
     *
     * Why: Delegates rendering to Facet for consistent HTML output.
     */
    /**
     * Render the element to output, or capture to string when $capture is true.
     *
     * @param bool $capture When true, return rendered HTML instead of echoing.
     * @return string|null  Rendered HTML when $capture is true; null otherwise.
     */
    public function render(bool $capture = false): ?string
    {
        if ($capture) {
            ob_start();
        }

        if (isset($this['glyph'])) {
            (new Facet($this))
                ->open("<{{glyph}} {{id=id}} {{hx}}>{{value}}");
        } else {
            echo parent::render();
        }

        if ($capture) {
            return ob_get_clean();
        }
        return null;
    }

    /**
     * Outputs inline styles for the element.
     *
     * Used to render the element’s `style` field as an inline `<style>` tag. Supports scalar styles (raw CSS)
     * or Shard objects containing selector-value pairs. Styles are processed through Facet for template
     * expansion and output within a `<style>` tag.
     *
     * Why: Enables dynamic CSS styling scoped to the element or its children.
     *
     * @return void
     */
    public function style(): void
    {
        $styleFields = $this->getFields("style::*");
        $styles = '';
        foreach ($styleFields as $field) {
            [$prefix, $selector] = explode('::', $field, 2);
            $value = $this->getField($field);
            if ($value !== null) {
                if (!str_ends_with($value)) {
                    $value = "$value;";
                }
                $styles .= "$selector { $value }\n";
            }
        }
        if ($styles) {
            (new Facet("<style>\n$styles"))->close();
        }
    }

    /**
     * Outputs inline scripts for the element.
     *
     * Used to render the element’s `script` field and `on` event handlers as an inline `<script>` tag. The
     * `script` field contains raw JavaScript, while the `on` field (as a Shard) maps events to handlers, which
     * are bound to the element’s ID using HTMX’s `me()` utility. Scripts are processed through Facet for
     * template expansion.
     *
     * Why: Enables dynamic client-side behavior for the element, including event handling.
     *
     * @return void
     */
    public function script(): void
    {
        $script = $this->getField('script') ?? null;
        $onFields = $this->getFields("on::*");
        $output = '';
        if (isset($script)) {
            $output .= "$script\n";
        }
        foreach ($onFields as $field) {
            [$prefix, $event] = explode('::', $field, 2);
            $handler = $this->getField($field);
            if ($handler !== null) {
                $output .= "me('#{{id}}').on(\"$event\", ev => { $handler });\n";
            }
        }
        if ($output) {
            (new Facet("<script>\n$output"))->close();
        }
    }

    /**
     * Sets the HTMX post URL for the element.
     *
     * Used to configure the `hx-post` attribute for HTMX requests. If a URL is provided, it is set directly;
     * otherwise, a default URL is constructed using the page URL and element name.
     *
     * Why: Defines the endpoint for HTMX-driven form submissions or actions.
     *
     * @param string|null $url The URL to set (optional; defaults to `{{Page::url}}{{name}}/`).
     * @return self For method chaining.
     */
    public function seturl(?string $url = null): self
    {
        if (isset($url)) {
            $this->setField('hx-post', Facet::_($url));
        } else {
            $this->setField('hx-post', Facet::_("{{Page::url}}{{name}}/"));
        }
        return $this;
    }

    /**
     * Sets the HTMX post URL to a specific method.
     *
     * Used to configure the `hx-post` attribute to point to a method-specific endpoint, constructed from the
     * page URL and the provided method name.
     *
     * Why: Allows targeting specific server-side methods for HTMX requests.
     *
     * @param string $methodname The method name to include in the URL.
     * @return self For method chaining.
     */
    public function setmethod(string $methodname): self
    {
        $this->setField('hx-post', Facet::_("{{Page::url}}{$methodname}/"));
        return $this;
    }

    /**
     * Sets the HTMX swap type for the element.
     *
     * Used to configure the `hx-swap` attribute, which determines how HTMX replaces content after a request
     * (e.g., `innerHTML`, `outerHTML`).
     *
     * Why: Controls the behavior of HTMX content updates.
     *
     * @param string $swap The swap type (e.g., `innerHTML`, `outerHTML`).
     * @return self For method chaining.
     */
    public function setswap(string $swap): self
    {
        $this->setField('hx-swap', Facet::_($swap));
        return $this;
    }

    /**
     * Sets the HTMX target for the element.
     *
     * Used to configure the `hx-target` attribute, which specifies the DOM element to update after an HTMX
     * request. Automatically prefixes the target with `#` if needed, unless it’s `this`.
     *
     * Why: Defines the target for HTMX content replacement.
     *
     * @param string $target The target selector (e.g., `#container`, `this`) (default: `this`).
     * @return self For method chaining.
     */
    public function settarget(string $target = 'this'): self
    {
        if (!str_starts_with($target, '#') && $target !== 'this') {
            $target = "#{$target}";
        }
        $this->setField('hx-target', Facet::_($target));
        return $this;
    }

    /**
     * Sets the HTMX trigger event for the element.
     *
     * Used to configure the `hx-trigger` attribute, which specifies the event that triggers an HTMX request
     * (e.g., `click`, `change`).
     *
     * Why: Defines the event that initiates HTMX interactions.
     *
     * @param string $trigger The trigger event (default: `click`).
     * @return self For method chaining.
     */
    public function settrigger(string $trigger = 'click'): self
    {
        $this->setField('hx-trigger', Facet::_($trigger));
        return $this;
    }

    /**
     * Adds a new trigger to the existing HTMX trigger attribute.
     *
     * Used to append a new event to the `hx-trigger` attribute, ensuring no duplicates. Triggers are stored as
     * a comma-separated list.
     *
     * Why: Allows multiple events to trigger HTMX requests.
     *
     * @param string $newtrigger The new trigger event to add.
     * @return self For method chaining.
     */
    public function addtrigger(string $newtrigger): self
    {
        if (!isset($newtrigger)) {
            return $this;
        }
        $trigger = $this->getField('hx-trigger');
        $triggers = $trigger ? explode(',', $trigger) : [];
        if (!in_array($newtrigger, $triggers)) {
            $triggers[] = $newtrigger;
            $this->setField('hx-trigger', Facet::_(implode(',', $triggers)));
        }
        return $this;
    }

    /**
     * Generates HTMX attributes for the element.
     *
     * Used to compile all HTMX-related attributes (`hx-*`), including `hx-buzz` for haptic feedback, `hx-swap-oob`
     * for out-of-band updates, and `class` for CSS classes. Iterates over fields with `hx-` prefixes using
     * Shard’s `iterateFields` and formats them via Facet.
     *
     * Why: Centralizes HTMX attribute generation for consistent rendering.
     *
     * @return string A space-separated string of HTMX attributes (e.g., `hx-post="/url" hx-swap="innerHTML"`).
     */
    public function getHxVals(): string
    {
        $out = '';
        $time = ClearView::Mosaic()->getVar('User::haptic-strength') * $this->getField('buzz');
        if ($time > 0) {
            $out .= "hx-buzz=$time ";
        }
        if (Facet::isOob()) {
            $out .= Facet::_("{{hx-swap-oob=hx-swap-oob}} ");
        }
        $cssclasses = $this->getField('class');
        if (isset($cssclasses)) {
            $out .= Facet::_("class=\"$cssclasses\" ");
        }
        $hxAttrs = $this->iterateFields(
            callback: fn ($value, $key) => Facet::_("{$key}=\"$value\""),
            delim: ' ',
            filter: '/^hx-/'
        );
        $retval = $out . $hxAttrs;
        Exception::debug('VAR', "HX: " . Facet::_(addslashes($retval)));
        return $retval;
    }

    /**
     * Adds a CSS class to the element’s class field.
     *
     * Used to append a CSS class to the `class` field, ensuring no duplicates. Updates the field and injects
     * client-side JavaScript to apply the class via HTMX.
     *
     * Why: Enables dynamic class manipulation for styling.
     *
     * @param string $cssclass The CSS class to add.
     * @param array|null $obj Optional array to modify instead of the element’s field.
     * @return bool True on success, false if the class is empty.
     */
    public function addClass(string $cssclass, ?array &$obj = null): bool
    {
        if (trim($cssclass) === '') {
            return false;
        }
        $cssclass = trim($cssclass);
        $currentClasses = $obj !== null ? ($obj['class'] ?? '') : ($this->getField('class') ?? '');
        $classArray = array_filter(explode(' ', trim($currentClasses)));
        if (in_array($cssclass, $classArray, true)) {
            return true;
        }
        $classArray[] = $cssclass;
        $newClasses = implode(' ', array_unique($classArray));
        if ($obj !== null) {
            $obj['class'] = $newClasses;
            return true;
        }
        $this->setField('class', $newClasses);
        $this['ClearView']->javascript("htmx.addClass(me(\"#{{id}}\"), '$cssclass');");
        return true;
    }

    /**
     * Removes a CSS class from the element's class field.
     *
     * Used to remove a CSS class from the `class` field. Updates the field and injects client-side JavaScript
     * to remove the class via HTMX.
     *
     * Why: Enables dynamic class manipulation for styling.
     *
     * @param string $cssclass The CSS class to remove.
     * @param array|null $obj Optional array to modify instead of the element’s field.
     * @return bool True on success, false if the class is empty.
     */
    public function delClass(string $cssclass, ?array &$obj = null): bool
    {
        if (trim($cssclass) === '') {
            return false;
        }
        $cssclass = trim($cssclass);
        $currentClasses = $obj !== null ? ($obj['class'] ?? '') : ($this->getField('class') ?? '');
        $classArray = array_filter(explode(' ', trim($currentClasses)));
        if (!in_array($cssclass, $classArray, true)) {
            return true;
        }
        $classArray = array_diff($classArray, [$cssclass]);
        $newClasses = implode(' ', array_unique($classArray));
        if ($obj !== null) {
            $obj['class'] = $newClasses;
            return true;
        }
        $this->setField('class', $newClasses);
        $this['ClearView']->javascript("htmx.removeClass(me(\"#{{id}}\"), '$cssclass');");
        return true;
    }

    /**
     * Toggles a CSS class in the element's class field.
     *
     * Used to toggle a CSS class in the `class` field, adding it if absent or removing it if present. Updates
     * the field and injects client-side JavaScript to toggle the class via HTMX.
     *
     * Why: Enables dynamic class manipulation for interactive styling.
     *
     * @param string $cssclass The CSS class to toggle.
     * @param array|null $obj Optional array to modify instead of the element’s field.
     * @return bool True on success, false if the class is empty.
     */
    public function toggleClass(string $cssclass, ?array &$obj = null): bool
    {
        if (trim($cssclass) === '') {
            return false;
        }
        $cssclass = trim($cssclass);
        $currentClasses = $obj !== null ? ($obj['class'] ?? '') : ($this->getField('class') ?? '');
        $classArray = array_filter(explode(' ', trim($currentClasses)));
        $exists = in_array($cssclass, $classArray, true);
        if ($exists) {
            $classArray = array_diff($classArray, [$cssclass]);
        } else {
            $classArray[] = $cssclass;
        }
        $newClasses = implode(' ', array_unique($classArray));
        if ($obj !== null) {
            $obj['class'] = $newClasses;
            return true;
        }
        $this->setField('class', $newClasses);
        $this['ClearView']->javascript("htmx.toggleClass(me(\"#{{id}}\"), '$cssclass');");
        return true;
    }

    /**
     * Sets a single CSS class, removing all others.
     *
     * Used to replace all CSS classes in the `class` field with a single class. Updates the field and injects
     * client-side JavaScript to apply the class via HTMX.
     *
     * Why: Enables exclusive class assignment for styling.
     *
     * @param string $cssclass The CSS class to set.
     * @param array|null $obj Optional array to modify instead of the element’s field.
     * @return bool True on success, false if the class is empty.
     */
    public function takeClass(string $cssclass, ?array &$obj = null): bool
    {
        if (trim($cssclass) === '') {
            return false;
        }
        $cssclass = trim($cssclass);
        if ($obj !== null) {
            $obj['class'] = $cssclass;
            return true;
        }
        $this->setField('class', $cssclass);
        $this['ClearView']->javascript("htmx.takeClass(me(\"#{{id}}\"), '$cssclass');");
        return true;
    }

    /**
     * Checks if a CSS class exists in the class field.
     *
     * Used to determine if a specific CSS class is present in the `class` field.
     *
     * Why: Supports conditional logic based on element styling.
     *
     * @param string $cssclass The CSS class to check.
     * @param array|null $obj Optional array to check instead of the element’s field.
     * @return bool True if the class exists, false otherwise.
     */
    public function hasClass(string $cssclass, ?array $obj = null): bool
    {
        if (trim($cssclass) === '') {
            return false;
        }
        $cssclass = trim($cssclass);
        $currentClasses = $obj !== null ? ($obj['class'] ?? '') : ($this->getField('class') ?? '');
        $classArray = array_filter(explode(' ', trim($currentClasses)));
        return in_array($cssclass, $classArray, true);
    }

    /**
     * Forwards unknown method calls to client-side JavaScript.
     *
     * Used to handle undefined method calls by generating JavaScript that invokes a function with the same
     * name on the element (via its ID) using HTMX’s `me()` utility. Arguments are JSON-encoded or escaped as
     * needed.
     *
     * Why: Enables dynamic JavaScript interactions without server-side method definitions.
     *
     * @param string $name The JavaScript function name to call.
     * @param array $arguments The arguments to pass to the function.
     * @return void
     */
    public function __call(string $name, array $arguments): void
    {
        $args = array_map(fn ($arg) => is_string($arg) ? "'" . addslashes($arg) . "'" : json_encode($arg), $arguments);
        $this['ClearView']->javascript("me('#{{id}}').{$name}(" . implode(',', $args) . ");");
    }

    /**
     * Delegates to View::loadPHPView() for backward compatibility.
     *
     * @deprecated Use \ClearView\View::loadPHPView() directly.
     * @param string $viewName The name of the view file (without .php extension).
     * @throws Exception If the view file is not found.
     */
    public static function loadPHPView(string $viewName): void
    {
        View::loadPHPView($viewName);
    }

    /**
     * Loads a glyph view file from the module stack, then base vendor glyphs.
     *
     * @param string $viewName The name of the glyph (without .php extension).
     * @return string|null the loaded class path or null if not found
     */
    public static function loadGlyph(string $viewName): ?string
    {
        foreach (Page::buildModuleStack() as $module) {
            $path = __DIR__ . "/modules/{$module}/glyphs/{$viewName}.php";
            if (file_exists($path)) {
                require_once($path);
                return "\\ClearView\\Element\\$viewName";
            }
        }
        $filePath = __DIR__ . "/modules/vendor/glyphs/{$viewName}.php";
        if (!file_exists($filePath)) {
            Exception::debug("Glyph file not found: {$filePath}");
            return null;
        }
        require_once($filePath);
        return "\\ClearView\\Element\\$viewName";
    }

    /**
     * Delegates to View::loadView() for backward compatibility.
     *
     * @deprecated Use \ClearView\View::loadView() directly.
     * @param string $view The name of the view file (without .php extension).
     * @param string|null $from Source marker (Shard::VIEW for view-loaded Shards).
     * @return Shard The Shard object representing the view's content.
     * @throws Exception If the view file is not found.
     */
    public static function loadView(string $view, ?string $from = null): Shard
    {
        return View::loadView($view, $from);
    }

    /**
     * Retrieves a field value with element-specific handling.
     *
     * Overrides Shard’s `getField()` to provide custom logic for fields like `id`, `hx`, `hx-swap-oob`, `data`,
     * and `interact`. Special handling ensures unique IDs, HTMX attribute compilation, and formatted data attributes.
     *
     * Why: Customizes field access for HTML and HTMX-specific rendering needs.
     * @param string $name The field name to retrieve.
     * @return mixed The resolved field value or null if not found.
     */
    public function getField(string $name)
    {
        // Prefix routing: keys containing :: resolve via the current Pane's Mosaic
        if (strpos($name, '::') !== false) {
            return ClearView::Mosaic()->getVar($name);
        }
        switch ($name) {
            case 'id':
                $storedId = parent::getField('id');
                if (empty($storedId)) {
                    return null;
                }
                if ($this->canonicalId) {
                    $name = parent::getField('name') ?? $storedId;
                    return ClearView::Mosaic()->getVar('Pane::name') . '-' . $this->inlay() . '-' . $name;
                }
                return $storedId;
            case 'hx':
                return $this->getHxVals();
            case 'interact':
                return 'interact(me())';
            case 'hx-swap-oob':
                if (!Facet::isOob()) {
                    return null; // Not found
                } else {
                    return parent::getField('hx-swap-oob') ?? 'true';
                }
                // no break
            case 'data':
                return $this->iterateFields(
                    callback: fn ($value, $key) => Facet::_("{$key}=\"$value\""),
                    delim: ' ',
                    filter: '/^data-/'
                );
            default:
                return parent::getField($name);
        }
    }

    /**
     * Sets a field value with special handling for :: fields.
     *
     * Overrides Shard’s `setField()` to handle fields with `::` syntax, such as `css::var` (CSS variables),
     * `data::key` (data attributes), `on::event` (event handlers), `class::operation` (class manipulations), 
     * and `style::rule` (style properties). Delegates to specialized methods for these cases.
     *
     * Why: Provides a flexible interface for managing element attributes and behaviors.
     *
     * @param string $var The field name to set.
     * @param mixed $val The value to set.
     * @return void
     */
    public function setField(string $var, $val): void
    {
        if (strpos($var, '::') !== false) {
            [$lvalue, $rvalue] = explode('::', $var, 2);
            switch ($lvalue) {
                case 'css':
                    $this->setCSSVar($rvalue, $val);
                    return;
                case 'data':
                    $data = $this->getField('data') ?? new Shard([], 'data');
                    if (!$data instanceof Shard) {
                        $data = new Shard(['value' => $data], 'data');
                    }
                    $data->setField($rvalue, $val);
                    parent::setField('data', $data);
                    return;
                case 'on':
                    $on = $this->getField('on') ?? new Shard([], 'on');
                    if (!$on instanceof Shard) {
                        $on = new Shard(['value' => $on], 'on');
                    }
                    $on->setField($rvalue, $val);
                    parent::setField('on', $on);
                    return;
                case 'class':
                    switch ($rvalue) {
                        case 'add':
                            $this->addClass($val);
                            return;
                        case 'del':
                            $this->delClass($val);
                            return;
                        case 'toggle':
                            $this->toggleClass($val);
                            return;
                        case 'take':
                            $this->takeClass($val);
                            return;
                        default:
                            Exception::debug('GLYPH', "Invalid class operation: $rvalue");
                            return;
                    }
                    // no break
                case 'style':
                    $style = $this->getField('style') ?? new Shard([], 'me');
                    if (!$style instanceof Shard) {
                        $style = new Shard(['me' => $style], 'me');
                    }
                    $style->setField($rvalue, $val);
                    parent::setField('style', $style);
                    return;
                default:
                    Exception::debug('GLYPH', "Invalid field prefix: $lvalue");
                    return;
            }
        }
        parent::setField($var, $val);
    }

    /**
     * Deletes a field with special handling for :: fields.
     *
     * Overrides Shard’s `delField()` to handle fields with `::` syntax, such as `css::var`, `data::key`, `on::event`, `class::name`, and `style::rule`. Delegates to specialized methods or clears values appropriately.
     *
     * Why: Ensures consistent field removal for complex attributes.
     *
     * @param string $var The field name to delete.
     * @return void
     */
    public function delField(string $var): void
    {
        if (strpos($var, '::') !== false) {
            [$lvalue, $rvalue] = explode('::', $var, 2);
            switch ($lvalue) {
                case 'css':
                    $this->setCSSVar($rvalue, '');
                    return;
                case 'data':
                    $data = $this->getField('data');
                    if ($data instanceof Shard) {
                        $data->delField($rvalue);
                        parent::setField('data', $data);
                    }
                    return;
                case 'on':
                    $on = $this->getField('on');
                    if ($on instanceof Shard) {
                        $on->delField($rvalue);
                        parent::setField('on', $on);
                    }
                    return;
                case 'class':
                    switch ($rvalue) {
                        case 'add':
                        case 'del':
                        case 'toggle':
                        case 'take':
                            Exception::debug('GLYPH', "Cannot delete class operation: $rvalue");
                            return;
                        default:
                            $this->delClass($rvalue);
                            return;
                    }
                    // no break
                case 'style':
                    $style = $this->getField('style');
                    if ($style instanceof Shard) {
                        $style->delField($rvalue);
                        parent::setField('style', $style);
                    }
                    return;
                default:
                    Exception::debug('GLYPH', "Invalid field prefix: $lvalue");
                    return;
            }
        }
        parent::delField($var);
    }
}
