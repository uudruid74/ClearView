<?php

namespace ClearView;

/**
 * Null ClearView crystal — returns null and empty buffers.
 * Maintains the static $instance for accessor compatibility.
 */
class ClearView extends Crystal
{
    private static ?ClearView $instance = null;

    public function __construct($pwObject = null, $name = null, $inlay = 'ClearView', $mos = null)
    {
        $this->mosaic = $mos;
        $this->data = [];
        self::$instance ??= $this;
        $this->scripts = [];
        $this->async = '';
        $this->oobBuffer = '';
        $this->debugBuffer = '';
    }

    public function getVar($key = null)
    {
        return $varname ?? $key ?? null;
    }

    public static function panename($value = null): string { return 'Default'; }
    public static function inlayname($value = null): string { return 'Pane'; }
    public static function method($value = null): string { return ''; }
    public static function Mosaic(): ?Mosaic { return Mosaic::instance(); }

    public static function javascript(string $string): void {}
    public static function asyncjs(string $string): void {}
    public static function sendOOB($elem): void {}
    public function dumpOOBdata(): void {}

    /** Resolve Crystal::User() → Mosaic::index('ClearView', 'User') */
    public static function __callStatic(string $name, array $args): mixed
    {
        return Mosaic::index('ClearView', $name);
    }
}
