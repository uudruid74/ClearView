<?php

namespace ClearView\Element;

use ClearView\Element;
use ClearView\Facet;
use ClearView\Mosaic;
use ClearView\Pane;
use ClearView\Exception;

/**
 * Outputs a Surreal-powered <script> tag that modifies the parent element.
 *
 * Supports attribute modifiers, content tags, and view-based layout changes.
 * Used inside ProcessWire page fields (e.g., Page::body) so the field can
 * directly manipulate the enclosing DOM element.
 *
 * Fields are iterated in order with Element::iterateFields(). Each field name
 * is an attribute to modify on the parent. Values may be comma-separated lists
 * with prefix modifiers:
 *   +  Add to list
 *   -  Remove from list
 *   ^  Toggle in list
 *   %  Take class (htmx.takeClass)
 *
 * Content tags (field name 'content' or 'contents'):
 *   -tag  Delete child elements with data-tag="tag" from parent
 *   +tag  Insert inner contents with data-tag="tag"
 *   %tag  Delete old then insert new with data-tag="tag"
 *
 * Layout switching:
 * When 'view' is set and differs from Shared::mainLayout, loads a new layout
 * view, updates Shared::mainLayout, and calls Pane::retargetResult('main') to
 * redirect the HTMX swap from the boosted <article> to the <main> element.
 *
 * @see ClearView\Element
 * @see ClearView\Facet
 * @see ClearView\Pane
 */
class attr extends Element
{
    /**
     * Render the <attr> element.
     *
     * Behavior branches on the 'view' field:
     * 1. view differs from Shared::mainLayout → layout switch: load new view,
     *    update Shared::mainLayout, call Pane::retargetResult('main').
     * 2. view matches Shared::mainLayout → render children normally.
     * 3. Other fields → generate Surreal JavaScript that applies them to me()
     *    (the parent element) after swap.
     *
     * @return void
     */
    public function render(): void
    {
        $view = $this->getField('view');
        $currentLayout = Mosaic::getVar('Shared::mainLayout');

        // Layout switching: view differs from current layout
        if ($view !== null && $view !== $currentLayout) {
            Mosaic::setVar('mainLayout', $view, 'Shared');

            // Load the new layout view
            $viewFile = Mosaic::getVar("ClearView::rootPath") . "/modules/vendor/views/{$view}.php";

            // Render the loaded view, replacing the current children
            (new Facet($this))
                ->open("<div>")
                ->load($viewFile)
                ->close();

            // Retarget the HTMX swap to <main> instead of the boosted <article>
            Pane::retargetResult('main');

            return;
        }

        // View matches or no view — generate attribute modifier script
        $fields = $this->getFields();
        $script = $this->buildSurrealScript($fields);

        if (!empty($script)) {
            // Open a script tag with the Surreal content
            (new Facet($this))
                ->open('<script type="text/surreal">')
                ->out($script)
                ->close();
        }

        // Render captured children even when emitting modifiers
        // (children are template-processed through the normal pipeline)
    }

    /**
     * Build a Surreal script that applies attribute modifiers to the parent element.
     *
     * Iterates over all fields (excluding 'view' which is handled separately),
     * generating me().setAttribute() calls for simple values and me().classList
     * operations for prefixed class modifiers.
     *
     * @param array $fields The element's field values.
     * @return string The Surreal JavaScript code.
     */
    private function buildSurrealScript(array $fields): string
    {
        $lines = [];

        foreach ($fields as $name => $value) {
            // Skip view (handled separately), id, and name (handled by framework)
            if ($name === 'view' || $name === 'id' || $name === 'name') {
                continue;
            }
            if ($value === null || $value === '') {
                continue;
            }

            // Content tags
            if ($name === 'content' || $name === 'contents') {
                $tags = explode(',', (string)$value);
                foreach ($tags as $tagExpr) {
                    $tagExpr = trim($tagExpr);
                    if (empty($tagExpr)) continue;

                    $prefix = $tagExpr[0];
                    $tag = substr($tagExpr, 1);

                    switch ($prefix) {
                        case '-':
                            $lines[] = "me().querySelectorAll('[data-tag=\"{$tag}\"]').forEach(el => el.remove());";
                            break;
                        case '+':
                            $lines[] = "me().insertAdjacentHTML('beforeend', `<span data-tag=\"{$tag}\">{$this->getValue()}</span>`);";
                            break;
                        case '%':
                            $escapedTag = addslashes($tag);
                            $lines[] = "me().querySelectorAll('[data-tag=\"{$tag}\"]').forEach(el => el.remove());";
                            $lines[] = "me().insertAdjacentHTML('beforeend', `<span data-tag=\"{$tag}\">{$this->getValue()}</span>`);";
                            break;
                    }
                }
                continue;
            }

            // Check for comma-separated values with modifiers (for class etc.)
            if (str_contains((string)$value, ',')) {
                $parts = array_map('trim', explode(',', (string)$value));
                foreach ($parts as $part) {
                    if (empty($part)) continue;
                    $prefix = $part[0];
                    $modValue = substr($part, 1);

                    if (in_array($prefix, ['+', '-', '^', '%'], true)) {
                        $lines[] = $this->modifierLine($name, $prefix, $modValue);
                    } else {
                        $lines[] = "me().setAttribute('{$name}', '{$part}');";
                    }
                }
            } else {
                $prefix = $value[0] ?? '';
                if (in_array($prefix, ['+', '-', '^', '%'], true)) {
                    $modValue = substr((string)$value, 1);
                    $lines[] = $this->modifierLine($name, $prefix, $modValue);
                } else {
                    $escapedValue = addslashes((string)$value);
                    $lines[] = "me().setAttribute('{$name}', '{$escapedValue}');";
                }
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Generate a Surreal modifier line for a class-list operation.
     *
     * @param string $attr The attribute name (e.g., 'class').
     * @param string $prefix The modifier prefix (+, -, ^, %).
     * @param string $value The value to apply.
     * @return string The Surreal JavaScript line.
     */
    private function modifierLine(string $attr, string $prefix, string $value): string
    {
        $escapedValue = addslashes($value);

        if ($attr === 'class') {
            return match ($prefix) {
                '+' => "me().classList.add('{$escapedValue}');",
                '-' => "me().classList.remove('{$escapedValue}');",
                '^' => "me().classList.toggle('{$escapedValue}');",
                '%' => "htmx.takeClass(me(), '{$escapedValue}');",
                default => "me().classList.add('{$escapedValue}');",
            };
        }

        // Non-class attributes with modifiers
        return match ($prefix) {
            '+' => "me().setAttribute('{$attr}', (me().getAttribute('{$attr}')||'') + ' {$escapedValue}');",
            '-' => "me().removeAttribute('{$attr}');",
            default => "me().setAttribute('{$attr}', '{$escapedValue}');",
        };
    }

    /**
     * Get the inner value/content of the <attr> element.
     *
     * @return string The inner content.
     */
    private function getValue(): string
    {
        return $this->getField('value') ?? '';
    }

    /**
     * Get all fields set on this element.
     *
     * @return array
     */
    private function getFields(): array
    {
        return $this->getFieldData() ?? [];
    }
}
// end of class
