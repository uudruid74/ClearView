<?php

namespace ClearView\Test\Fixture;

use ClearView\Mosaic;
use ClearView\Facet;

/**
 * Minimal ViewBuilder — assembles a view / element tree at runtime
 * for headless testing.
 *
 * Full implementation in sibling task t_3f36d1d2.
 * This version provides just enough for glyph-render tests to pass.
 */
class ViewBuilder
{
    private string $panename;
    private string $inlayname;

    /** @var array<string, \ClearView\Element> */
    private array $elements = [];

    private function __construct(string $panename, string $inlayname)
    {
        $this->panename  = $panename;
        $this->inlayname = $inlayname;
    }

    /**
     * Create a new builder, resetting ClearView / Mosaic / Facet state.
     */
    public static function new(
        string $panename = 'TestPage',
        string $inlayname = 'Default'
    ): self {
        // Re-init Mosaic to get a clean state.
        self::tearDownMosaic();
        Mosaic::init();

        // Initialise ClearView singleton (headless — no ProcessWire server).
        // Element::__construct accesses ClearView::CurrentPane(),
        // which dereferences self::$instance->creator.
        self::initClearView($panename, $inlayname);

        // Seed minimal variables so templates resolve.
        // Use the 3-arg form to store the value directly under the
        // 'Page' inlay (bypassing the Page Crystal's setVar, which
        // requires ProcessWire's Sanitizer).
        Mosaic::setVar('name', $panename, 'Pane');
        Mosaic::setVar('url', '/' . $panename . '/', 'Page');

        return new self($panename, $inlayname);
    }

    /**
     * Add an Element (glyph) by id.
     *
     * @param string $id    Shard id.
     * @param string $tag   HTML tag / glyph name (e.g. 'button', 'input').
     * @param array  $attrs Element fields (value, hx-post, class, etc.).
     */
    public function withElement(string $id, string $tag, array $attrs = []): self
    {
        $class = '\\ClearView\\Element\\' . $tag;

        if (!class_exists($class)) {
            throw new \ClearView\Test\TestFixtureException(
                "Unknown glyph class: {$class}"
            );
        }

        $data = array_merge([
            'id'    => $id,
            'name'  => $id,
            'glyph' => $tag,
            'inlay' => $this->inlayname,
        ], $attrs);

        $element = new $class($data);
        $this->elements[$id] = $element;

        return $this;
    }

    /**
     * Retrieve a previously added element by id.
     */
    public function getElement(string $id): \ClearView\Element
    {
        if (!isset($this->elements[$id])) {
            throw new \ClearView\Test\TestFixtureException(
                "No element named '{$id}' in builder"
            );
        }
        return $this->elements[$id];
    }

    /**
     * Render a specific named element, capturing its output.
     */
    public function render(?string $id = null): string
    {
        if ($id === null) {
            $id = array_key_first($this->elements);
        }

        if (!isset($this->elements[$id])) {
            throw new \ClearView\Test\TestFixtureException(
                "No element named '{$id}' in builder"
            );
        }

        $element = $this->elements[$id];

        // Facet::me() falls back to ClearView::CurrentPane() when
        // the tag stack is empty.  Set the current pane to this
        // element so that template variable lookups ({{id}}, {{value}},
        // etc.) resolve against it.
        \ClearView\ClearView::CurrentPane($element);

        \ob_start();
        $element->render();
        return \ob_get_clean();
    }

    // ── internal helpers ──────────────────────────────────────────

    /**
     * Initialise the ClearView singleton just enough that
     * Element::__construct doesn't crash on CurrentPane().
     */
    private static function initClearView(string $panename, string $inlayname): void
    {
        try {
            $ref = new \ReflectionProperty(\ClearView\ClearView::class, 'instance');
            $ref->setAccessible(true);

            // Create the singleton via its protected constructor.
            $cv = new \ReflectionClass(\ClearView\ClearView::class);
            $instance = $cv->newInstanceWithoutConstructor();

            // Manually call the protected constructor.
            $ctor = $cv->getConstructor();
            $ctor->setAccessible(true);
            $ctor->invoke($instance);

            // Set panename / inlayname so id() and inlay() work.
            $refPn = new \ReflectionProperty(\ClearView\ClearView::class, 'panename');
            $refPn->setAccessible(true);
            $refPn->setValue($instance, $panename);

            $refIn = new \ReflectionProperty(\ClearView\ClearView::class, 'inlayname');
            $refIn->setAccessible(true);
            $refIn->setValue($instance, $inlayname);

            $ref->setValue($instance);
        } catch (\ReflectionException $e) {
            // If ClearView isn't loaded yet, skip.
        }
    }

    /**
     * Tear down the Mosaic singleton so ::init() can be called again.
     */
    private static function tearDownMosaic(): void
    {
        // Mosaic has a protected __clone / __wakeup — we use
        // reflection to null out the singleton.
        try {
            $ref = new \ReflectionProperty(Mosaic::class, 'instance');
            $ref->setAccessible(true);
            $ref->setValue(null);
        } catch (\ReflectionException $e) {
            // Mosaic may not have been loaded yet — that's fine.
        }

        // Reset Facet static state.
        try {
            $ref = new \ReflectionProperty(Facet::class, 'data');
            $ref->setAccessible(true);
            $ref->setValue(null, []);
        } catch (\ReflectionException $e) {
            // Facet may not have been loaded.
        }
    }
}
