<?php

namespace ClearView;
use ClearView\Facet;
use ClearView\Exception;
use ClearView\Mosaic;

class jsonmangler
{
    /**
     * HTML void (self-closing) elements that never load default views.
     * @var array<string>
     */
    private const VOID_ELEMENTS = [
        'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input',
        'link', 'meta', 'param', 'source', 'track', 'wbr',
    ];

    /**
     * @var array<string,string> Mapping of special characters to encoded values for mangling JSON.
     */
    private static $encode_map = [
        '::' => '%0', '{{' => '%1', '}}' => '%2', '[[' => '%3', ']]' => '%4', '=>' => '%5', '===' => '%6',
        '!==' => '%7', '</' => '%8', '==' => '%9', '!=' => '%A', ':' => '%B', '{' => '%C', '}' => '%D',
        '[' => '%E', ']' => '%F', ',' => '%G', '\"' => '%H', '&' => '%I', '<' => '%J', '>' => '%K',
        "'" => '%L', "\\" => '%M', "children" => '%N', "href" => '%R', "class" => '%S', "text" => '%T',
        "title" => '%U', "value" => '%V', "glyph" => '%Y', '%' => '%Z'
    ];

    /**
     * Let %Q be used for the new references.  %Qname%D would expand to {glyph:"reference",name:"name"}
     */

    /**
     * @var array<string,string> Reverse mapping of encoded values to special characters for unmangling JSON.
     */
    private static $decode_map;

    /**
     * Initializes the decode map by flipping the encode map.
     *
     * Why: Ensures the decode map is available for unmangling JSON strings, improving performance by
     * initializing it once during class loading.
     */
    public static function __constructStatic()
    {
        self::$decode_map = array_flip(self::$encode_map);
    }

    /**
     * Converts an array to a mangled JSON string, escaping special characters.
     *
     * @param mixed $input The input data to mangle, typically an array.
     * @param string|null $primaryField Optional primary field name to include as __pF.
     * @return string The mangled JSON string.
     *
     * Why: Provides a compact, quote-less JSON representation for storage or transmission, used by
     * Shard::deflate() to serialize Shard data efficiently.
     */
    public static function mangle($input, ?string $primaryField = null)
    {
        if (isset($primaryField) && $primaryField != 'value') {
            $input['__pF'] = $primaryField;
        }
        //$result = '{';
        $result = '';
        $first = true;
        foreach ($input as $key => $value) {
            if ($key === 'inlay' || $key === 'id') {
                continue;
            }
            if (strncmp($key, '__', 2) === 0) {
                continue;
            }
            if ($key === 'name' && isset($input['id']) && $value === $input['id']) {
                continue;
            }
            if (!$first) {
                $result .= ',';
            }
            $first = false;

            // Encode key and value
            $encoded_key = strtr($key, self::$encode_map);
            if (is_array($value)) {
                if (!empty($value) && is_array($value[array_key_first($value)])) {
                    $encoded_value = '[';
                    foreach ($value as $subkey => $subval) {
                        $encoded_value .= self::mangle($subval);
                    }
                    $encoded_value .= ']';
                } else {
                    $encoded_value = '[' . implode(',', $value ?? '') . ']';
                }
            } else {
                $encoded_value = strtr(preg_replace('/(^"|"$)/', '', 
                    json_encode($value, JSON_UNESCAPED_UNICODE)), self::$encode_map);
            }
            $result .= "$encoded_key:$encoded_value";
        }
        //$result .= '}';

        // Clean whitespace
        $result = preg_replace('/\s*([\{\}:,\[\]])\s*/', '$1', $result);
        return $result;
    }

