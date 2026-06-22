<?php

namespace ClearView;

use ClearView\Page;
use ClearView\Exception;
use ClearView\Mosaic;
use ProcessWire;
use ReflectionClass;

/**
 * Abstract base class for Crystals, managing ProcessWire objects.
 *
 * Crystals wrap ProcessWire objects (e.g., pages, users, sessions) to integrate them into ClearView’s data model.
 * They provide a unified interface for getting and setting variables or fields, forwarding method calls to the
 * underlying ProcessWire object, and registering instances as Shards in Mosaic. Subclasses specialize in specific
 * ProcessWire contexts, such as input, sessions, or pages.
 *
 * @see \ClearView\Shard
 * @see \ClearView\Mosaic
 * @see \ClearView\Page
 */
abstract class Crystal extends Page implements ArrayAccess
{
    protected $mosaic;
    /**
     * Initializes the Crystal with a ProcessWire object.
     *
     * Called during instantiation to set the ProcessWire object that the Crystal wraps. Subclasses may override
     * to provide default objects if none is provided.
     *
     * Why: Establishes the link between ClearView’s data model and ProcessWire’s API.
     *
     * @param mixed $pwObject The ProcessWire object to wrap.
     */
    public function __construct($pwObject=null,$name=null,$inlay='ClearView',$mos=null)
    {
        $this->mosaic = $mos;
        parent::__construct($pwObject,$name,$inlay,$mos);
    }

    // ── ArrayAccess ──────────────────────────────────────────────

    public function offsetGet($key): mixed
    {
        return Mosaic::getVar($key);
    }

    public function offsetSet($key, $value): void
    {
        Mosaic::setVar($key, $value);
    }

    public function offsetExists($key): bool
    {
        return Mosaic::getVar($key) !== null;
    }

    public function offsetUnset($key): void
    {
        Mosaic::delVar($key);
    }

    /**
     * Initializes all Crystal subclasses and registers them in Mosaic.
     *
     * Called during system startup to instantiate all concrete Crystal subclasses
     * and register them as Shards in Mosaic. Also instantiates the ClearView
     * crystal explicitly.
     *
     * @param Mosaic $mosaic The Mosaic to register crystals in
     * @param string|null $overridePath Load from crystals/<path>/ instead of crystals/
     * @return Mosaic The Mosaic instance (also assigned to Pane)
     */
    public static function loadAll($mosaic, ?string $overridePath = null): Mosaic
    {
        $crystalDir = __DIR__ . '/crystals';
        $loadDir = $overridePath ? "{$crystalDir}/{$overridePath}" : $crystalDir;

        // Load all Crystal subclass files from the crystals directory
        if (is_dir($loadDir)) {
            foreach (glob("{$loadDir}/*.php") as $file) {
                require_once $file;
            }
        }

        // Short-name overrides: crystals whose class name differs from
        // the Mosaic inlay name they should be registered under.
        $nameOverrides = [
            'ClearView\\PaneCrystal' => 'Pane',
            'ClearView\\StaticCrystal' => 'Static',
        ];

        $classes = get_declared_classes();
        foreach ($classes as $class) {
            if (is_subclass_of($class, self::class) && (new ReflectionClass($class))->isInstantiable()) {
                $shortName = $nameOverrides[$class]
                          ?? (($pos = strrpos($class, '\\')) !== false ? substr($class, $pos + 1) : $class);
                new $class(null, $shortName, 'ClearView', $mosaic);
            }
        }
        new Page(\ProcessWire\page(), 'Page', 'ClearView', $mosaic);
        return $mosaic;
    }

}

