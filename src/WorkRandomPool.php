<?php

declare(strict_types=1);

namespace Poetry;

use PDO;

/** 随机一首：预采样 work_id，避免在线全表 RAND() */
final class WorkRandomPool
{
    public const KIND_ALL = '__all__';

    public static function poolSize(): int
    {
        $n = (int) (Config::get('RANDOM_POOL_SIZE', '384') ?: 384);

        return max(32, min(2048, $n));
    }

    public static function ensureTable(PDO $db): void
    {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS random_work_pool (
              kind VARCHAR(32) NOT NULL,
              slot SMALLINT UNSIGNED NOT NULL,
              work_id VARCHAR(320) NOT NULL,
              PRIMARY KEY (kind, slot),
              KEY idx_pool_work (work_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public static function isReady(PDO $db): bool
    {
        try {
            $stmt = $db->prepare('SELECT 1 FROM random_work_pool WHERE kind = ? LIMIT 1');
            $stmt->execute([self::KIND_ALL]);

            return (bool) $stmt->fetchColumn();
        } catch (\PDOException) {
            return false;
        }
    }

    public static function pickWorkId(PDO $db, ?string $kind): ?string
    {
        $poolKind = $kind ?? self::KIND_ALL;
        $stmt = $db->prepare(
            'SELECT MAX(slot) FROM random_work_pool WHERE kind = ?'
        );
        $stmt->execute([$poolKind]);
        $maxSlot = $stmt->fetchColumn();
        if ($maxSlot === false || $maxSlot === null) {
            if ($poolKind !== self::KIND_ALL) {
                return self::pickWorkId($db, null);
            }

            return null;
        }
        $max = (int) $maxSlot;
        $stmt = $db->prepare(
            'SELECT work_id FROM random_work_pool WHERE kind = ? AND slot = ?'
        );
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $slot = random_int(0, $max);
            $stmt->execute([$poolKind, $slot]);
            $id = $stmt->fetchColumn();
            if (is_string($id) && $id !== '') {
                return $id;
            }
        }

        return null;
    }

    public static function rebuild(PDO $db): void
    {
        self::ensureTable($db);
        $db->exec('TRUNCATE TABLE random_work_pool');

        $size = self::poolSize();
        /** @var list<string> $kinds */
        $kinds = $db->query(
            'SELECT DISTINCT kind FROM works ORDER BY kind'
        )->fetchAll(PDO::FETCH_COLUMN);

        $targets = array_merge([self::KIND_ALL], $kinds);
        $insert = $db->prepare(
            'INSERT INTO random_work_pool (kind, slot, work_id) VALUES (?, ?, ?)'
        );

        foreach ($targets as $target) {
            if ($target === self::KIND_ALL) {
                $stmt = $db->prepare(
                    'SELECT id FROM works ORDER BY RAND() LIMIT ?'
                );
                $stmt->bindValue(1, $size, PDO::PARAM_INT);
                $stmt->execute();
            } else {
                $stmt = $db->prepare(
                    'SELECT id FROM works WHERE kind = ? ORDER BY RAND() LIMIT ?'
                );
                $stmt->bindValue(1, $target);
                $stmt->bindValue(2, $size, PDO::PARAM_INT);
                $stmt->execute();
            }
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($ids as $slot => $id) {
                if (!is_string($id) || $id === '') {
                    continue;
                }
                $insert->execute([$target, (int) $slot, $id]);
            }
        }
    }
}