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
abstract class Crystal extends Page
{
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
    public function __construct($pwObject=null,$name=null,$inlay='ClearView')
    {
        parent::__construct($pwObject,$name,$inlay);
    }

    /**
     * Initializes all Crystal subclasses and registers them in Mosaic.
     *
     * Called during system startup to instantiate all concrete Crystal subclasses and register them as Shards in
     * Mosaic under the 'ClearView' inlay.
     *
     * Why: Sets up ProcessWire objects as Shards for access via Mosaic::getVar() or setVar().
     *
     * @return void
     */
    public static function plugAllCrystals(): void
    {
        // Load all Crystal subclass files from the Crystals/ directory
        foreach (glob(__DIR__ . '/crystals/*.php') as $file) {
            require_once $file;
        }

        // Short-name overrides: crystals whose class name differs from
        // the Mosaic inlay name they should be registered under.
        $nameOverrides = [
            'ClearView\\PaneCrystal' => 'Pane',
        ];

        $classes = get_declared_classes();
        foreach ($classes as $class) {
            if (is_subclass_of($class, self::class) && (new ReflectionClass($class))->isInstantiable()) {
                $shortName = $nameOverrides[$class] 
                          ?? (($pos = strrpos($class, '\\')) !== false ? substr($class, $pos + 1) : $class);
                new $class(null,$shortName,'ClearView');
            }
        }
        new Page(\ProcessWire\page(),'Page','ClearView');
    }

}