    /**
     * Converts a mangled JSON string back to a PHP array.
     *
     * @param string $jsonString The mangled JSON string to unmangle.
     * @return array The decoded PHP array.
     * @throws Exception If unmangling fails.
     *
     * Why: Restores mangled JSON to a usable PHP array, used by Shard::inflate() to reconstruct
     * Shard objects from stored data.
     */
    public static function unmangle(string $jsonString)
    {
        $jsonString = trim($jsonString);

        // Handle regular JSON
        if (str_contains($jsonString, '"')) {
            $jsonArray = json_decode($jsonString, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($jsonArray)) {
                return $jsonArray;
            }
            Exception::debug('JSON', "Regular JSON failed, proceeding to unmangle: $jsonString");
        }

        // Split into elements, preserving delimiters
        $elements = preg_split('/([\{\}\[\],:])/', $jsonString, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $result = '';
        $i = 0;
        while ($i < count($elements)) {
            $element = trim($elements[$i]);

            // Handle delimiters
            if (preg_match('/^[\{\}\[\],:]$/', $element)) {
                $result .= $element;
                $i++;
                continue;
            }

            // Expect key or value
            if ($i + 2 < count($elements) && $elements[$i + 1] === ':') {
                // Key-value pair
                $key = $element;
                $i += 2; // Move to value position

                // Handle value, including arrays
                $value = '';
                $depth = 0;
                if ($elements[$i] === '[') {
                    // Array value
                    $value .= '[';
                    $depth++;
                    $i++;
                    $array_elements = [];
                    $curr_element = '';
                    while ($i < count($elements) && $depth > 0) {
                        $curr = trim($elements[$i]);
                        if ($curr === '[') {
                            $depth++;
                            $curr_element .= $curr;
                        } elseif ($curr === ']') {
                            $depth--;
                            if ($depth == 0) {
                                if ($curr_element !== '') {
                                    $array_elements[] = $curr_element;
                                }
                                $value .= ']';
                            } else {
                                $curr_element .= $curr;
                            }
                        } elseif ($curr === ',' && $depth == 1) {
                            if ($curr_element !== '') {
                                $array_elements[] = $curr_element;
                            }
                            $curr_element = '';
                        } else {
                            $curr_element .= $curr;
                        }
                        $i++;
                    }

                    // Decode and quote array elements
                    $decoded_array = array_map(function ($elem) {
                        $decoded = strtr($elem, self::$decode_map);
                        return preg_match('/^-?\d+(\.\d+)?$/', $decoded) || 
                               in_array($decoded, ['true', 'false', 'null'], true) ? 
                               $decoded : '"' . $decoded . '"';
                    }, $array_elements);
                    $value = '[' . implode(',', $decoded_array) . ']';
                } else {
                    // Non-array value
                    $value = trim($elements[$i]);
                    $i++;
                }

                // Decode key and value
                $decoded_key = strtr($key, self::$decode_map);
                $decoded_value = $value[0] === '[' ? $value : strtr($value, self::$decode_map);

                // Quote key and value
                $quoted_key = '"' . $decoded_key . '"';
                // Check if value is an array, number, boolean, or null
                if (preg_match('/^[\[\{].*[\]\]]$/', $decoded_value) || 
                    preg_match('/^-?\d+(\.\d+)?$/', $decoded_value) || 
                    in_array($decoded_value, ['true', 'false', 'null'], true)) {
                    $quoted_value = $decoded_value;
                } else {
                    $quoted_value = '"' . $decoded_value . '"';
                }

                $result .= "$quoted_key:$quoted_value";
            } else {
                // Stray element
                $decoded_element = strtr($element, self::$decode_map);
                $result .= preg_match('/^[\[\{].*[\]\]]$/', $decoded_element) || 
                           in_array($decoded_element, ['true', 'false', 'null'], true) ? 
                           $decoded_element : '"' . $decoded_element . '"';
                $i++;
            }
        }

        $jsonArray = json_decode($result, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($jsonArray)) {
            return $jsonArray;
        }
        return [ 'text' =>$jsonString, '__pF' => 'text' ];
    }

    /**
     * Converts an HTML string to a JSON array.
     *
     * @param string $html The raw HTML string to parse.
     * @param string|null $context Optional parent view name for nested view resolution.
     *                             When set, default view lookups also check
     *                             views/{pane}/{context}/{glyph}.php.
     * @return array The JSON-compatible array representing the HTML structure.
     */
    public static function fromhtml(string $html, ?string $context = null): array
    {
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $result = [];
        foreach ($doc->childNodes as $node) {
            // Ensure we only process the root HTML element, or body if it exists
            if ($node->nodeType === XML_ELEMENT_NODE) {
                $processedNode = self::processNode($node, $context);
                if (!empty($processedNode)) {
                    $result[] = $processedNode;
                }
            }
        }
        if (count($result) === 1) {
            return $result[0];
        }
        return ['children' => $result];
    }

    /**
     * Recursively processes a DOM node into a JSON-compatible array.
     *
     * Rules:
     * - Void/self-closing elements (br, hr, img, input, etc.) never load default views.
     * - Default views can nest: e.g. <head> loads views/head.php whose children may
     *   reference views/head/<child>.php.
     * - view="name" on any element overrides its children with the loaded view fragment,
     *   including on self-closing tags.
     * - Folder globs in view names (e.g. view="icons/*") load all matching views/<pane>/icons/*.php
     *   as sibling fragments.
     *
     * @param \DOMNode $node The DOM node to process.
     * @param string|null $context Optional parent view name for nested default-view lookup.
     * @return array The JSON-compatible array for the node.
     */
    private static function processNode(\DOMNode $node, ?string $context = null): array
    {
        // Handle Text Nodes
        if ($node instanceof \DOMText) {
            $trimmedValue = trim($node->nodeValue);
            return $trimmedValue !== '' ? ['text' => $trimmedValue, '__pF' => 'text'] : [];
        }

        // Handle Element Nodes
        if ($node instanceof \DOMElement) {
            $glyph = $node->nodeName;
            $element = [ 'glyph' => $glyph ];

            // Collect attributes first (needed for view= check)
            if ($node->hasAttributes()) {
                foreach ($node->attributes as $attr) {
                    $element[$attr->name] = $attr->value;
                }
            }

            $hasView = !empty($element['view']);

            // Default view lookup — skip for void (self-closing) elements
            // and skip when an explicit view= attribute is present.
            if (!$hasView && !in_array($glyph, self::VOID_ELEMENTS, true)) {
                $pane = Facet::me()->getField('name') ?? 'Default';

                // 1. Nested context: views/{pane}/{context}/{glyph}.php
                if ($context !== null) {
                    foreach (\ClearView\Page::buildModuleStack() as $module) {
                        $ctxPath = __DIR__ . "/../modules/{$module}/views/{$pane}/{$context}/{$glyph}.php";
                        if (file_exists($ctxPath)) {
                            $element['__loadExternal'] = "View::{$context}/{$glyph}";
                            break;
                        }
                    }
                }

                // 2. Top-level: views/{pane}/{glyph}.php
                if (empty($element['__loadExternal'])) {
                    foreach (\ClearView\Page::buildModuleStack() as $module) {
                        $filePath = __DIR__ . "/../modules/{$module}/views/{$pane}/{$glyph}.php";
                        if (file_exists($filePath)) {
                            $element['__loadExternal'] = "View::$glyph";
                            break;
                        }
                    }
                }

                // 3. Default fallback: views/Default/{glyph}.php
                if (empty($element['__loadExternal']) && $pane != 'Default') {
                    foreach (\ClearView\Page::buildModuleStack() as $module) {
                        $filePath = __DIR__ . "/../modules/{$module}/views/Default/{$glyph}.php";
                        if (file_exists($filePath)) {
                            $element['__loadExternal'] = "View::$glyph";
                            break;
                        }
                    }
                }
            }

            if ($hasView) {
                // view="name" overrides element children.
                // Handle folder globs: load all matching files as sibling fragments.
                if (str_contains($element['view'], '*')) {
                    $pane = Facet::me()->getField('name') ?? 'Default';
                    $globChildren = [];
                    foreach (\ClearView\Page::buildModuleStack() as $module) {
                        $globPattern = __DIR__ . "/../modules/{$module}/views/{$pane}/{$element['view']}";
                        $files = glob($globPattern);
                        foreach ($files as $file) {
                            if (is_file($file)) {
                                // Load glob fragments standalone — no parent context so nested
                                // default-view lookups don't bleed into sibling fragments.
                                $subData = self::fromhtml(file_get_contents($file));
                                if (!empty($subData)) {
                                    if (isset($subData['glyph'])) {
                                        $globChildren[] = $subData;
                                    } elseif (isset($subData['children'])) {
                                        $globChildren = array_merge($globChildren, $subData['children']);
                                    }
                                }
                            }
                        }
                    }
                    if (!empty($globChildren)) {
                        $element['children'] = $globChildren;
                    }
                    // No __loadExternal for globs — already expanded inline.
                } else {
                    // Normal view reference
                    if (str_contains($element['view'], '::')) {
                        $element['__loadExternal'] = $element['view'];
                    } else {
                        $element['__loadExternal'] = "View::{$element['view']}";
                    }
                }
            } else {
                // No view override — process children normally.
                $children = [];
                foreach ($node->childNodes as $child) {
                    $processedChild = self::processNode($child, $context);
                    if (!empty($processedChild)) {
                        $children[] = $processedChild;
                    }
                }

                if ($node->childNodes->length === 1 && $node->firstChild instanceof \DOMText) {
                    $trimmedValue = trim($node->firstChild->nodeValue);
                    if ($trimmedValue !== '') {
                        $element['children'] = [ $trimmedValue ];
                    }
                } elseif (!empty($children)) {
                    $element['children'] = $children;
                }
            }

            return $element;
        }

        // Return empty array for any other node types (e.g., comments, etc.)
        return [];
    }
}

jsonmangler::__constructStatic();
?>

