<?php

declare(strict_types=1);

namespace Poetry;

use PDO;

/** 名人墙预聚合：避免对 works 全表 GROUP BY */
final class AuthorWallStats
{
    public const KIND_ALL = '__all__';

    public static function ensureTable(PDO $db): void
    {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS author_wall_stats (
              kind VARCHAR(32) NOT NULL,
              author VARCHAR(255) NOT NULL,
              work_count INT UNSIGNED NOT NULL,
              PRIMARY KEY (kind, author),
              KEY idx_stats_kind_count (kind, work_count)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public static function isReady(PDO $db): bool
    {
        try {
            $stmt = $db->prepare('SELECT 1 FROM author_wall_stats WHERE kind = ? LIMIT 1');
            $stmt->execute([self::KIND_ALL]);

            return (bool) $stmt->fetchColumn();
        } catch (\PDOException) {
            return false;
        }
    }

    public static function rebuild(PDO $db): void
    {
        self::ensureTable($db);
        $db->exec('TRUNCATE TABLE author_wall_stats');

        $db->exec(
            "INSERT INTO author_wall_stats (kind, author, work_count)
             SELECT kind, author, COUNT(*) AS c FROM works
             WHERE author IS NOT NULL AND TRIM(author) != ''
             GROUP BY kind, author"
        );

        $all = self::KIND_ALL;
        $db->exec(
            "INSERT INTO author_wall_stats (kind, author, work_count)
             SELECT '{$all}', author, COUNT(*) AS c FROM works
             WHERE author IS NOT NULL AND TRIM(author) != ''
             GROUP BY author"
        );

        AuthorWallCache::clear();
    }
}