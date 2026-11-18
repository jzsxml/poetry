<?php

declare(strict_types=1);

namespace Poetry;

final class AuthorWallCache
{
    private static function cacheDir(): string
    {
        $dir = dirname(__DIR__) . '/var/cache/author-wall';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private static function ttlSeconds(): int
    {
        $raw = Config::get('AUTHOR_WALL_CACHE_TTL', '604800');
        $n = (int) $raw;

        return $n > 60 ? $n : 604800;
    }

    private static function filePath(string $kind, int $limit): string
    {
        $safeKind = preg_replace('/[^a-z0-9_-]/i', '_', $kind) ?: 'all';

        return self::cacheDir() . '/' . $safeKind . '_' . $limit . '.json';
    }

    /** @return list<array{author: string, count: int}>|null */
    public static function get(string $kind, int $limit): ?array
    {
        $path = self::filePath($kind, $limit);
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
        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    /** @param list<array{author: string, count: int}> $items */
    public static function set(string $kind, int $limit, array $items): void
    {
        $path = self::filePath($kind, $limit);
        file_put_contents(
            $path,
            json_encode($items, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
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