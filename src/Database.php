<?php

class Database
{
    private $pdo;

    public function __construct(array $config)
    {
        $charset = $config['charset'] ?? 'utf8mb4';
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 3306;
        $name = $config['name'] ?? '';
        $dsn = sprintf('mysql:host=%s;port=%s;charset=%s', $host, $port, $charset);
        if ($name !== '') {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $name, $charset);
        }

        $this->pdo = new PDO($dsn, $config['user'] ?? '', $config['password'] ?? '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public function pdo()
    {
        return $this->pdo;
    }
}
