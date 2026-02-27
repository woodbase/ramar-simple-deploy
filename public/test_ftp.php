<?php
// test-ftp.php

require __DIR__ . '/../src/deploy.php';

$config = require __DIR__ . '/../bootstrap.php';

try {

    $ftp = ftp_connect($config['ftp_host']);

    if (!$ftp) {
        throw new Exception("Could not connect to FTP host");
    }

    if (!ftp_login($ftp, $config['ftp_user'], $config['ftp_pass'])) {
        throw new Exception("FTP login failed");
    }

    ftp_pasv($ftp, true);

    echo "FTP connection successful!";

    ftp_close($ftp);

} catch (Exception $e) {
    echo $e->getMessage();
}