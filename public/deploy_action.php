<?php

//require __DIR__ . '/../bootstrap.php';
require __DIR__ . '/../src/github.php';
require __DIR__ . '/../src/logger.php';
require __DIR__ . '/../src/deploy.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /');
    exit;
}

$action = $_POST['action'] ?? null;
$branch = $_POST['branch'] ?? null;

if (!$action || !$branch) {
    header('Location: /');
    exit;
}

if ($action === 'set_default') {

    $settingsFile = __DIR__ . '/../settings.json';

    $settings = file_exists($settingsFile)
        ? json_decode(file_get_contents($settingsFile), true)
        : [];

    $settings['default_branch'] = $branch;

    file_put_contents(
        $settingsFile,
        json_encode($settings, JSON_PRETTY_PRINT)
    );

    header('Location: /');
    exit;
}

if ($action === 'deploy') {

    $lockFile = __DIR__ . '/../deploy.lock';

    if (file_exists($lockFile)) {
        logMessage("Deploy already running.");
        header('Location: /');
        exit;
    }

    file_put_contents($lockFile, time());

    try {

        logMessage("Deploy started");
        logMessage("Selected branch: " . $branch);

        $config = require __DIR__ . '/../bootstrap.php';

        $commit = getLatestCommit($config, $branch);

        if (!$commit || empty($commit['sha'])) {
            logMessage("Could not retrieve commit.");
            throw new Exception("Commit fetch failed");
        }

        logMessage("Deploying commit: " . $commit['sha']);

        $zip = downloadBranchZip($config, $branch);

        if (!$zip) {
            logMessage("Download failed.");
            throw new Exception("Zip download failed");
        }

        $rootFolder = extractZip($zip);

        if (!$rootFolder) {
            logMessage("Extraction failed.");
            throw new Exception("Extraction failed");
        }

        $targetPath = __DIR__ . '/../live';

        if (!deployLocally($rootFolder, $targetPath)) {
            logMessage("Deploy failed.");
            throw new Exception("Deploy failed");
        }

        logMessage("Deploy SUCCESS.");

    } catch (Exception $e) {

        logMessage("Deploy error: " . $e->getMessage());

    } finally {

        if (file_exists($lockFile)) {
            unlink($lockFile);
        }
    }

    header('Location: /');
    exit;
}