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

    public static function projectRoot(): string
    {
        return dirname(__DIR__);
    }

    /** chinese-poetry 数据目录（默认项目根下 ./data，不纳入 Git） */
    public static function resolveDataPath(): string
    {
        $root = self::projectRoot();
        $dataEnv = self::get('POETRY_DATA_PATH', './data') ?? './data';
        if (str_starts_with($dataEnv, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $dataEnv) === 1) {
            return realpath($dataEnv) ?: $dataEnv;
        }

        $path = $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dataEnv);

        return realpath($path) ?: $path;
    }
}
