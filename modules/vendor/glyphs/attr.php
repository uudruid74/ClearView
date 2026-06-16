<?php

namespace ClearView\Element;

use ClearView\Element;
use ClearView\Facet;
use ClearView\Pane;
use ClearView\Shared;

/**
 * attr glyph — outputs a Surreal-powered <script> tag that modifies the
 * parent element, or triggers a layout change when {@code view} is set.
 *
 * <p>Supports attribute modifiers ({@code +add -remove ^toggle %takeClass}),
 * content tag semantics ({@code -tag +tag %tag}), and view-based layout
 * retargeting via {@link Pane::retargetResult()}.</p>
 *
 * @see Glyph-attr
 * @see Runtime-Pane
 */
class attr extends Element
{
    /**
     * Renders the attr glyph.
     *
     * <p>Three branches:</p>
     * <ol>
     *   <li><b>view</b> set and differs from {@link Shared::$mainLayout} —
     *       load the view file, retarget to {@code main}, fire inlaychange.</li>
     *   <li><b>view</b> set and matches — render children normally.</li>
     *   <li>No view — generate a Surreal {@code <script>} tag that applies
     *       attribute modifiers and content tags to the parent element in
     *       an {@code afterSwap} handler.</li>
     * </ol>
     *
     * @return void
     */
    public function render(): void
    {
        $view = $this->getField('view');

        // Branch 1: view-based layout change
        if ($view !== null && $view !== Shared::$mainLayout) {
            Shared::$mainLayout = $view;

            $viewFile = __DIR__ . "/../../views/{$view}.php";
            if (file_exists($viewFile)) {
                include $viewFile;
            }

            Pane::retargetResult('main');
            ClearView::CurrentPane()->triggerevent('inlaychange');
            return;
        }

        // Branch 2: view matches current layout — render children normally
        if ($view !== null) {
            $this->html();
            return;
        }

        // Branch 3: generate Surreal JavaScript for attribute/content modifiers
        $script = $this->buildSurrealScript();
        if ($script !== '') {
            (new Facet($this))->out($script);
        }
    }

    /**
     * Builds the Surreal <script> tag that modifies the parent element.
     *
     * <p>Iterates all fields via {@link Shard::iterateFields()}, collecting
     * modifier operations and content-tag instructions. Returns an empty
     * string when there is nothing to emit.</p>
     *
     * @return string The complete <script> block, or empty string.
     */
    private function buildSurrealScript(): string
    {
        $ops        = [];  // afterSwap attribute-modifier statements
        $removeOps  = [];  // pre-swap content-tag removals
        $hasContentInsert = false;
        $childrenHTML = '';

        $this->iterateFields(function ($value, $key) use (&$ops, &$removeOps, &$hasContentInsert, &$childrenHTML) {
            // Skip structural and already-handled fields
            if ($key === 'view' || $key === 'children') {
                return null;
            }

            // ── content / contents field ──
            if ($key === 'content' || $key === 'contents') {
                $tags = array_map('trim', explode(',', (string)$value));
                foreach ($tags as $tag) {
                    if ($tag === '') {
                        continue;
                    }
                    $prefix  = $tag[0];
                    $name    = substr($tag, 1);
                    $escaped = addslashes($name);

                    if ($prefix === '-') {
                        // Delete tagged children from parent after swap
                        $removeOps[] = "me().find('[data-tag=\"{$escaped}\"]').remove();";
                    } elseif ($prefix === '+') {
                        // Insert children with data-tag (output below script)
                        $hasContentInsert = true;
                    } elseif ($prefix === '%') {
                        // Delete old, then insert new
                        $removeOps[] = "me().find('[data-tag=\"{$escaped}\"]').remove();";
                        $hasContentInsert = true;
                    }
                }
                return null;
            }

            // ── attribute modifiers ──
            $values = array_map('trim', explode(',', (string)$value));
            foreach ($values as $val) {
                if ($val === '') {
                    continue;
                }

                $prefix = $val[0];

                // Check for modifier prefix
                if ($prefix === '+' || $prefix === '-' || $prefix === '^' || $prefix === '%') {
                    $name    = substr($val, 1);
                    $escaped = addslashes($name);

                    if ($key === 'class') {
                        switch ($prefix) {
                            case '+': $ops[] = "me().classAdd('{$escaped}');"; break;
                            case '-': $ops[] = "me().classRemove('{$escaped}');"; break;
                            case '^': $ops[] = "me().classToggle('{$escaped}');"; break;
                            case '%': $ops[] = "me().takeClass('{$escaped}');"; break;
                        }
                    }
                    // For non-class attributes with modifiers — skip (modifiers are class-specific)
                } else {
                    // No modifier — set as plain attribute on parent
                    $escapedKey = addslashes((string)$key);
                    $escapedVal = addslashes($val);
                    if ($key === 'class') {
                        $ops[] = "me().classAdd('{$escapedVal}');";
                    } elseif ($val !== '') {
                        $ops[] = "me().attribute('{$escapedKey}', '{$escapedVal}');";
                    } else {
                        // Boolean attribute (e.g., hidden="")
                        $ops[] = "me().attribute('{$escapedKey}', '');";
                    }
                }
            }

            return null;
        });

        // ── Render content-insert children if needed ──
        if ($hasContentInsert) {
            ob_start();
            $this->html();
            $childrenHTML = ob_get_clean();
        }

        // ── Nothing to emit ──
        if (empty($ops) && empty($removeOps) && !$hasContentInsert) {
            return '';
        }

        // ── Build the script ──
        $jsLines = [];

        // Content-tag removals run BEFORE swap (synchronous) to avoid
        // removing the newly-inserted tagged content.
        if (!empty($removeOps)) {
            $removeJS = implode("\n", $removeOps);
            $jsLines[] = $removeJS;
        }

        // Attribute modifiers and non-content operations run afterSwap
        if (!empty($ops)) {
            $opsJS = implode("\n  ", $ops);
            $jsLines[] = "me().afterSwap(function() {\n  {$opsJS}\n});";
        }

        $scriptBody = implode("\n", $jsLines);
        $script = "<script>\n{$scriptBody}\n</script>";

        // Append wrapped children for content-insert tags
        if ($hasContentInsert && $childrenHTML !== '') {
            $script .= "\n" . $childrenHTML;
        }

        return $script;
    }
}
