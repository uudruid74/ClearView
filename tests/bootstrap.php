<?php
/**
 * ClearView test bootstrap.
 *
 * Provides minimal ProcessWire stubs and loads the dependency tree
 * needed for ViewBuilder and fixture unit tests.
 */

// ── ProcessWire stubs (parse-time only) ────────────────────────────────────
namespace ProcessWire {
    if (!class_exists(\ProcessWire\WireException::class, false)) {
        class WireException extends \Exception {}
    }
    // Stub Page for Crystal::Page creation
    if (!class_exists(\ProcessWire\Page::class, false)) {
        class Page {
            public $id;
            public $url;
            public $name;
            public function __construct(array $fields = []) {
                foreach ($fields as $k => $v) { $this->$k = $v; }
            }
            public function get(string $key) { return $this->$key ?? null; }
        }
    }
}

namespace {
    const CLEARVIEW_ROOT = __DIR__ . '/..';

    // ── Core classes in dependency order ──────────────────────────────────
    // NOTE: Do NOT load crystals/Pane.php — it defines class Pane in the
    //       ClearView namespace, colliding with root Pane.php (Element).
    $files = [
        '/utility/jsonmangler.php',
        '/QueryParser.php',
        '/Shard.php',
        '/Page.php',
        '/Crystal.php',
        '/crystals/Config.php',
        '/Exception.php',
        '/Facet.php',
        '/Mosaic.php',
        '/ClearView.php',
        '/Element.php',
        '/Pane.php',
        // Test fixtures — load Fixture\TestFixtureException first (parent class)
        '/Test/Fixture/TestFixtureException.php',
        '/Test/TestFixtureException.php',
        '/Test/InlayRegistry.php',
        '/Test/StubPane.php',
        '/Test/Fixture/InlayStub.php',
        '/Test/Fixture/ViewBuilder.php',
    ];

    foreach ($files as $file) {
        $path = CLEARVIEW_ROOT . $file;
        if (file_exists($path)) {
            require_once $path;
        }
    }

    // ── Initialise Mosaic singleton ───────────────────────────────────────
    \ClearView\Mosaic::init();
}
