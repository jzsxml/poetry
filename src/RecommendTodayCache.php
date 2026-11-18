<?php

declare(strict_types=1);

namespace Poetry;

/** 时令荐诗 API 按日 + 分页 + 简繁 文件缓存 */
final class RecommendTodayCache
{
    private static function cacheDir(): string
    {
        $dir = dirname(__DIR__) . '/var/cache/recommend-today';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private static function ttlSeconds(): int
    {
        $raw = Config::get('RECOMMEND_CACHE_TTL', '86400');
        $n = (int) $raw;

        return $n > 60 ? $n : 86400;
    }

    public static function httpMaxAge(): int
    {
        $raw = Config::get('RECOMMEND_HTTP_MAX_AGE', '3600');
        $n = (int) $raw;

        return max(60, min(86400, $n > 0 ? $n : 3600));
    }

    public static function key(string $solarYmd, int $offset, int $limit, string $variant): string
    {
        $v = $variant === 'trad' ? 'trad' : 'simp';

        return $solarYmd . '_' . $offset . '_' . $limit . '_' . $v;
    }

    private static function filePath(string $key): string
    {
        return self::cacheDir() . '/' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $key) . '.json';
    }

    /** @return array<string, mixed>|null */
    public static function get(string $key): ?array
    {
        $path = self::filePath($key);
        if (!is_file($path)) {
            return null;
        }
        $age = time() - (int) filemtime($path);
        if ($age > self::ttlSeconds()) {
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

        return is_array($data) ? $data : null;
    }

    /** @param array<string, mixed> $payload */
    public static function set(string $key, array $payload): void
    {
        $path = self::filePath($key);
        file_put_contents(
            $path,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            LOCK_EX
        );
    }

    public static function clear(): void
    {
        $dir = self::cacheDir();
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}