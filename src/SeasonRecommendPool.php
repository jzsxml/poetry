<?php

declare(strict_types=1);

namespace Poetry;

use PDO;

/** 离线预采样各节令主题作品，线上按 theme_id 直读 */
final class SeasonRecommendPool
{
    public static function poolSize(): int
    {
        $n = (int) (Config::get('SEASON_POOL_SIZE', '96') ?: 96);

        return max(24, min(256, $n));
    }

    public static function ensureTable(PDO $db): void
    {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS season_recommend_pool (
              theme_id VARCHAR(64) NOT NULL,
              slot SMALLINT UNSIGNED NOT NULL,
              work_id VARCHAR(320) NOT NULL,
              score SMALLINT NOT NULL DEFAULT 0,
              PRIMARY KEY (theme_id, slot),
              KEY idx_season_pool_work (work_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public static function isReady(PDO $db, string $themeId): bool
    {
        try {
            $stmt = $db->prepare(
                'SELECT 1 FROM season_recommend_pool WHERE theme_id = ? LIMIT 1'
            );
            $stmt->execute([$themeId]);

            return (bool) $stmt->fetchColumn();
        } catch (\PDOException) {
            return false;
        }
    }

    /**
     * @param array<string, bool> $seen
     * @return list<array<string, mixed>>
     */
    public static function fetchHits(
        PDO $db,
        PoetryRepository $repo,
        string $themeId,
        array $theme,
        int $limit,
        int $offset,
        array &$seen
    ): array {
        if (!self::isReady($db, $themeId)) {
            return [];
        }
        $stmt = $db->prepare(
            'SELECT MAX(slot) FROM season_recommend_pool WHERE theme_id = ?'
        );
        $stmt->execute([$themeId]);
        $maxSlot = $stmt->fetchColumn();
        if ($maxSlot === false || $maxSlot === null) {
            return [];
        }
        $max = (int) $maxSlot;
        $size = $max + 1;
        $start = ($offset * $limit) % $size;

        $slots = [];
        for ($i = 0; $i < $size && count($slots) < $limit * 2; $i++) {
            $slots[] = ($start + $i) % $size;
        }

        $placeholders = implode(',', array_fill(0, count($slots), '?'));
        $sql = "SELECT work_id, score FROM season_recommend_pool
                WHERE theme_id = ? AND slot IN ({$placeholders})
                ORDER BY score DESC, slot";
        $stmt = $db->prepare($sql);
        $params = array_merge([$themeId], $slots);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $ids = [];
        foreach ($rows as $row) {
            $id = (string) ($row['work_id'] ?? '');
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $ids[] = $id;
            if (count($ids) >= $limit) {
                break;
            }
        }
        if ($ids === []) {
            return [];
        }

        $hits = $repo->getSearchHitsByIds($ids);
        /** @var list<array<string, mixed>> $out */
        $out = [];
        foreach ($hits as $h) {
            $id = (string) ($h['id'] ?? '');
            if ($id === '' || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = array_merge($h, [
                'themeId' => $themeId,
                'themeName' => (string) ($theme['name'] ?? ''),
            ]);
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    public static function rebuild(PDO $db, PoetryRepository $repo): void
    {
        self::ensureTable($db);
        $db->exec('TRUNCATE TABLE season_recommend_pool');

        $size = self::poolSize();
        $insert = $db->prepare(
            'INSERT INTO season_recommend_pool (theme_id, slot, work_id, score)
             VALUES (?, ?, ?, ?)'
        );

        foreach (SeasonThemes::all() as $theme) {
            $themeId = (string) ($theme['id'] ?? '');
            if ($themeId === '') {
                continue;
            }
            $keywords = is_array($theme['keywords'] ?? null) ? $theme['keywords'] : [];
            $name = (string) ($theme['name'] ?? '');
            if ($keywords === []) {
                continue;
            }

            /** @var array<string, array{hit: array<string, mixed>, score: int}> $ranked */
            $ranked = [];
            foreach (array_slice($keywords, 0, 4) as $kw) {
                $result = $repo->searchThemeKeyword((string) $kw, $size * 2, 0);
                foreach ($result['hits'] as $h) {
                    $id = (string) ($h['id'] ?? '');
                    if ($id === '') {
                        continue;
                    }
                    $score = self::scoreHitForTheme($h, $keywords, $name);
                    if (!isset($ranked[$id]) || $ranked[$id]['score'] < $score) {
                        $ranked[$id] = ['hit' => $h, 'score' => $score];
                    }
                }
            }

            if ($ranked === []) {
                continue;
            }
            uasort($ranked, static fn (array $a, array $b) => $b['score'] <=> $a['score']);
            $slot = 0;
            foreach ($ranked as $entry) {
                if ($slot >= $size) {
                    break;
                }
                $insert->execute([
                    $themeId,
                    $slot,
                    $entry['hit']['id'],
                    $entry['score'],
                ]);
                $slot++;
            }
        }
    }

    /** @param list<string> $keywords */
    private static function scoreHitForTheme(array $hit, array $keywords, string $themeName): int
    {
        $title = (string) ($hit['title'] ?? '');
        $rhythmic = (string) ($hit['rhythmic'] ?? '');
        $preview = (string) ($hit['preview'] ?? '');
        $score = 0;
        foreach ($keywords as $kw) {
            if ($title !== '' && str_contains($title, $kw)) {
                $score += 8;
            } elseif ($rhythmic !== '' && str_contains($rhythmic, $kw)) {
                $score += 5;
            } elseif ($preview !== '' && str_contains($preview, $kw)) {
                $score += 2;
            } else {
                $score += 1;
            }
        }
        if ($themeName !== '' && $title !== '' && str_contains($title, $themeName)) {
            $score += 12;
        }

        return $score;
    }
}