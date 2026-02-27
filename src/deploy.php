<?php

function downloadBranchZip($config, $branch)
{
    logMessage("Downloading branch ZIP...");

    $url = "https://api.github.com/repos/{$config['github']['repo']}/zipball/{$branch}";
    $zipPath = __DIR__ . "/tmp/deploy.zip";

    if (file_exists($zipPath)) {
        unlink($zipPath);
    }

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true, // 🔥 viktigt
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$config['github']['token']}",
            "User-Agent: Deploy-App"
        ]
    ]);

    $data = curl_exec($ch);

    if (curl_errno($ch)) {
        logMessage("cURL error: " . curl_error($ch));
        curl_close($ch);
        return false;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        logMessage("GitHub returned HTTP code: " . $httpCode);
        return false;
    }

    file_put_contents($zipPath, $data);

    if (filesize($zipPath) === 0) {
        logMessage("Downloaded ZIP is empty.");
        return false;
    }

    logMessage("ZIP downloaded successfully. Size: " . filesize($zipPath) . " bytes");

    return $zipPath;
}

function extractZip($zipPath)
{
    logMessage("Extracting ZIP...");

    $extractPath = __DIR__ . "/tmp/extracted";

    // Rensa gammal extraction
    if (is_dir($extractPath)) {
        deleteDirectory($extractPath);
    }

    mkdir($extractPath, 0777, true);

    $zip = new ZipArchive;

    if ($zip->open($zipPath) === TRUE) {
        $zip->extractTo($extractPath);
        $zip->close();
        logMessage("ZIP extracted successfully.");
    } else {
        logMessage("Failed to extract ZIP.");
        return false;
    }

    // GitHub skapar en hash-mapp, hitta den
    $dirs = glob($extractPath . "/*", GLOB_ONLYDIR);

    if (count($dirs) === 1) {
        logMessage("Root folder detected: " . basename($dirs[0]));
        return $dirs[0];
    }

    logMessage("Could not detect root folder.");
    return false;
}

function deleteDirectory($dir)
{
    if (!is_dir($dir)) return;

    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..') {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
    }
    rmdir($dir);
}

function deployToFtp($config, $localPath)
{
    logMessage("Connecting to FTP...");

    $ftp = ftp_connect($config['ftp_host']);

    if (!$ftp) {
        logMessage("Could not connect to FTP host.");
        return false;
    }

    if (!ftp_login($ftp, $config['ftp_user'], $config['ftp_pass'])) {
        logMessage("FTP login failed.");
        return false;
    }

    ftp_pasv($ftp, true);

    logMessage("FTP connected successfully.");

    uploadDirectory($ftp, $localPath, $config['ftp_remote_path']);

    ftp_close($ftp);

    logMessage("Deploy completed successfully.");

    return true;
}

function uploadDirectory($ftp, $localDir, $remoteDir)
{
    $files = scandir($localDir);

    foreach ($files as $file) {

        if ($file === '.' || $file === '..') continue;

        $localPath = $localDir . '/' . $file;
        $remotePath = $remoteDir . '/' . $file;

        if (is_dir($localPath)) {

            @ftp_mkdir($ftp, $remotePath);
            uploadDirectory($ftp, $localPath, $remotePath);

        } else {

            logMessage("Uploading: " . $remotePath);

            if (!ftp_put($ftp, $remotePath, $localPath, FTP_BINARY)) {
                logMessage("Failed uploading: " . $remotePath);
            }
        }
    }
}

function deployLocally($sourcePath, $targetPath)
{
    logMessage("Syncing files...");

    syncDirectories($sourcePath, $targetPath);

    logMessage("Local deploy complete.");
    return true;
}

function copyDirectory($src, $dst)
{
    $dir = opendir($src);
    @mkdir($dst);

    while (($file = readdir($dir)) !== false) {

        if ($file != '.' && $file != '..') {

            if (is_dir($src . '/' . $file)) {

                copyDirectory($src . '/' . $file, $dst . '/' . $file);

            } else {

                copy($src . '/' . $file, $dst . '/' . $file);
            }
        }
    }

    closedir($dir);
}

function syncDirectories($source, $target)
{
    // Skapa target om det inte finns
    if (!is_dir($target)) {
        mkdir($target, 0777, true);
    }

    $sourceFiles = scandir($source);
    $targetFiles = is_dir($target) ? scandir($target) : [];

    // 🔹 1. Ta bort filer som inte finns i source
    foreach ($targetFiles as $file) {

        if ($file === '.' || $file === '..') continue;

        if (!in_array($file, $sourceFiles)) {

            $targetPath = $target . '/' . $file;

            if (is_dir($targetPath)) {
                deleteDirectory($targetPath);
                logMessage("Deleted directory: $targetPath");
            } else {
                unlink($targetPath);
                logMessage("Deleted file: $targetPath");
            }
        }
    }

    // 🔹 2. Kopiera / uppdatera filer från source
    foreach ($sourceFiles as $file) {

        if ($file === '.' || $file === '..') continue;

        $sourcePath = $source . '/' . $file;
        $targetPath = $target . '/' . $file;

        if (is_dir($sourcePath)) {

            syncDirectories($sourcePath, $targetPath);

        } else {

            // Kopiera om fil saknas eller ändrats
            if (!file_exists($targetPath) || md5_file($sourcePath) !== md5_file($targetPath)) {
                copy($sourcePath, $targetPath);
                logMessage("Updated file: $targetPath");
            }
        }
    }
}