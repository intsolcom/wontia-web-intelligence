<?php
namespace App\Core;

class Database
{
    private static ?\PDO $instance = null;

    public static function instance(): \PDO
    {
        if (self::$instance === null) {
            $host = Config::get('DB_HOST', 'localhost');
            $port = Config::get('DB_PORT', '3306');
            $name = Config::get('DB_NAME', 'wontia');
            $user = Config::get('DB_USER', 'wontia');
            $pass = Config::get('DB_PASS', '');
            $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
            $maxRetries = 3;
            $lastException = null;
            for ($i = 0; $i < $maxRetries; $i++) {
                try {
                    self::$instance = new \PDO($dsn, $user, $pass, [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                        \PDO::ATTR_EMULATE_PREPARES => false,
                        \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                    ]);
                    return self::$instance;
                } catch (\PDOException $e) {
                    $lastException = $e;
                    if ($i < $maxRetries - 1) usleep(500000);
                }
            }
            throw $lastException;
        }
        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}
