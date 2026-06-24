<?php

namespace ClearView;
use ClearView\Shard;
use ClearView\Config;
use ClearView\Mosaic;
use ProcessWire;

/**
 * Class for managing page data in ProcessWire.
 * Wraps ProcessWire’s page object to provide access to page properties and fields. Supports change tracking
 * and automatic saving when modified.
 */
class Page extends Shard
{
    /**
     * Initializes the Page with a ProcessWire page object.
     * Called during system initialization or when accessing page data. Uses ProcessWire’s `page()` function
     * if no object is provided.
     * @param mixed $pwPage The ProcessWire page object (defaults to Page via `page()`).
     */
    public function __construct($pwObject=null,$name=null,$inlay=Config::SHARD_ANONINLAY)
    {
        $init = [       // Default inlay is ANONINLAY to not record these Shards
            Config::PAGE_PWOBJECT => $pwObject,
            '__pF'  => Config::PAGE_PWOBJECT,
            'id'    => $name,
            'name'  => $name,
            'inlay' => $inlay,
        ];
        if ($pwObject instanceof \ProcessWire\PageArray) {
            $init['__pF'] = "children";
            $init['children'] = $pwObject;
            $childType = Shard::PageArray;
        } else {
            $childType = Shard::ChildArray;
        }
        if (is_String($pwObject)) {
            $init[Config::PAGE_PWPANE] = \ProcessWire\page();
            $pwObject = \ProcessWire\pages()->findOne($pwObject);
        }
        if ($pwObject && $pwObject->id) {
            $init['name'] = 'page-' . $pwObject->id;
        }
        parent::__construct($init);
        $this->setChildType($childType);
    }

    /**
     * The "from" call creates a new anonymous object from the Crystal instance
     * @param string $string A string to find the correct object to return
     * @return mixed the new wrapped object
     */
    public static function from(string $search)
    {
        $pwPage = \ProcessWire\pages()->findOne($search);
        if (!$pwPage) {
            throw new Exception("Could not find ProcessWire page with search: " . $search);
        }
        Exception::debug("Page->from returning " . gettype($pwPage));
        return new self($pwPage);
    }

    /**
     * Gets a Page variable.
     * @param string $key The key to retrieve,
     * @return mixed The page value, Page Crystal, or null if not found.
     */
    public function getField(string $key)
    {
        $pw = $this->data[Config::PAGE_PWOBJECT];
        Exception::debug("Page getField($key) called on " . gettype($pw));
        return $pw->get($key);
    }

    /**
     * Wrap getVar() to getField(), not Mosaic, but return a wrapped object
     * @param string|null $key to retrieve from Page
     * @return mixed the wrapped page
     */
    public function getVar(string $key)
    {
        if ($key === 'Pane') {
            return $this->data[Config::PAGE_PWPANE];
        }
        if ($key === null || $key === '') {
            return $this->data[Config::PAGE_PWOBJECT];
        }
        $pwObject = $this->data[Config::PAGE_PWOBJECT]->get($key);
        if (isset($pwObject) &&  $pwObject instanceof \ProcessWire\Wire) {
            return new Page($pwObject);
        }
        return $pwObject ?? null;
    }

    /**
     * Used to retrieve multiple properties or fields from the ProcessWire object in a single call.
     * @param string|array $keys The keys to retrieve, possibly with '.' for nested fields.
     * @return array The values, indexed by key.
     */
    public function getVars($keys): array
    {
        return array_map([$this->data[Config::PAGE_PWOBJECT], 'getVar'], (array)$keys);
    }

    /**
     * Sets a variable or field in the ProcessWire object.
     * @param string $key The key to set.
     * @param mixed $value The value to set.
     * @return void
     */
    public function setField(string $key, $value): void
    {
        $this->data[Config::PAGE_PWOBJECT]->set(
            $key,
            ClearView::Mosaic()->index('ClearView', 'Sanitizer')->sanitize($value, Config::SANI_PAGE_SAVE)
        );
    }

    /**
     * Page's and crystals use setVar/getVar as synonyms for getField, not Mosaic calls
     * @param string $key to set
     * @param mixed $value to set
     * @return void
     */
    public function setVar(string $key, $value): void
    {
        $this->data[Config::PAGE_PWOBJECT]->set(
            $key,
            ClearView::Mosaic()->index('ClearView', 'Sanitizer')->sanitize($value, Config::SANI_PAGE_SAVE)
        );
    }

