<?php

namespace ClearView;

use ClearView\Exception;
use ClearView\Sanitizer;
use ClearView\Facet;
use ClearView\Mosaic;
use ProcessWire;

/**
 * Handles parsing and interpretation of variable query expressions.
 *
 * This class centralizes all logic for understanding and resolving template
 * expressions used by Facet. It parses expressions into components and resolves
 * them against the current application state, handling method calls, variable
 * lookups, and HTML/CSS attribute definitions.
 */
class QueryParser
{
    /**
     * The main entry point for parsing and resolving a single template expression.
     *
     * This method is responsible for taking a raw expression string (e.g., 'count++' or
     * 'Inlay::method()') and returning its resolved value. Handles short-circuit operators
     * (^^ XOR, ||, &&), increment/decrement, array indexing, template expansion, and
     * sanitizer pipelines.
     *
     * @param string $expression The template expression to resolve.
     * @param array|null $locals Local variables for variable lookup (optional).
     * @param string|null $inlay The inlay name to resolve against (optional).
     * @param mixed $forceFacet A named flag to force resolution through the given object first.
     * @return mixed The resolved value (string or array).
     * @throws Exception When ^^ XOR has both operands non-null.
     */
    public static function parseAndResolve(string $expression, ?array $locals = null, ?string $inlay=null, mixed $forceFacet = null)
    {
        $expression = trim($expression);

        // Process templates first
        $expression = self::processTemplate($expression, $locals, $inlay, $forceFacet);

        // ^^ XOR evaluation: returns non-null operand, throws if both non-null.
        if (strpos($expression, '^^') !== false) {
            [$left, $right] = explode('^^', $expression, 2);
            $leftVal = self::parseAndResolve(trim($left), locals: $locals, inlay: $inlay, forceFacet: $forceFacet);
            $rightVal = self::parseAndResolve(trim($right), locals: $locals, inlay: $inlay, forceFacet: $forceFacet);
            $leftOk = $leftVal !== null && $leftVal !== false && $leftVal !== '' && !(is_array($leftVal) && count($leftVal) === 0);
            $rightOk = $rightVal !== null && $rightVal !== false && $rightVal !== '' && !(is_array($rightVal) && count($rightVal) === 0);
            if ($leftOk && $rightOk) {
                throw new Exception("XOR conflict: both operands are non-null");
            }
            return $leftOk ? $leftVal : $rightVal;
        }
        // || and && short-circuit evaluation
        // Operates on the resolved string, splitting on the first occurrence.
        if (strpos($expression, '||') !== false) {
            [$left, $right] = explode('||', $expression, 2);
            $leftVal = self::parseAndResolve(trim($left), locals: $locals, inlay: $inlay, forceFacet: $forceFacet);
            // Template truthy: not null, not false, not empty string, not empty array
            if ($leftVal !== null && $leftVal !== false && $leftVal !== '' && !(is_array($leftVal) && count($leftVal) === 0)) {
                return $leftVal;
            }
            return self::parseAndResolve(trim($right), locals: $locals, inlay: $inlay, forceFacet: $forceFacet);
        }
        if (strpos($expression, '&&') !== false) {
            [$left, $right] = explode('&&', $expression, 2);
            $leftVal = self::parseAndResolve(trim($left), locals: $locals, inlay: $inlay, forceFacet: $forceFacet);
            $isFalsy = $leftVal === null || $leftVal === false || $leftVal === '' || (is_array($leftVal) && count($leftVal) === 0);
            if ($isFalsy) {
                return $leftVal;
            }
            return self::parseAndResolve(trim($right), locals: $locals, inlay: $inlay, forceFacet: $forceFacet);
        }

        // Pre-decrement: {{--count}}
        if (preg_match('/^--(\w+)$/', $expression, $matches)) {
            $var = $matches[1];
            $value = (int) Mosaic::getVar($var,$inlay); // Cast to int for numeric ops
            $newValue = $value - 1;
            Mosaic::setVar($var, $newValue);
            return $newValue;
        }
        // Post-increment: {{count++}}
        elseif (preg_match('/^(\w+)\+\+$/', $expression, $matches)) {
            $var = $matches[1];
            $value = (int) Mosaic::getVar($var,$inlay); // Cast to int for numeric ops
            Mosaic::setVar($var, $value + 1);
            return $value;
        }
        // Array indexing: {{var[index]}}
        elseif (preg_match('/^(\w+)\[(.+?)\]$/', $expression, $matches)) {
            $var = $matches[1];
            $indexExpr = $matches[2];
            // Recursively resolve the index expression
            $index = self::parseAndResolve($indexExpr, locals: $locals, forceFacet: $forceFacet);
            $shard = self::getVarValue($var, $inlay, $locals, $forceFacet);
            $contents = $shard ? $shard->getField(Config::SHARD_ARRAYNAME) : null;
            return $contents[$index] ?? '';
        }

        // Parse and resolve other expression types
        $parsed = self::parse($expression, $inlay);
        $type = $parsed['type'];

        // Handle subfield (dot notation) by splitting the base if applicable
        if (isset($parsed['base']) && strpos($parsed['base'], '.') !== false) {
            $pos = strrpos($parsed['base'], '.');
            $subfield = substr($parsed['base'], $pos + 1);
            $parsed['base'] = substr($parsed['base'], 0, $pos);
            $parsed['subfield'] = $subfield;
        }

        $callbacks = [
            'method' => [self::class, 'resolveMethodCall'],
            'css' => [self::class, 'resolveCSSDefinition'],
            'attribute' => [self::class, 'resolveHTMLAttribute'],
            'inlay' => [self::class, 'resolveInlayQuery'],
            'variable' => [self::class, 'resolveVariable']
        ];

        if (isset($callbacks[$type])) {
            $value = call_user_func($callbacks[$type], $parsed, $locals, $forceFacet);

            // Apply subfield (dot notation) if present
            if (!empty($parsed['subfield'])) {
                if (is_array($value)) {
                    $value = array_filter(array_map(function($v) use ($parsed) {
                        return is_object($v) && method_exists($v, 'getField') ? $v->getField($parsed['subfield']) : null;
                    }, $value));
                } elseif (is_object($value) && method_exists($value, 'getField')) {
                    $value = $value->getField($parsed['subfield']);
                } else {
                    $value = null;
                }
            }

            // Apply sanitizers after resolution and subfield
            if (!empty($parsed['sanitizers'])) {
                $value = ClearView::Sanitizer()->sanitize($value, $parsed['sanitizers']);
            }
            return $value;
        }

        return '';
    }

