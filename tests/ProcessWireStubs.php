<?php

/**
 * Minimal ProcessWire stubs for headless ClearView testing.
 *
 * Provides just enough of the ProcessWire API surface so that
 * ClearView core classes (Exception, Mosaic, Shard, Facet,
 * Element, Pane) can be loaded and instantiated without a
 * running ProcessWire server.
 */

namespace ProcessWire;

// ---------------------------------------------------------------------------
// Base classes
// ---------------------------------------------------------------------------

class WireException extends \Exception {}

class Wire {
    /** @var array<string, mixed> */
    protected array $data = [];

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }
}

class WireInputData extends Wire
{
    public function requestMethod(): string
    {
        return $this->data['requestMethod'] ?? 'GET';
    }
}

class Sanitizer extends Wire {}

// ---------------------------------------------------------------------------
// CSRF (needed by Session::getPaneKey)
// ---------------------------------------------------------------------------

class SessionCSRF
{
    /** @var array<string, string> */
    public array $tokens = [];

    public function hasToken(string $name): bool
    {
        return isset($this->tokens[$name]);
    }

    public function createToken(string $name): void
    {
        // Generate a deterministic token for testing
        if (!isset($this->tokens[$name])) {
            $this->tokens[$name] = 'csrf_' . $name . '_' . bin2hex(random_bytes(8));
        }
    }

    public function getTokenValue(string $name): string
    {
        return $this->tokens[$name] ?? '';
    }
}

class Session
{
    public SessionCSRF $CSRF;

    public function __construct()
    {
        $this->CSRF = new SessionCSRF();
    }

    public function get(string $key): mixed
    {
        return null;
    }
}

// ---------------------------------------------------------------------------
// Page / Pages
// ---------------------------------------------------------------------------

class Page extends Wire
{
    public int $id = 0;
    public ?Page $parent = null;

    /** @var array<string, mixed> */
    public array $fields = [];

    public function __construct(array $fields = [])
    {
        $this->fields = $fields;
        $this->id = $fields['id'] ?? 0;
    }

    public function get(string $key): mixed
    {
        return $this->fields[$key] ?? null;
    }
}

class PageArray extends Wire implements \IteratorAggregate
{
    /** @var Page[] */
    private array $pages = [];

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->pages);
    }

    public function get(string $selector): ?Page
    {
        foreach ($this->pages as $page) {
            // Simple selector: "name=foo" or "id=123"
            if (preg_match('/^(\w+)=(.+)$/', $selector, $m)) {
                $field = $m[1];
                $value = $m[2];
                if ((string)($page->fields[$field] ?? '') === $value) {
                    return $page;
                }
            }
        }
        return null;
    }
}

// ---------------------------------------------------------------------------
// Modules
// ---------------------------------------------------------------------------

class Modules extends Wire
{
    public function get(string $name): mixed
    {
        return null;
    }

    public function isInstalled(string $name): bool
    {
        return false;
    }
}

// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------

class Config extends Wire
{
    public bool $debug = false;
}

// ---------------------------------------------------------------------------
// Global factory functions
// ---------------------------------------------------------------------------

function wire(string $name = ''): Wire
{
    static $instances = [];
    return $instances[$name] ??= match ($name) {
        'input'   => new WireInputData(),
        'session' => new Session(),
        'config'  => new Config(),
        'modules' => new Modules(),
        'pages'   => new PageArray(),
        default   => new Wire(),
    };
}

function input(): WireInputData
{
    /** @var WireInputData */
    return wire('input');
}

function session(): Session
{
    /** @var Session */
    return wire('session');
}

function config(): Config
{
    /** @var Config */
    return wire('config');
}

function modules(): Modules
{
    /** @var Modules */
    return wire('modules');
}

function pages(): PageArray
{
    /** @var PageArray */
    return wire('pages');
}

function page(): ?Page
{
    return null;
}

function user(): Wire
{
    static $user;
    return $user ??= new Wire();
}
