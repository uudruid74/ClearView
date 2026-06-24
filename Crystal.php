<?php

namespace ClearView;

use ClearView\Page;
use ClearView\Exception;
use ClearView\Mosaic;
use ProcessWire;
use ReflectionClass;

/**
 * Abstract base class for Crystals, managing ProcessWire objects.
 * Crystals wrap ProcessWire objects (e.g., pages, users, sessions) to integrate them into ClearView’s data model.
 * They provide a unified interface for getting and setting variables or fields, forwarding method calls to the
 * underlying ProcessWire object, and registering instances as Shards in Mosaic. Subclasses specialize in specific
 * ProcessWire contexts, such as input, sessions, or pages.
 * @see \ClearView\Shard
 * @see \ClearView\Mosaic
 * @see \ClearView\Page
 */
abstract class Crystal extends Page implements ArrayAccess
{
    protected $mosaic;
    /**
     * Initializes the Crystal with a ProcessWire object.
     * Called during instantiation to set the ProcessWire object that the Crystal wraps. Subclasses may override
     * to provide default objects if none is provided.
     * @param mixed $pwObject The ProcessWire object to wrap.
     * @param mixed $name Description.
     * @param mixed $inlay Description.
     * @param mixed $mos Description.
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
     * Iterates the Framework module stack and loads crystal files from
     * modules/<module>/crystals/.  First module to define a crystal wins;
     * lower-priority modules are skipped for already-loaded crystals.
     * Called during system startup.  Requires Framework::instance() to be
     * set before Mosaic::load() so the module list is available.
     * @param Mosaic $mosaic The Mosaic to register crystals in
     * @return Mosaic The Mosaic instance
     */
    public static function loadAll($mosaic): Mosaic
    {
        $modules = Framework::Modules();
        $loaded = [];

        foreach ($modules as $module) {
            $crystalDir = __DIR__ . "/modules/{$module}/crystals";
            if (!is_dir($crystalDir)) continue;

            foreach (glob("{$crystalDir}/*.php") as $file) {
                $basename = basename($file, '.php');
                if (isset($loaded[$basename])) continue; // higher-priority module already loaded
                $loaded[$basename] = true;
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

        // Load per-module _init.php config after crystals are instantiated
        foreach ($modules as $module) {
            $initFile = __DIR__ . "/modules/{$module}/_init.php";
            if (file_exists($initFile)) {
                $vars = require $initFile;
                if (is_array($vars)) {
                    foreach ($vars as $key => $value) {
                        Mosaic::setVar($key, $value, 'Config');
                    }
                }
            }
        }

        return $mosaic;
    }

}

