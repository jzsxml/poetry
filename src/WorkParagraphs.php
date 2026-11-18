<?php

declare(strict_types=1);

namespace Poetry;

/** 将库内各种 paragraphs 结构规整为 string[] */
final class WorkParagraphs
{
    /**
     * @param list<mixed> $raw
     * @return list<string>
     */
    public static function flattenList(array $raw): array
    {
        $out = [];
        foreach ($raw as $item) {
            foreach (self::flattenItem($item) as $line) {
                $out[] = $line;
            }
        }

        return $out;
    }

    /** @return list<string> */
    private static function flattenItem(mixed $item): array
    {
        if (is_string($item)) {
            $t = trim($item);
            if ($t === '' || self::isCorruptedToken($t)) {
                return [];
            }

            return [$t];
        }
        if (!is_array($item)) {
            return [];
        }
        if (isset($item['paragraphs']) && is_array($item['paragraphs'])) {
            $lines = [];
            $sub = $item['subchapter'] ?? $item['chapter'] ?? null;
            if (is_string($sub) && trim($sub) !== '') {
                $lines[] = '〔' . trim($sub) . '〕';
            }
            foreach ($item['paragraphs'] as $p) {
                $lines = array_merge($lines, self::flattenItem($p));
            }

            return $lines;
        }
        if (self::isListArray($item)) {
            $lines = [];
            foreach ($item as $p) {
                $lines = array_merge($lines, self::flattenItem($p));
            }

            return $lines;
        }

        return [];
    }

    /** @param array<mixed> $arr */
    private static function isListArray(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }

        return array_keys($arr) === range(0, count($arr) - 1);
    }

    public static function isCorruptedToken(string $line): bool
    {
        return str_contains($line, '[object Object]');
    }

    /** @param list<string> $lines */
    public static function linesCorrupted(array $lines): bool
    {
        foreach ($lines as $line) {
            if (self::isCorruptedToken($line)) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $lines */
    public static function bodyFromLines(array $lines): string
    {
        return implode("\n", $lines);
    }
}