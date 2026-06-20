<?php

namespace ClearView;

/**
 * TestRig — headless Pane for CLI testing.
 *
 * Runs without ProcessWire by loading null crystals. Accepts
 * CLI arguments for configuration. Renders test views.
 *
 * Usage: php TestRig.php --panename=MyPane --inlayname=TestInlay --view=my_test
 *
 * @see \ClearView\Pane
 * @see \ClearView\Mosaic
 */
class TestRig extends Pane
{
    /** @var array CLI argument overrides */
    private array $cliArgs = [];

    /**
     * Creates a headless test Pane.
     *
     * @param string $template Always 'CLI' — no ProcessWire template.
     * @param array $cliArgs  Key-value pairs from CLI (e.g. ['panename' => 'TestPage'])
     */
    public function __construct(string $template = 'CLI', array $cliArgs = [])
    {
        $this->cliArgs = $cliArgs;

        // Build Mosaic::load() options — use null crystals (no ProcessWire)
        $options = [
            'loadCrystals' => true,
            'overridePath' => 'null',
            'loadCliData'  => $this->cliArgs,
        ];

        // If a snapshot is specified, load it
        if (isset($cliArgs['snapshot'])) {
            $options['loadSnapShot'] = $cliArgs['snapshot'];
        }

        Mosaic::load($options);

        // Set routing state from CLI args or fall back to defaults
        ClearView::panename($cliArgs['panename'] ?? 'TestRig');
        ClearView::inlayname($cliArgs['inlayname'] ?? 'Default');
        ClearView::method($cliArgs['methodname'] ?? 'open');
        ClearView::paneobj($this);

        $this->mosaic = Mosaic::instance();
    }

    /**
     * Render a test view file.
     *
     * Test views are plain PHP files that use the Facet rendering
     * pipeline directly. They live in views/ and are included inline.
     *
     * @param string $name View name (views/<name>.php)
     */
    public function renderTestView(string $name): void
    {
        $path = __DIR__ . "/views/{$name}.php";
        if (!file_exists($path)) {
            throw new Exception("Test view not found: {$path}");
        }
        include $path;
    }

    /**
     * Entry point for CLI test execution.
     *
     * Parses argv, creates TestRig, renders the specified view.
     */
    public static function run(): void
    {
        $args = self::parseArgv();

        $rig = new self('CLI', $args);

        $view = $args['view'] ?? 'default';
        $rig->renderTestView($view);

        // Flush any OOB/script output
        $rig['ClearView']->dumpOOBdata();
    }

    /**
     * Parse CLI arguments into key-value pairs.
     *
     * Supports: --key=value, --key value, --flag (sets value to true)
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
