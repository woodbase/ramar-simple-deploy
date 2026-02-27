<?php

$logFile = __DIR__ . '/../src/logs/deploy.log';

if (file_exists($logFile)) {
    echo nl2br(htmlspecialchars(file_get_contents($logFile)));
}