    /**
     * Used to update multiple properties or fields in the ProcessWire object in a single call.
     * @param array $data Key-value pairs to set, with keys possibly using '.' for nested fields.
     * @return void
     */
    public function setVars(array $data, ?string $inlay = null): void
    {
        foreach ($data as $key => $value) {
            $this->setVar($key, $value);
        }
    }
    /**
     * Used to retrieve multiple properties or fields from the ProcessWire object in a single call.
     * @param string|array $keys The keys to retrieve, possibly with '.' for nested fields.
     * @return array The values, indexed by key.
     */
    public function getFields($keys): array
    {
        return array_map([$this->data[Config::PAGE_PWOBJECT], 'getField'], (array)$keys);
    }

    /**
     * Checks if the crystal has changed and saves if necessary.
     * Used to detect modifications to the PW object and persist changes to ProcessWire’s database.
     * @return mixed Self if changes were saved, false otherwise.
     */
    public function hasChanged(): mixed
    {
        if ($this->data[Config::PAGE_PWOBJECT]->isChanged()) {
            $this->data[Config::PAGE_PWOBJECT]->save();
            return $this;
        }
        return null;
    }

    /**
     * Searches children for matching field values.
     * @param string $field Field to search.
     * @param mixed $value Value to match.
     * @param string $operator Comparison operator (e.g., '=', '*=').
     * @param string|null $returnField Optional field to return.
     * @return array Matching values or Shards.
     */
    protected function searchChildren(string $field, $value, string $operator, ?string $returnField = null): array
    {
        $pwObject = $this->data[Config::PAGE_PWOBJECT];
        $selector = $field . $operator . $value;

        $results = [];

        // Use find() on PageArray
        if ($pwObject instanceof \ProcessWire\PageArray) {
            $pwResults = $pwObject->find($selector);
            foreach ($pwResults as $pwPage) {
                $results[] = new Page($pwPage);
            }
        }
        // Use children() on single Page
        elseif ($pwObject instanceof \ProcessWire\Page) {
            $pwResults = $pwObject->children($selector);
            foreach ($pwResults as $pwPage) {
                $results[] = new Page($pwPage);
            }
        }

        return $results;
    }

    public function getChildren(string $query): array
    {
        $pwObject = $this->data[Config::PAGE_PWOBJECT];
        $results = [];

        if ($pwObject instanceof \ProcessWire\PageArray) {
            $pwResults = $pwObject->find($query);
            foreach ($pwResults as $pwPage) {
                $results[] = new Page($pwPage);
            }
        } elseif ($pwObject instanceof \ProcessWire\Page) {
            $pwResults = $pwObject->children($query);
            foreach ($pwResults as $pwPage) {
                $results[] = new Page($pwPage);
            }
        }
        return $results;
    }

    public function addChildren($content): void
    {
        $pwObject = $this->data[Config::PAGE_PWOBJECT];
        if ($pwObject instanceof \ProcessWire\Page) {
            if ($content instanceof Page) {
                $pwChild = $content->data[Config::PAGE_PWOBJECT];
                if ($pwChild instanceof \ProcessWire\Page) {
                    $pwObject->addChild($pwChild);
                    $pwObject->save();
                }
            } elseif ($content instanceof \ProcessWire\Page) {
                $pwObject->addChild($content);
                $pwObject->save();
            }
        }
    }

    public function replaceChildren($content): void
    {
        $pwObject = $this->data[Config::PAGE_PWOBJECT];
        if ($pwObject instanceof \ProcessWire\Page) {
            // Delete all current children
            $pwObject->children()->deleteAll();
            $pwObject->save();
            // Add the new children
            if (is_array($content)) {
                foreach ($content as $child) {
                    $this->addChildren($child);
                }
            } elseif ($content !== null) {
                $this->addChildren($content);
            }
        }
    }

    public function render(): void
    {
        throw new Exception("Pages can't be rendered");
    }

    public function renderChildren(): void
    {
        throw new Exception("Page children don't get rendered");
    }

    public function __call($name, $arguments)
    {
        Exception::debug("Page " . self::class . " __call $name");
        if (method_exists($this->data[Config::PAGE_PWOBJECT], $name)) {
            /* Probably need to see if a page is returned and wrap it in PwPage */
            //return call_user_func_array([$this[Config::PAGE_PWOBJECT], $name], $arguments);
            $trace = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 10);
            $stack = [];
            foreach ($trace as $frame) {
                $stack[] = sprintf(
                    "%s:%d %s%s%s",
                    $frame['file'] ?? 'unknown',
                    $frame['line'] ?? 0,
                    $frame['class'] ?? '',
                    !empty($frame['class']) ? '::' : '',
                    $frame['function']
                );
            }
            Exception::debug("Page __call stack trace for method $name:\n" . implode("\n", $stack));
            throw new Exception("Stack trace logged for method '$name' to diagnose recursion");
        }
        throw new Exception("Object of class: " . self::class . " has no method '$name'");
    }
}
