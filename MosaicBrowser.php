<?php

namespace ClearView;

/**
 * Mosaic Browser — CLI REPL for exploring panes without a web browser.
 *
 * Instead of rendering HTML and parsing htmx responses, it directly
 * manipulates the Mosaic and re-invokes the Framework. Every named
 * Shard in the Mosaic becomes an interactive element.
 *
 * Usage:
 *   php MosaicBrowser.php --url=/loginform/newaccount/
 *   php MosaicBrowser.php --pane=loginform --inlay=newaccount
 *   php MosaicBrowser.php --url=/loginform/newaccount/ --dump
 *
 * @see \ClearView\TestRig
 * @see \ClearView\Mosaic
 */
class MosaicBrowser extends TestRig
{
    private bool $dump = false;
    private string $panename;
    private string $inlayname;
    private string $methodname = 'init';
    /** @var string|null Direct view to render (headless mode) */
    private ?string $view = null;
    /** @var array Captured HTMX trigger events from response headers */
    private array $capturedEvents = [];

    public function __construct(array $cliArgs = [])
    {
        $this->dump = !empty($cliArgs['dump']);
        $this->view = $cliArgs['view'] ?? null;
        $this->resolveUrl($cliArgs);
        parent::__construct($cliArgs);
    }

    private function resolveUrl(array $args): void
    {
        if (!empty($args['url'])) {
            $url = trim($args['url'], '/');
            $segments = explode('/', $url);
            $this->panename = $segments[0] ?? 'Default';
            $this->inlayname = $segments[1] ?? 'Default';
            $this->methodname = $segments[2] ?? 'init';
        } else {
            $this->panename = $args['pane'] ?? $args['panename'] ?? 'Default';
            $this->inlayname = $args['inlay'] ?? $args['inlayname'] ?? 'Default';
            $this->methodname = $args['method'] ?? 'init';
        }
    }

    public function getModuleList(): array
    {
        $modules = parent::getModuleList();
        if (!in_array('dummy', $modules)) {
            $modules[] = 'dummy';
        }
        return $modules;
    }

    public function bootstrap(): void
    {
        $this->setInput($this->panename, $this->inlayname, $this->methodname);
        $this->loadPane();
    }

    /**
     * Override Framework's header sender to capture events instead.
     */
    public function onSendHtmxHeader(string $header, $event, $params): void
    {
        $eventName = is_array($params) ? $event : (string)$params;
        $eventData = is_array($params) ? $params : ['event' => $eventName];
        $this->capturedEvents[$eventName] = $eventData;
    }

    private function setInput(string $pane, string $inlay, string $method): void
    {
        Mosaic::setVar('panename', $pane, 'Input');
        Mosaic::setVar('inlayname', $inlay, 'Input');
        Mosaic::setVar('methodname', $method, 'Input');
        Mosaic::setVar('url', "/{$pane}/{$inlay}/{$method}/", 'Input');
    }

    private function loadPane(): void
    {
        $this->capturedEvents = [];
        try {
            if ($this->view) {
                ob_start();
                $this->renderTestView($this->view);
                $html = ob_get_clean();
                // Parse rendered HTML into Mosaic Shards
                $data = jsonmangler::fromhtml($html, $this->view);
                Shard::loadShard($data, inlay: $this->inlayname);
                if ($this->dump) {
                    echo "\n--- Response ---\n{$html}\n--- End Response ---\n";
                }
            } else {
                ob_start();
                $this->html();
                $output = ob_get_clean();
                if ($this->dump && $output) {
                    echo "\n--- Response ---\n{$output}\n--- End Response ---\n";
                }
            }
            ClearView::dumpOOBdata();
            $this->processEvents();
        } catch (\Throwable $e) {
            if (ob_get_level()) ob_end_clean();
            echo "Error: " . $e->getMessage() . "\n";
        }
    }

    private function processEvents(): void
    {
        foreach ($this->capturedEvents as $name => $data) {
            if ($name === 'inlaychange' && isset($data['inlay'])) {
                $old = $this->inlayname;
                $this->inlayname = $data['inlay'];
                echo "  \xe2\x86\xb3 inlaychange: {$old} \xe2\x86\x92 {$this->inlayname}\n";
            }
        }
    }

    public function show(): void
    {
        $items = $this->getInteractables();
        if (empty($items)) {
            echo "(empty Mosaic)\n";
            return;
        }

        echo "\n" . str_repeat('═', 72) . "\n";
        printf("  %-30s %s\n", $this->panename . '/' . $this->inlayname, '[q=quit h=help]');
        echo str_repeat('─', 72) . "\n";
        printf("  %-3s %-10s %-25s %s\n", '#', 'Glyph', 'Name', 'Value/Action');

        $i = 1;
        foreach ($items as $item) {
            $value = $item['value'] ?? '';
            if (strlen($value) > 30) {
                $value = substr($value, 0, 27) . '...';
            }
            $label = $item['action'] ? "{$value}  [run]" : $value;
            printf("  %-3d %-10s %-25s %s\n", $i, $item['glyph'], $item['name'], $label);
            $i++;
        }
        echo str_repeat('═', 72) . "\n";
    }

