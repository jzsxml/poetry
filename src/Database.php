<?php

declare(strict_types=1);

namespace Poetry;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }
        try {
            $pdo = new PDO(
                Config::dbDsn(),
                Config::get('DB_USERNAME', 'root'),
                Config::get('DB_PASSWORD', ''),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    // 检索 SQL 会多次复用同名占位符（与 SQLite 版一致）；原生预处理不支持
                    PDO::ATTR_EMULATE_PREPARES => true,
                ]
            );
        } catch (PDOException $e) {
            throw new PDOException('数据库连接失败: ' . $e->getMessage(), (int) $e->getCode());
        }
        self::$pdo = $pdo;

        return self::$pdo;
    }

    public static function available(): bool
    {
        try {
            self::pdo()->query('SELECT 1');

            return true;
        } catch (PDOException) {
            return false;
        }
    }
}
