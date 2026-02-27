<?php

namespace NEBF\Core;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Simple PSR-4 style autoloader.
 */
class Autoloader {

    /**
     * Register autoloader.
     */
    public static function register() {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    /**
     * Autoload class files.
     *
     * @param string $class Fully qualified class name.
     */
    private static function autoload($class) {

        if (strpos($class, 'NEBF\\') !== 0) {
            return;
        }

        $base_dir = NEBF_MVC_PATH . 'includes/';
        $relative_class = str_replace('NEBF\\', '', $class);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        if (file_exists($file)) {
            require_once $file;
        }
    }
}

Autoloader::register();
