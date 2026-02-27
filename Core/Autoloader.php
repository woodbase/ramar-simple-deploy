<?php

namespace Core;

class Autoloader
{
    protected static array $prefixes = [];

    public static function register(): void
    {
        spl_autoload_register([self::class, 'load']);
    }

    public static function addNamespace(string $prefix, string $baseDir): void
    {
        $prefix = trim($prefix, '\\') . '\\';
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        self::$prefixes[$prefix][] = $baseDir;
    }

    protected static function load(string $class): void
    {
        foreach (self::$prefixes as $prefix => $baseDirs) {

            if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
                continue;
            }

            $relativeClass = substr($class, strlen($prefix));
            $relativeClass = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

            foreach ($baseDirs as $baseDir) {

                $file = $baseDir . $relativeClass;

                if (file_exists($file)) {
                    require $file;
                    return;
                }
            }
        }
    }
}