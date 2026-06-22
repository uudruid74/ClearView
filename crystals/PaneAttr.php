<?php

namespace ClearView;

/**
 * PaneAttr crystal — HTML attribute manager for the pane element.
 *
 * Stores pane-level HTML attributes (modules, class, update, and any
 * hx-vals passed from the <pane> element) in the "PaneAttr" Mosaic
 * inlay.  Detects changes and outputs OOB JavaScript to update the
 * parent pane element's attributes in the browser.
 *
 * Template usage: {{PaneAttr::modules}}, {{PaneAttr::class}}, etc.
 *
 * @see \ClearView\Crystal
 * @see \ClearView\Page       (buildModuleStack reads PaneAttr::modules)
 */
class PaneAttr extends Crystal
{
    /**
     * Initialize the PaneAttr crystal.
     *
     * Registers under the "PaneAttr" inlay name.  The ProcessWire
     * object is always null — PaneAttr is pure Mosaic state.
     */
    public function __construct($pwObject = null, ?string $name = null, ?string $inlay = null, $mosaic = null)
    {
        // PaneAttr has no ProcessWire backing — pure Mosaic storage.
        parent::__construct(null, 'PaneAttr', 'ClearView', $mosaic);
    }

    /**
     * Get a PaneAttr value from Mosaic.
     *
     * Reads from the "PaneAttr" Mosaic inlay.  If the value doesn't
     * exist in Mosaic, returns null (no ProcessWire fallback — unlike
     * the Pane crystal).
     *
     * @param string|null $key  Attribute name (modules, class, update, …)
     * @return mixed  The value, or null.
     */
    public function getVar($key = null)
    {
        if ($key === null || $key === '') {
            return null;
        }

        $shard = Mosaic::index('PaneAttr', $key);
        if ($shard) {
            return $shard->getField('value') ?? $shard;
        }

        return null;
    }

    /**
     * Set a PaneAttr value in Mosaic and output OOB JavaScript
     * to update the pane element's HTML attribute.
     *
     * @param string $key   Attribute name
     * @param mixed  $value Attribute value
     */
    public function setVar(string $key, $value): void
    {
        // Store in Mosaic under "PaneAttr" inlay
        $existing = Mosaic::index('PaneAttr', $key);
        if ($existing) {
            $existing->setField('value', $value);
            Mosaic::checkShard($existing);
        } else {
            Shard::loadShard([
                'id'    => $key,
                'inlay' => 'PaneAttr',
                'value' => $value,
            ]);
        }

        // Output OOB JavaScript to update the pane element's attribute.
        // Simple key-value pairs like `modules` don't need special JS —
        // the Mosaic update mechanism handles rendering changes.
        // For class changes, delegate to attr glyph's pattern.
        if ($key === 'class') {
            $escaped = addslashes((string)$value);
            echo "<script>me().attribute('class','{$escaped}')</script>";
        }
    }
}
