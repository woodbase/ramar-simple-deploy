<?php

class FtpService
{
    private $connection;
    private string $host;
    private int $port;
    private string $user;
    private string $pass;
    private string $basePath;
    private int $timeout;

    public function __construct(array $config)
    {
        $this->host     = $config['host'];
        $this->port     = $config['port'] ?? 21;
        $this->user     = $config['user'];
        $this->pass     = $config['pass'];
        $this->basePath = rtrim($config['path'], '/') . '/';
        $this->timeout  = $config['timeout'] ?? 30;
    }

    public function connect(): void
    {
        $this->connection = ftp_ssl_connect(
            $this->host,
            $this->port,
            $this->timeout
        );

        if (!$this->connection) {
            throw new Exception('Could not connect to FTP server');
        }

        if (!ftp_login($this->connection, $this->user, $this->pass)) {
            throw new Exception('FTP login failed');
        }

        ftp_pasv($this->connection, true);
    }

    public function upload(string $localFile, string $remoteFile): void
    {
        $remotePath = $this->basePath . ltrim($remoteFile, '/');

        if (!ftp_put($this->connection, $remotePath, $localFile, FTP_BINARY)) {
            throw new Exception("Failed uploading {$remoteFile}");
        }
    }

    public function close(): void
    {
        if ($this->connection) {
            ftp_close($this->connection);
        }
    }
}