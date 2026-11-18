<?php

declare(strict_types=1);

namespace Poetry;

/** 检索 COUNT 缓存：翻页时避免重复全表统计 */
final class SearchTotalCache
{
    private static function cacheDir(): string
    {
        $dir = dirname(__DIR__) . '/var/cache/search-total';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private static function ttlSeconds(): int
    {
        $n = (int) (Config::get('SEARCH_TOTAL_CACHE_TTL', '1800') ?: 1800);

        return max(300, min(86400, $n));
    }

    public static function cap(): int
    {
        $n = (int) (Config::get('SEARCH_TOTAL_CAP', '10001') ?: 10001);

        return max(1001, min(100001, $n));
    }

    /** 允许翻页浏览的最大条数（2G 小内存机建议 500） */
    public static function browseMax(): int
    {
        $n = (int) (Config::get('SEARCH_BROWSE_MAX', '500') ?: 500);

        return max(50, min(5000, $n));
    }

    public static function key(string $q, string $kind): string
    {
        $norm = Chinese::normalizeForSearch($q);
        $k = preg_replace('/[^a-z0-9_-]/i', '_', $kind) ?: 'all';

        return hash('sha256', $k . "\0" . $norm);
    }

    private static function filePath(string $key): string
    {
        return self::cacheDir() . '/' . $key . '.json';
    }

    /** @return array{total: int, capped: bool}|null */
    public static function get(string $key): ?array
    {
        $path = self::filePath($key);
        if (!is_file($path)) {
            return null;
        }
        if (time() - (int) filemtime($path) > self::ttlSeconds()) {
            return null;
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!is_array($data) || !isset($data['total'])) {
            return null;
        }

        return [
            'total' => (int) $data['total'],
            'capped' => !empty($data['capped']),
        ];
    }

    public static function set(string $key, int $total, bool $capped): void
    {
        $path = self::filePath($key);
        file_put_contents(
            $path,
            json_encode(['total' => $total, 'capped' => $capped], JSON_THROW_ON_ERROR),
            LOCK_EX
        );
    }
}