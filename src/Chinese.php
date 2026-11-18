<?php

declare(strict_types=1);

namespace Poetry;

use Overtrue\PHPOpenCC\OpenCC;

final class Chinese
{
    public static function hasChineseForSearch(string $query): bool
    {
        return (bool) preg_match('/[\x{4e00}-\x{9fff}]/u', $query);
    }

    public static function toTraditional(string $text): string
    {
        if ($text === '') {
            return $text;
        }

        return OpenCC::convert($text, 'S2T');
    }

    public static function toSimplified(string $text): string
    {
        if ($text === '') {
            return $text;
        }

        return OpenCC::convert($text, 'T2S');
    }

    /** 库内多为繁体：简体展示 T2S，繁体原样 */
    public static function displayText(string $text, string $variant): string
    {
        if ($text === '') {
            return $text;
        }
        if ($variant === 'trad') {
            return $text;
        }

        return self::toSimplified($text);
    }

    /**
     * 去掉未成对的书名号（如截断导致的「《上清宝鼎诗」）；不补全书名号。
     */
    public static function stripUnpairedBookTitleMarks(string $text): string
    {
        if ($text === '') {
            return $text;
        }
        $pairs = [
            ['《', '》'],
            ['『', '』'],
        ];
        $len = mb_strlen($text, 'UTF-8');
        $remove = array_fill(0, $len, false);
        foreach ($pairs as [$open, $close]) {
            $stack = [];
            for ($i = 0; $i < $len; $i++) {
                $c = mb_substr($text, $i, 1, 'UTF-8');
                if ($c === $open) {
                    $stack[] = $i;
                } elseif ($c === $close) {
                    if ($stack !== []) {
                        array_pop($stack);
                    } else {
                        $remove[$i] = true;
                    }
                }
            }
            foreach ($stack as $pos) {
                $remove[$pos] = true;
            }
        }
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            if (!$remove[$i]) {
                $out .= mb_substr($text, $i, 1, 'UTF-8');
            }
        }

        return $out;
    }

    private const PUNCT_RE = '/[\s，。、；：！？,.;:!?\'"·…—\-（）()\[\]【】]/u';

    public static function meaningfulChars(string $trad): array
    {
        $re = '/[\s，。、；：！？,.;:!?\'"·…—\-（）()\[\]【】]/u';
        $chars = [];
        $len = mb_strlen($trad, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $c = mb_substr($trad, $i, 1, 'UTF-8');
            if ($c !== '' && trim($c) !== '' && !preg_match($re, $c)) {
                $chars[] = $c;
            }
        }

        return $chars;
    }

    public static function toLooseLikePattern(string $query): string
    {
        $trad = self::toTraditional($query);
        $chars = self::meaningfulChars($trad);
        if ($chars === []) {
            return '%' . $trad . '%';
        }

        return '%' . implode('%', $chars) . '%';
    }

    /** @return list<string>|null */
    public static function toLineHeadPatterns(string $query): ?array
    {
        $chars = self::meaningfulChars(self::toTraditional($query));
        if (count($chars) < 2) {
            return null;
        }
        $allSame = count(array_unique($chars)) === 1;
        if (!$allSame) {
            return null;
        }
        $patterns = [];
        $joinedComma = implode('，', $chars);
        $patterns[] = '["' . $joinedComma . '%';
        if (count($chars) === 1) {
            $patterns[] = '["' . $chars[0] . '%';
        }

        return $patterns;
    }

    public static function prefersLikeHeadSearch(string $query): bool
    {
        $chars = self::meaningfulChars(self::toTraditional($query));
        if ($chars === []) {
            return true;
        }
        if (count($chars) <= 4) {
            return true;
        }
        if (count($chars) >= 2 && count(array_unique($chars)) === 1) {
            return true;
        }

        return false;
    }

    /** 仅「字字相同」开篇检索（如鹅鹅鹅）走段落 LIKE 排序；单字「日」等仍走 FTS */
    public static function isRepeatedCharHeadSearch(string $query): bool
    {
        $chars = self::meaningfulChars(self::toTraditional($query));
        if (count($chars) < 2) {
            return false;
        }

        return count(array_unique($chars)) === 1;
    }

    public static function normalizeForSearch(string $query): string
    {
        $stripped = preg_replace(self::PUNCT_RE, ' ', $query) ?? $query;

        return self::toTraditional(trim($stripped));
    }
}
