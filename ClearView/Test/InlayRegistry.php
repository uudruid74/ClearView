<?php

namespace ClearView\Test;

/**
 * Central registry for InlayStub fixtures.
 *
 * Keyed by "panename/inlayname".  When ClearView::loadInlay() detects a
 * test environment, it consults this registry first.  If a stub is
 * registered, the generated stub class is returned; otherwise the
 * normal module search proceeds.
 */
class InlayRegistry
{
    /** @var array<string, mixed> stub payloads keyed by "panename/inlayname" */
    private static array $stubs = [];

    /**
     * Check whether a stub is registered for the given pane/inlay.
     */
    public static function hasStub(string $panename, string $inlayname): bool
    {
        return isset(self::$stubs["{$panename}/{$inlayname}"]);
    }

    /**
     * Return the fully-qualified stub Pane class name.
     *
     * Always returns StubPane — the actual data is resolved at runtime
     * from the registry keyed by panename/inlayname.
     */
    public static function getClass(string $panename, string $inlayname): string
    {
        return StubPane::class;
    }

    /**
     * Register stub data for a pane/inlay pair.
     *
     * @param string $panename
     * @param string $inlayname
     * @param mixed $data The stub payload (array or Shard).
     */
    public static function register(string $panename, string $inlayname, mixed $data): void
    {
        self::$stubs["{$panename}/{$inlayname}"] = $data;
    }

    /**
     * Retrieve stub data for a pane/inlay pair.
     *
     * @return mixed The registered stub payload, or null if not found.
     */
    public static function getStub(string $panename, string $inlayname): mixed
    {
        return self::$stubs["{$panename}/{$inlayname}"] ?? null;
    }

    /**
     * Remove a registered stub (for test teardown).
     */
    public static function unregister(string $panename, string $inlayname): void
    {
        unset(self::$stubs["{$panename}/{$inlayname}"]);
    }

    /**
     * Clear all registered stubs (for global reset between tests).
     */
    public static function reset(): void
    {
        self::$stubs = [];
    }
}