    /**
     * Parses a template expression string into its component parts.
     *
     * Analyzes expressions and categorizes them as method calls, CSS definitions, HTML attributes,
     * inlay queries, or variables. Pane:: prefix routes to inlay field queries (no method dispatch).
     * Inlay::, Glyph::, and other Crystal:: prefixes route to method dispatch.
     *
     * @param string $expression The full variable expression string.
     * @param string|null $inlay The current inlay name for context.
     * @return array An associative array containing the parsed components.
     */
    public static function parse(string $expression, ?string $inlay=null): array
    {
        $result = [
            'type' => 'variable',
            'base' => null,
            'sanitizers' => '',
            'inlay' => $inlay ?? ClearView::inlay(),
            'method' => null,
            'attr' => null,
            'property' => null,
        ];

        $sanitizerPos = strrpos($expression, '\\');
        if ($sanitizerPos !== false) {
            $result['sanitizers'] = trim(substr($expression, 0, $sanitizerPos));
            $expression = trim(substr($expression, $sanitizerPos + 1));
        }

        // Pane:: prefix resolves fields only (no method dispatch).
        // Strip any trailing () and route as inlay field query.
        if (str_starts_with($expression, 'Pane::')) {
            $expressionNoParens = rtrim($expression, '()');
            [$newinlay, $query] = explode('::', $expressionNoParens, 2);
            $result['type'] = 'inlay';
            $result['inlay'] = trim($newinlay);
            $result['base'] = trim($query);
            return $result;
        }
        // Method call: Inlay::method(), Glyph::method(), or Crystal::method()
        if (preg_match('/^(\w+)::(\w+)\(\)$/', $expression, $matches)) {
            $result['type'] = 'method';
            $result['inlay'] = $matches[1] ?? $inlay;
            $result['method'] = $matches[2];
            return $result;
        }
        // Inlay query: inlay::var
        if (strpos($expression, '::') !== false) {
            [$newinlay, $query] = explode('::', $expression, 2);
            $result['type'] = 'inlay';
            $result['inlay'] = trim($newinlay) ?? $inlay;
            $result['base'] = trim($query);
            return $result;
        }
        // CSS definition: property:var
        if (strpos($expression, ':') !== false && strpos($expression, '::') === false) {
            [$property, $var] = explode(':', $expression, 2);
            $result['type'] = 'css';
            $result['property'] = trim($property);
            $result['base'] = trim($var);
            return $result;
        }
        // HTML attribute: attr=value
        if (strpos($expression, '=') !== false && strpos($expression, '==') === false) {
            [$attr, $var] = explode('=', $expression, 2);
            $result['type'] = 'attribute';
            $result['attr'] = trim($attr);
            $result['base'] = trim($var);
            return $result;
        }

        // Default to variable
        $result['type'] = 'variable';
        $result['base'] = $expression;

        return $result;
    }

