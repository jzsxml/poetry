<?php

declare(strict_types=1);

namespace Poetry;

final class WorkId
{
    public const MAX_LEN = 320;

    public static function isValid(string $id): bool
    {
        if ($id === '' || strlen($id) > self::MAX_LEN) {
            return false;
        }
        if (preg_match('/[\x00-\x1f\x7f]/', $id)) {
            return false;
        }
        if (str_contains($id, '..') || str_contains($id, '\\')) {
            return false;
        }

        return true;
    }

    public static function normalize(string $raw): ?string
    {
        $id = rawurldecode(trim($raw));

        return self::isValid($id) ? $id : null;
    }
}
