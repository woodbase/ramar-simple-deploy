<?php

require_once __DIR__ . '/Core/Autoloader.php';

use Core\Autoloader;

Autoloader::register();

Autoloader::addNamespace('Core', __DIR__ . '/Core');
Autoloader::addNamespace('Src', __DIR__ . '/src');

$config = require __DIR__ . '/config/config.php';

return $config;