    /**
     * Resolves a method call expression.
     *
     * Handles {{Inlay::method()}}, {{Glyph::method()}} or {{Crystal::method()}} by dispatching
     * to the appropriate object. Inlay dispatches to the current Inlay Crystal instance;
     * Glyph dispatches to the current Facet Element; any other prefix dispatches to the
     * Crystal whose inlay name matches.
     *
     * @param array $parsed Parsed expression components (inlay, method).
     * @param array|null $locals Local variables for scope.
     * @param mixed $forceFacet A named flag to force resolution through the given object.
     * @return string The result of the method call, or empty string if not found.
     */
    private static function resolveMethodCall($parsed,$locals,$forceFacet)
    {
        $inlay = $parsed['inlay'];
        $method = $parsed['method'];

        switch ($inlay) {
            case 'Inlay':
                // Dispatch to current Inlay instance (Crystal registered for current inlay name)
                $inlayCrystal = Mosaic::getVar("ClearView::" . ClearView::inlay());
                if ($inlayCrystal instanceof Crystal && method_exists($inlayCrystal, $method)) {
                    return $inlayCrystal->$method();
                }
                return '';
            case 'Glyph':
                $elem = Facet::me();
                return method_exists($elem, $method) ? $elem->$method() : '';
            case 'Facet':
                return method_exists(Facet::class, $method) ? Facet::$method() : '';
            default:
                // Generalized crystal dispatch: any Crystal whose inlay name matches the prefix
                $crystal = Mosaic::getVar("ClearView::{$inlay}");
                if ($crystal instanceof Crystal && method_exists($crystal, $method)) {
                    return $crystal->$method();
                }
                return '';
        }
    }

    /**
     * Resolves a CSS definition expression.
     *
     * Handles `{{property:var}}` by retrieving the variable and formatting it as a
     * CSS property (e.g., `color: red;`).
     *
     * @param array $parsed Parsed expression components (property, base).
     * @return string The CSS property string, or empty string if no value.
     */
    private static function resolveCSSDefinition($parsed,$locals,$forceFacet)
    {
        $value = self::getVarValue($parsed['base'], $parsed['inlay'],$locals,$forceFacet);

        if ($value !== null) {
            $property = $parsed['property'];
            return "$property: $value;";
        }
        return '';
    }

