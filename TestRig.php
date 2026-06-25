<?php

namespace ClearView;

use ClearView\Framework;

/**
 * TestRig — headless Framework for CLI testing.
 * Runs without ProcessWire by loading null crystals. Accepts
 * CLI arguments for configuration. Renders test views.
 * Usage: php TestRig.php --panename=MyPane --inlayname=TestInlay --view=my_test
 * @see \ClearView\Runtime
 * @see \ClearView\Mosaic
 */
class TestRig extends Framework
{
    /** @var array CLI argument overrides */
    private array $cliArgs = [];

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

    /**
     * Returns the module list with 'testjig' prepended.
     * TestRig loads null crystals from modules/testjig/crystals/
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
     * Override view path to use module views when headless.
     */
    public function renderTestView(string $name): void
    {
        // Try module vendor views first, then root views
        $path = __DIR__ . "/modules/vendor/views/{$name}.php";
        if (!file_exists($path)) {
            $path = __DIR__ . "/views/{$name}.php";
        }
        if (!file_exists($path)) {
            throw new Exception("View not found: {$name}");
        }
        include $path;
    }

    /**
     * Entry point for CLI test execution.
     * Parses argv, creates TestRig, renders the specified view.
     */
    public static function run(): void
    {
        $args = self::parseArgv();

        $rig = new self('CLI', $args);

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
