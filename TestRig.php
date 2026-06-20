<?php

namespace ClearView;

/**
 * TestRig — a Pane subclass for automated testing.
 *
 * Accepts CLI arguments or extended URL parameters. Uses null crystals
 * (no ProcessWire dependency) and can load/restore Mosaic snapshots
 * via the Facet tag stack.
 *
 * Tests are ClearView view files. They're PHP includes — assertions
 * are just `if (!condition) throw`. Facet's automatic variable restore
 * means test state doesn't leak between test cases.
 *
 * Usage (CLI):
 *   php -r "Mosaic::load(['overridePath'=>'null','loadCliData'=>[...]]); (new TestRig('Test'))->run();"
 *
 * Usage (test view):
 *   $facet->loadMosaic(['loadSnapShot'=>'known-state'])
 *         ->render()
 *         ->close();
 */
class TestRig extends Pane
{
    /** @var array CLI arguments or test parameters */
    private array $params;

    public function __construct(string $template = 'Test', array $params = [])
    {
        $this->params = $params;

        // Mosaic should already be loaded via Mosaic::load() with null crystals.
        // The constructor sets up the Pane from the Input crystal.
        if (!Mosaic::instance()) {
            Mosaic::load([
                'loadCrystals' => true,
                'overridePath' => 'null',
                'loadCliData'  => $params,
            ]);
        }

        parent::__construct($template);
    }

    /**
     * Runs all tests by rendering the Pane::body view.
     * Test cases are child elements in the view tree — they auto-render.
     */
    public function run(): void
    {
        $body = $this['Pane::body'];
        if ($body) {
            (new Facet($body))
                ->render()
                ->close();
        }
    }

    /**
     * Asserts a condition, throws on failure.
     */
    public static function assert(bool $condition, string $message = 'Assertion failed'): void
    {
        if (!$condition) {
            throw new Exception("TEST FAILED: {$message}");
        }
    }

    /**
     * Asserts two values are equal.
     */
    public static function assertEquals($expected, $actual, string $message = ''): void
    {
        $msg = $message ?: "Expected " . json_encode($expected) . ", got " . json_encode($actual);
        self::assert($expected === $actual, $msg);
    }
}
