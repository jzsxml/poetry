<?php

declare(strict_types=1);

namespace Poetry;

final class Config
{
    /** @var array<string, string> */
    private static array $env = [];

    public static function load(string $envPath): void
    {
        if (is_file($envPath)) {
            $dotenv = \Dotenv\Dotenv::createImmutable(dirname($envPath));
            $dotenv->safeLoad();
        }
        foreach ($_ENV as $k => $v) {
            if (is_string($v)) {
                self::$env[$k] = $v;
            }
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::$env[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default;
    }

    public static function dbDsn(): string
    {
        $host = self::get('DB_HOST', 'localhost');
        $port = self::get('DB_PORT', '3306');
        $db = self::get('DB_DATABASE', 'poetry');
        $charset = self::get('DB_CHARSET', 'utf8mb4');

        return "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
    }
}
