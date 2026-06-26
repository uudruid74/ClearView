<?php

namespace ClearView;

/**
 * Mosaic Browser — CLI REPL for exploring panes without a web browser.
 *
 * Two modes:
 *   dummy (default) — test views + null crystals, zero dependencies
 *   real            — vendor/site crystals, requires ProcessWire
 *
 * Usage:
 *   php bin/mosaic-browser --view=test-login
 *   php bin/mosaic-browser --pane=loginform --inlay=login --method=open
 *   php bin/mosaic-browser --view=test-login --dump
 *   php bin/mosaic-browser --mode=real --url=/loginform/login/
 *
 * @see \\ClearView\\TestJig
 */
class MosaicBrowser extends TestJig
{
    private bool $dump = false;
    private string $mode = 'dummy';
    private string $inlayname = 'Default';
    private string $methodname = 'open';
    private ?string $view = null;
    private array $capturedEvents = [];

    public function __construct(array $cliArgs = [])
    {
        $this->dump = !empty($cliArgs['dump']);
        $this->view = $cliArgs['view'] ?? null;
        $this->mode = $cliArgs['mode'] ?? 'dummy';
        if (!in_array($this->mode, ['dummy', 'real'], true)) {
            $this->mode = 'dummy';
        }
        $this->resolveUrl($cliArgs);
        parent::__construct($cliArgs);
    }

    private function resolveUrl(array $args): void
    {
        if (!empty($args['url'])) {
            $url = trim($args['url'], '/');
            $segments = explode('/', $url);
            $this->panename = $segments[0] ?: 'Default';
            $this->inlayname = $segments[1] ?: 'Default';
            $this->methodname = $segments[2] ?: 'open';
        } else {
            $this->panename = $args['pane'] ?? $args['panename'] ?? $this->panename;
            $this->inlayname = $args['inlay'] ?? $args['inlayname'] ?? $this->inlayname;
            $this->methodname = $args['method'] ?? $this->methodname;
        }
    }

    public function getModuleList(): array
    {
        $modules = parent::getModuleList();
        // Dummy mode: testjig is already prepended by TestJig
        // Real mode: strip testjig, use only vendor/site modules
        if ($this->mode === 'real') {
            $modules = array_values(array_filter($modules, fn($m) => $m !== 'testjig'));
        }
        return array_values(array_unique($modules));
    }

    public function bootstrap(): void
    {
        $this->setInput($this->panename, $this->inlayname, $this->methodname);
        // Real mode without view: bootstrap via ProcessWire URL
        if ($this->mode === 'real' && !$this->view) {
            $this->loadPaneReal();
        } else {
            $this->loadPane();
        }
    }

    /**
     * Bootstrap in real mode — loads the pane via ProcessWire page.
     * Requires PW to be installed and configured.
     */
    private function loadPaneReal(): void
    {
        $this->capturedEvents = [];
        try {
            $segments = [$this->panename, $this->inlayname, $this->methodname];
            $url = '/' . implode('/', array_filter($segments, fn($s) => $s !== 'Default')) . '/';

            ob_start();
            // Try to load via ProcessWire page lookup
            $body = '';
            try {
                $page = \ProcessWire\pages()->get($url);
                if ($page && $page->id) {
                    $body = $page->get('body') ?? '';
                }
            } catch (\Throwable $e) {
                // PW not available — body stays empty, fall through to html()
            }

            if ($body) {
                $data = jsonmangler::fromhtml($body, $this->panename);
                Shard::loadShard($data, inlay: $this->inlayname);
            } else {
                // No PW page body — use Framework::html() for direct rendering
                $this->html();
            }
            $output = ob_get_clean();
            if ($this->dump && $output) {
                echo "\n--- Response ---\n{$output}\n--- End Response ---\n";
            }
            ClearView::dumpOOBdata();
            $this->processEvents();
        } catch (\Throwable $e) {
            if (ob_get_level()) ob_end_clean();
            echo "Error (real mode): " . $e->getMessage() . "\n";
            echo "Falling back to dummy mode. Use --mode=dummy for offline use.\n";
            $this->mode = 'dummy';
        }
    }

    public function onSendHtmxHeader(string $header, $event, $params): void
    {
        $eventName = is_array($params) ? $event : (string)$params;
        $eventData = is_array($params) ? $params : ['event' => $eventName];
        $this->capturedEvents[$eventName] = $eventData;
    }

    private function setInput(string $pane, string $inlay, string $method): void
    {
        TestJig::setJig('panename', $pane);
        TestJig::setJig('inlayname', $inlay);
        TestJig::setJig('methodname', $method);
    }

