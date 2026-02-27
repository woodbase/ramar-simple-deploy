<?php

$config = require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../src/github.php';

if (!isset($_GET['branch'])) {
    exit;
}

$commit = getLatestCommit($config, $_GET['branch']);

if (!$commit) {
    exit;
}

echo json_encode([
    'sha' => substr($commit['sha'], 0, 7),
    'author' => $commit['commit']['author']['name'],
    'date' => date("Y-m-d H:i", strtotime($commit['commit']['author']['date'])),
    'message' => $commit['commit']['message']
]);