    /**
     * Resolves an HTML attribute expression.
     *
     * Handles `{{attr=value}}` by retrieving the variable and formatting it as an
     * HTML attribute.
     *
     * @param array $parsed Parsed expression components (attr, base).
     * @return string The attribute string, or empty string if no value.
     */
    private static function resolveHTMLAttribute($parsed,$locals,$forceFacet)
    {
        $value = self::getVarValue($parsed['base'], $parsed['inlay'],$locals,$forceFacet);

        if ($value !== null) {
            $attr = $parsed['attr'];
            return "$attr=\"$value\"";
        }
        return '';
    }

    /**
     * Resolves a variable expression.
     *
     * Handles standard variable lookups, first checking locals, then the current element
     * via Facet::me(), and finally Mosaic::getVar().
     *
     * @param array $parsed Parsed expression components (base).
     * @param array|null $locals Local variables for lookup (optional).
     * @return mixed The resolved value.
     */
    private static function resolveVariable($parsed, ?array $locals = null, mixed $forceFacet = null)
    {
        $base = $parsed['base'];
        $inlay = $parsed['inlay'];

        // Check if this is a search expression
        if (preg_match('/^([\w.]+)(==|!=|>|<|>=|<=|~=|\*=)(.+)$/', $base, $matches)) {
            $field = trim($matches[1]);
            $op = $matches[2];
            $expected = trim($matches[3]);

            if ($forceFacet) {
                $contents = $forceFacet->getField(Config::SHARD_ARRAYNAME) ?? [];
                $results = [];
                $primaryField = $forceFacet->primaryField ?? 'value';
                foreach ($contents as $item) {
                    if ($item instanceof Shard) {
                        $itemValue = $item->getField($field);
                        if (self::compare($itemValue, $op, $expected)) {
                            $results[] = $item;
                        }
                    } elseif (is_string($item) && $field === $primaryField) {
                        if (self::compare($item, $op, $expected)) {
                            $results[] = $item;
                        }
                    }
                }
                return $results;
            } else {
                return Mosaic::findShards($field, $expected, $inlay, $op);
            }
        }
        return self::getVarValue($base, $inlay, $locals, $forceFacet);
    }

    /**
     * Resolves an inlay query expression.
     *
     * Handles `{{inlay::query}}` by retrieving the specified shard and field.
     *
     * @param array $parsed Parsed expression components (inlay, base).
     * @return mixed The resolved value from Mosaic.
     */
    private static function resolveInlayQuery($parsed,$locals,$forceFacet)
    {
        $base = $parsed['base'];
        $inlay = $parsed['inlay'];

        // Check if this is a search expression
        if (preg_match('/^([\w.]+)(==|!=|>|<|>=|<=|~=|\*=)(.+)$/', $base, $matches)) {
            $field = trim($matches[1]);
            $op = $matches[2];
            $expected = trim($matches[3]);

            $crystal = Mosaic::getVar("ClearView::" . $inlay);
            if ($crystal instanceof Crystal) {
                $query = $field . $op . $expected;
                return $crystal->getVar($query);
            } else {
                if ($forceFacet) {
                    $contents = $forceFacet->getField(Config::SHARD_ARRAYNAME) ?? [];
                    $results = [];
                    $primaryField = $forceFacet->primaryField ?? 'value';
                    foreach ($contents as $item) {
                        if ($item instanceof Shard) {
                            $itemValue = $item->getField($field);
                            if (self::compare($itemValue, $op, $expected)) {
                                $results[] = $item;
                            }
                        } elseif (is_string($item) && $field === $primaryField) {
                            if (self::compare($item, $op, $expected)) {
                                $results[] = $item;
                            }
                        }
                    }
                    return $results;
                } else {
                    return Mosaic::findShards($field, $expected, $inlay, $op);
                }
            }
        }

        $shard = Mosaic::index($inlay, $base);
        if ($shard) {
            return $shard;
        }

        // As a fallback, check if it's a Crystal
        $crystal = Mosaic::index("ClearView",$inlay);
        if ($crystal instanceof Page) {
            return $crystal->getVar($base);
        }
        return null;
    }