    public function getInteractables(): array
    {
        $items = [];
        $inlayShards = Mosaic::getShardsByInlay($this->inlayname);

        foreach ($inlayShards as $shard) {
            if (!$shard instanceof Shard) continue;
            if ($shard->isAnonymous()) continue;

            $name = $shard->getField('name') ?? $shard->getField('id');
            if (empty($name)) continue;

            $glyph = $shard->getField('glyph') ?? 'Shard';
            $value = (string)$shard;

            $url = $shard->getField('hx-post')
                ?? $shard->getField('hx-get')
                ?? $shard->getField('href')
                ?? null;

            $items[] = [
                'name'   => $name,
                'glyph'  => $glyph,
                'value'  => $value,
                'url'    => $url,
                'action' => $url !== null,
                'shard'  => $shard,
            ];
        }
        return $items;
    }

    public function set(string $name, string $value): void
    {
        Mosaic::setVar($name, $value, $this->inlayname);
        echo "OK — {$name} = {$value}\n";
    }

    public function invoke(string $name): void
    {
        $items = $this->getInteractables();
        foreach ($items as $item) {
            if ($item['name'] !== $name) continue;
            if (!$item['url']) {
                echo "'{$name}' has no URL to execute.\n";
                return;
            }

            $url = trim($item['url'], '/');
            $segments = explode('/', $url);
            $this->panename = $segments[0] ?? $this->panename;
            $this->inlayname = $segments[1] ?? $this->inlayname;
            $this->methodname = $segments[2] ?? 'submit';

            $this->setInput($this->panename, $this->inlayname, $this->methodname);
            echo "→ {$this->panename}/{$this->inlayname}/{$this->methodname}/\n";

            // In view mode, load inlay and dispatch command directly
            if ($this->view) {
                try {
                    $className = Inlay::load($this->panename, $this->inlayname);
                    $inlay = new $className();
                    ob_start();
                    $inlay->{$this->methodname}();
                    $output = ob_get_clean();
                    // Reset to view inlay so show() reads dispatch results
                    $this->inlayname = 'Default';
                    if ($this->dump && $output) {
                        echo "\n--- Response ---\n{$output}\n--- End Response ---\n";
                    }
                } catch (\Throwable $e) {
                    echo "  error: " . $e->getMessage() . "\n";
                }
                return;
            }

            $this->loadPane();
            return;
        }
        echo "No element named '{$name}' found.\n";
    }

    public function toggleDump(): void
    {
        $this->dump = !$this->dump;
        echo "Dump: " . ($this->dump ? 'ON' : 'OFF') . "\n";
    }

    public function repl(): void
    {
        $this->bootstrap();

        while (true) {
            $this->show();
            echo "\n> ";
            $line = trim(fgets(STDIN));
            if ($line === '' || $line === false) continue;

            $parts = preg_split('/\s+/', $line, 3);
            $cmd = strtolower($parts[0] ?? '');

            switch ($cmd) {
                case 'q': case 'quit': case 'exit': return;
                case 'h': case 'help':
                    echo "  set <name> = <value>   set a value\n";
                    echo "  run <name>             execute a method\n";
                    echo "  show / refresh         redisplay\n";
                    echo "  dump                   toggle response dump\n";
                    echo "  quit / exit / q        exit\n";
                    echo "  <number>               interact with item #N\n";
                    break;
                case 'show': case 'refresh': break;
                case 'dump': $this->toggleDump(); break;

                case 'set':
                    $rest = $parts[1] . (isset($parts[2]) ? ' ' . $parts[2] : '');
                    if (preg_match('/^(\S+)\s*=\s*(.+)$/', $rest, $m)) {
                        $this->set($m[1], $m[2]);
                    } else {
                        echo "Usage: set <name> = <value>\n";
                    }
                    break;

                case 'run':
                    if (isset($parts[1])) {
                        $this->invoke($parts[1]);
                    } else {
                        echo "Usage: run <name>\n";
                    }
                    break;

                default:
                    if (is_numeric($cmd)) {
                        $items = $this->getInteractables();
                        $idx = (int)$cmd - 1;
                        if (isset($items[$idx])) {
                            if ($items[$idx]['action']) {
                                $this->invoke($items[$idx]['name']);
                            } else {
                                echo "  {$items[$idx]['glyph']} {$items[$idx]['name']} = {$items[$idx]['value']}\n";
                            }
                        } else {
                            echo "Invalid number.\n";
                        }
                    } else {
                        echo "Unknown: {$cmd}\n";
                    }
                    break;
            }
        }
    }

    public static function main(): void
    {
        $args = self::parseArgs();
        $browser = new self($args);
        $browser->repl();
    }

    private static function parseArgs(): array
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

if (PHP_SAPI === 'cli' && isset($GLOBALS['argv']) && realpath($GLOBALS['argv'][0]) === __FILE__) {
    MosaicBrowser::main();
}
