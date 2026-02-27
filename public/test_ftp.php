<?php
// test-ftp.php

<?php

require '../config/config.php';
require '../src/deploy.php';

$config = require '../config/config.php';

$ftpConfig = $config['ftp'];

if ($ftpConfig['ssl']) {
    $ftp = ftp_ssl_connect($ftpConfig['host'], $ftpConfig['port']);
} else {
    $ftp = ftp_connect($ftpConfig['host'], $ftpConfig['port']);
}

if (!$ftp) {
    die("Could not connect");
}

if (!ftp_login($ftp, $ftpConfig['user'], $ftpConfig['pass'])) {
    die("Login failed");
}

ftp_pasv($ftp, true);

echo "FTP connection successful!";

ftp_close($ftp);

} catch (Exception $e) {
    echo $e->getMessage();
}