    private function loadPane(): void
    {
        $this->capturedEvents = [];
        try {
            if ($this->view) {
                ob_start();
                $this->renderTestView($this->view, $this->panename);
                $html = ob_get_clean();
                $data = jsonmangler::fromhtml($html, $this->view);
                Shard::loadShard($data, inlay: $this->inlayname);
                if ($this->dump) {
                    echo "\n--- Response ---\n{$html}\n--- End Response ---\n";
                }
            } else {
                // Fresh Mosaic for each open — simulates new HTTP request
                Mosaic::reset();
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
            if (($name === 'inlaychange' || $name === 'loginchange') && isset($data['inlay'])) {
                echo "  event: {$name} → {$data['inlay']}\n";
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

        echo "\n" . str_repeat("═", 80) . "\n";
        printf("  %-38s mode=%s  %s\n", '/' . $this->panename . '/' . $this->inlayname . '/' . $this->methodname . '/',
            $this->mode, 'q=quit h=help d=dump [' . ($this->dump ? 'ON' : 'OFF') . ']');
        echo str_repeat("─", 80) . "\n";
        printf("  %-3s %-8s %-20s %-8s %s\n", '#', 'Glyph', 'Name', 'Inlay', 'Value/Action');

        $i = 1;
        foreach ($items as $item) {
            $value = $item['value'] ?? '';
            if (strlen($value) > 25) {
                $value = substr($value, 0, 22) . '...';
            }
            $action = '';
            if ($item['action']) {
                $method = $item['method'] ?? 'POST';
                $action = " [{$method}]";
            }
            printf("  %-3d %-8s %-20s %-8s %s%s\n",
                $i, $item['glyph'], $item['name'], $item['inlay'] ?? '-', $value, $action);
            $i++;
        }
        echo str_repeat("═", 80) . "\n";

        if (!empty($this->capturedEvents)) {
            echo "  events: " . implode(', ', array_keys($this->capturedEvents)) . "\n";
        }
    }

    public function getInteractables(): array
    {
        $items = [];
        $allShards = Mosaic::getAllShards();

        foreach ($allShards as $inlayName => $inlayShards) {
            foreach ($inlayShards as $shard) {
                if (!$shard instanceof Shard) continue;
                if ($shard->isAnonymous()) continue;

                $name = $shard->getField('name') ?? $shard->getField('id');
                if (empty($name)) continue;

                $glyph = $shard->getField('glyph') ?? 'Shard';
                $value = (string)$shard;
                $shardInlay = $shard->getField('inlay') ?? $shard->inlay();

                $postUrl = $shard->getField('hx-post');
                $getUrl = $shard->getField('hx-get');
                $url = $postUrl ?? $getUrl ?? $shard->getField('href') ?? null;
                $method = $postUrl ? 'POST' : ($getUrl ? 'GET' : null);

                $items[] = [
                    'name'   => $name,
                    'glyph'  => $glyph,
                    'value'  => $value,
                    'url'    => $url,
                    'action' => $url !== null,
                    'method' => $method,
                    'inlay'  => $inlayName,
                    'shard'  => $shard,
                ];
            }
        }
        return $items;
    }

    public function set(string $name, string $value): void
    {
        Mosaic::setVar($name, $value, $this->inlayname);
        echo "OK: {$name} = {$value}\n";
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
            $this->panename = $segments[0] ?: $this->panename;
            $this->inlayname = $segments[1] ?: $this->inlayname;
            $this->methodname = $segments[2] ?: 'submit';

            $this->setInput($this->panename, $this->inlayname, $this->methodname);
            echo "→ {$this->panename}/{$this->inlayname}/{$this->methodname}/\n";

            try {
                // Fresh Mosaic for manual invocation — simulates new HTTP request
                Mosaic::reset();
                $className = Inlay::load($this->panename, $this->inlayname);
                ob_start();
                $inlay = new $className();
                $inlay->{$this->methodname}();
                $output = ob_get_clean();
                // Sync from Input crystal after execution — URL ↔ pane/inlay
                $currentInlay = Mosaic::getVar('Input::inlayname');
                if ($currentInlay) {
                    $this->inlayname = (string)$currentInlay;
                    $this->panename = (string)(Mosaic::getVar('Input::panename') ?: $this->panename);
                }
                $this->processEvents();
                if ($this->dump && $output) {
                    echo "--- Response ---\n{$output}\n--- End Response ---\n";
                }
            } catch (\Throwable $e) {
                if (ob_get_level()) ob_end_clean();
                echo "  error: " . $e->getMessage() . "\n";
            }
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
                    echo "  dump / d               toggle response dump\n";
                    echo "  mode [real|dummy]      show or switch mode\n";
                    echo "  ds <#>                 dump a single shard (all fields)\n";
                    echo "  de                     dump everything (recursive tree)\n";
                    echo "  quit / exit / q        exit\n";
                    echo "  <number>               interact with item #N\n";
                    break;
                case 'show': case 'refresh': break;
                case 'd':
                    if (($parts[1] ?? '') === 'on') { $this->dump = true; }
                    elseif (($parts[1] ?? '') === 'off') { $this->dump = false; }
                    else { $this->dump = !$this->dump; }
                    echo "Dump: " . ($this->dump ? 'ON' : 'OFF') . "\n";
                    break;

                case 'mode':
                    if (isset($parts[1]) && in_array($parts[1], ['real', 'dummy'], true)) {
                        $this->mode = $parts[1];
                        echo "Mode: {$this->mode}\n";
                        echo "(reloading with new module configuration...)\n";
                        $this->bootstrap();
                    } else {
                        echo "Mode: {$this->mode} (use 'mode real' or 'mode dummy')\n";
                    }
                    break;

                case 'ds':
                    if (is_numeric($parts[1] ?? '')) {
                        $items = $this->getInteractables();
                        $idx = (int)$parts[1] - 1;
                        if (isset($items[$idx])) {
                            echo json_encode($items[$idx]['shard'], JSON_PRETTY_PRINT) . "\n";
                        } else {
                            echo "Invalid number.\n";
                        }
                    } else {
                        echo "Usage: ds <#>\n";
                    }
                    break;

                case 'de':
                    Mosaic::dumpEverything();
                    break;

                case 'set':
                    $rest = ($parts[1] ?? '') . ' ' . ($parts[2] ?? '');
                    if (preg_match('/^(\S+)\s*=\s*(.+)$/', trim($rest), $m)) {
                        $this->set($m[1], trim($m[2]));
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
                                $name = $items[$idx]['name'];
                                $val = $items[$idx]['value'];
                                echo "  {$items[$idx]['glyph']} {$name} = {$val}\n  new value: ";
                                $newVal = trim(fgets(STDIN));
                                if ($newVal !== '') {
                                    $this->set($name, $newVal);
                                }
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