    public static function processTemplate(string $string, ?array $locals = null, ?string $inlay = null, mixed $forceFacet = null): string
    {
        $replaced = true;
        do {
            $replaced = false;
            $string = preg_replace_callback('/{{((?:[^{}]*|(?R))*)}}(\s)?/', function ($matches) use (&$replaced, $locals, $inlay, $forceFacet) {
                // The expression is in $matches[1]
                $expression = trim($matches[1]);
                $value = self::parseAndResolve($expression, locals: $locals, inlay: $inlay, forceFacet: $forceFacet);

                // If the value is an array, implode it
                if (is_array($value)) {
                    $value = implode(' ', $value);
                }

                // Null values eat one trailing space: "{{null}} {{value}}" -> "value"
                $isNull = $value === null || $value === '' || $value === false;
                if ($isNull) {
                    $replaced = true;
                    return '';
                }

                // If a replacement occurred, set the flag to continue the loop
                if ($value !== $matches[0]) {
                    $replaced = true;
                }

                // Preserve trailing space for non-null values
                return $value . ($matches[2] ?? '');
            }, $string);
        } while ($replaced);

        return $string;
    }

    /**
     * Gets a variable's value from the most specific scope outwards.
     *
     * @param string $var The variable name.
     * @param array|null $locals Local variables for lookup (optional).
     * @param bool $forceFacet If true, forces resolution through Facet::me() first.
     * @return mixed The resolved value.
     */
    private static function getVarValue(string $var, ?string $inlay = null, ?array $locals = null, mixed $forceFacet = null)
    {
        // Handle subfield resolution (dot notation)
        $field = null;
        if (strpos($var, '.') !== false) {
            [$var, $field] = explode('.', $var, 2);
        }

        // 1. Check locals
        if ($locals && isset($locals[$var])) {
            $value = $locals[$var];
            return $field ? ($value->getField($field) ?? null) : $value;
        }

        if ($forceFacet) {
            $value = $forceFacet->getField($var);
            return $field ? ($value->getField($field) ?? null) : $value;
        } else {
            $shard = Mosaic::index($inlay ?? ClearView::inlay(),$var);
            if ($shard) {
                return $field ? ($shard->getField($field) ?? null) : $shard;
            }
        }
        return null;
    }

    /**
     * Evaluates a conditional match for rendering qualifiers.
     *
     * Compares a value against an expected value using operators like `==`, `!=`, or `~=` for conditional
     * rendering checks. This is a unification of the previous logic from Facet.
     *
     * @param mixed $value The value to compare.
     * @param string $operator The comparison operator (e.g., `==`, `!=`, `*=`).
     * @param mixed $expected The expected value.
     * @return bool True if the comparison passes, false otherwise.
     */
    public static function compare($value, $operator, $expected)
    {
        switch ($operator) {
            case '==':
                return $value == $expected;
            case '!=':
                return $value != $expected;
            case '>':
                return is_numeric($value) && is_numeric($expected) && $value > $expected;
            case '<':
                return is_numeric($value) && is_numeric($expected) && $value < $expected;
            case '>=':
                return is_numeric($value) && is_numeric($expected) && $value >= $expected;
            case '<=':
                return is_numeric($value) && is_numeric($expected) && $value <= $expected;
            case '~=':
                return stripos((string)$value, (string)$expected) !== false; // Case-insensitive partial match
            case '*=':
                return preg_match('/' . preg_quote($expected, '/') . '/i', (string)$value); // Regex match
            default:
                return false;
        }
    }
}
