<?php

namespace ClearView\Test;

use ClearView\ClearView;
use ClearView\Test\Fixture\InlayStub;

/**
 * Central registry for InlayStub fixtures.
 * Consulted by ClearView::loadInlay() when ClearView::inTesting()
 * is true.  Registers stub panes that return synthetic data so
 * tests can bypass the real module/glyph filesystem lookup.
 */
class InlayRegistry
{
    /** @var array<string,InlayStub> keyed by "panename:inlayname" */
    private static array $stubs = [];

    /** Register an InlayStub for a given pane/inlay pair. */


    public static function register(InlayStub $stub): void
    {
        $key = $stub->panename . ':' . $stub->inlayname;
        self::$stubs[$key] = $stub;
    }

    /** Check whether a stub is registered for the given pane/inlay. */


    public static function hasStub(string $panename, string $inlayname): bool
    {
        $key = $panename . ':' . $inlayname;
        return isset(self::$stubs[$key]);
    }

    /**
     * Return the class name to instantiate for the given pane/inlay.
     * Uses InlayRegistry to dynamically generate a StubPane
     * subclass that will render the stub's data.
     * @return string Fully-qualified class name
     */
    public static function getClass(string $panename, string $inlayname): string
    {
        $key   = $panename . ':' . $inlayname;
        $stub  = self::$stubs[$key];

        // If the stub has a custom pane class, return it directly.
        if ($stub->paneClass !== null) {
            return $stub->paneClass;
        }

        // Generate a unique class name for this pane/inlay pair.
        $suffix  = str_replace(['\\', '/', '-', ' '], '_', $panename . '_' . $inlayname);
        $class   = 'ClearView\\Test\\StubPane_' . $suffix;

        if (!class_exists($class, false)) {
            // Dynamically define a subclass of StubPane that bakes in the
            // stub data so render() emits it.
            $parentClass = StubPane::class;
            $encoded     = var_export($stub->data, true);
            $callable    = $stub->callable;

            if ($callable !== null) {
                // Store callable in a static property so the stub can invoke it.
                $callableKey = $panename . ':' . $inlayname;
                StubPane::$callables[$callableKey] = $callable;
                $code = "namespace ClearView\\Test;\n"
                      . "class StubPane_{$suffix} extends \\ClearView\\Test\\StubPane {\n"
                      . "    protected static \$stubData = null;\n"
                      . "    protected static \$stubCallableKey = " . var_export($callableKey, true) . ";\n"
                      . "    protected function getStubData(): array {\n"
                      . "        return (\\ClearView\\Test\\StubPane::\$callables[self::\$stubCallableKey])(\$this->panename, \$this->inlayname, []);\n"
                      . "    }\n"
                      . "}";
            } else {
                $code = "namespace ClearView\\Test;\n"
                      . "class StubPane_{$suffix} extends \\ClearView\\Test\\StubPane {\n"
                      . "    protected static \$stubData = {$encoded};\n"
                      . "    protected function getStubData(): array { return self::\$stubData; }\n"
                      . "}";
            }
            eval($code);
        }

        return $class;
    }

    /** Reset all registered stubs (called between tests). */


    public static function reset(): void
    {
        self::$stubs = [];
        StubPane::$callables = [];
    }
}
