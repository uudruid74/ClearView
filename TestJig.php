<?php

namespace ClearView;

use ClearView\Framework;

/**
 * TestJig — headless Framework for CLI testing.
 * Runs without ProcessWire by loading null crystals. Accepts
 * CLI arguments for configuration. Renders test views.
 * Usage: php TestJig.php --panename=MyPane --inlayname=TestInlay --view=my_test
 * @see \ClearView\Framework
 * @see \ClearView\Mosaic
 */
class TestJig extends Framework
{
    /** @var array CLI argument overrides */
    private array $cliArgs = [];

    /** @var array Static jig values for headless Input crystal */
    private static array $jig = [];

    /** @var string Current pane name for view resolution */
    protected string $panename = 'Default';

    /**
     * Creates a headless test Pane.
     * @param array $cliArgs  Key-value pairs from CLI (e.g. ['panename' => 'TestPage'])
     */
    public function __construct(array $cliArgs = [])
    {
        // Register as the active Framework BEFORE Mosaic::load()
        self::$instance = $this;

        $this->cliArgs = $cliArgs;

        // Build Mosaic::load() options — using module-based crystal loading
        $options = [
            'loadCrystals' => true,
            'loadCliData'  => $this->cliArgs,
        ];

        // If a snapshot is specified, load it
        if (isset($cliArgs['snapshot'])) {
            $options['loadSnapShot'] = $cliArgs['snapshot'];
        }

        Mosaic::load($options);
        $this->mosaic = Mosaic::instance();
    }

    /** Set a jig value for headless Input crystal access. */
    public static function setJig(string $key, mixed $value): void
    {
        self::$jig[$key] = $value;
    }

    /** Get a jig value. */
    public static function getJig(string $key): mixed
    {
        return self::$jig[$key] ?? null;
    }

    /**
     * Returns the module list with 'testjig' prepended.
     * TestJig loads null crystals from modules/testjig/crystals/
     * before vendor crystals, so headless tests run without
     * ProcessWire dependencies.
     * @return array<string>
     */
    public function getModuleList(): array
    {
        $modules = parent::getModuleList();
        array_unshift($modules, 'testjig');
        return array_values(array_unique($modules));
    }

    /**
     * Resolve a view file path across three levels:
     *   1. Inlay directory: modules/<module>/panes/<panename>/views/<name>.php
     *   2. Module path:      modules/<module>/views/<name>.php
     *   3. Global fallback:  main/views/<name>.php
     *
     * @param string $name View name (without .php extension)
     * @param string|null $panename Pane name for inlay-directory lookup (defaults to $this->panename)
     * @return string Resolved absolute file path
     * @throws Exception if no view is found
     */
    public function resolveViewPath(string $name, ?string $panename = null): string
    {
        $panename = $panename ?? $this->panename;

        // Level 1: Inlay directory (pane-specific views)
        foreach (Framework::Modules() as $module) {
            $path = __DIR__ . "/modules/{$module}/panes/{$panename}/views/{$name}.php";
            if (file_exists($path)) {
                return $path;
            }
        }

        // Level 2: Module path (module-level views)
        foreach (Framework::Modules() as $module) {
            $path = __DIR__ . "/modules/{$module}/views/{$name}.php";
            if (file_exists($path)) {
                return $path;
            }
        }

        // Level 3: Global fallback
        $path = __DIR__ . "/main/views/{$name}.php";
        if (file_exists($path)) {
            return $path;
        }

        throw new Exception("View not found: {$name}");
    }

    /**
     * Override view path to use module views when headless.
     * Uses resolveViewPath() for multi-level view resolution.
     */
    public function renderTestView(string $name, ?string $panename = null): void
    {
        $path = $this->resolveViewPath($name, $panename);
        include $path;
    }

    /**
     * Entry point for CLI test execution.
     * Parses argv, creates TestJig, renders the specified view.
     */
    public static function run(): void
    {
        $args = self::parseArgv();

        $rig = new self($args);

        $view = $args['view'] ?? 'default';
        $rig->renderTestView($view);

        // Flush any OOB/script output
	ClearView::dumpOOBdata();
    }

    /**
     * Parse CLI arguments into key-value pairs.
     * Supports: --key=value, --key value, --flag (sets value to true)
     * @return array Description.
     */
    private static function parseArgv(): array
    {
        global $argv;
        $args = [];
        for ($i = 1, $c = count($argv); $i < $c; $i++) {
            $arg = $argv[$i];
            if (str_starts_with($arg, '--')) {
                $arg = substr($arg, 2);
                if (str_contains($arg, '=')) {
                    [$key, $value] = explode('=', $arg, 2);
                    $args[$key] = $value;
                } elseif ($i + 1 < $c && !str_starts_with($argv[$i + 1], '--')) {
                    $args[$arg] = $argv[++$i];
                } else {
                    $args[$arg] = true;
                }
            }
        }
        return $args;
    }
}
