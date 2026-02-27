<?php

function logMessage($message)
{
    $line = "[" . date("Y-m-d H:i:s") . "] " . $message . PHP_EOL;
    file_put_contents(__DIR__ . "/logs/deploy.log", $line, FILE_APPEND);
}