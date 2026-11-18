<?php

declare(strict_types=1);

namespace Poetry;

final class SearchHighlight
{
    /** @return list<string> */
    public static function terms(string $query): array
    {
        $q = trim(preg_replace('/[\'"*\\\\]/', ' ', $query) ?? '');
        if ($q === '') {
            return [];
        }
        $parts = preg_split('/\s+/u', $q) ?: [];
        $terms = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            foreach ([$part, Chinese::toTraditional($part), Chinese::toSimplified($part)] as $t) {
                if ($t !== '' && !in_array($t, $terms, true)) {
                    $terms[] = $t;
                }
            }
        }
        usort($terms, static fn (string $a, string $b): int => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));

        return $terms;
    }

    /**
     * @param list<string> $terms
     */
    public static function mark(string $text, array $terms): string
    {
        if ($text === '' || $terms === []) {
            return View::e($text);
        }
        $ranges = [];
        $len = mb_strlen($text, 'UTF-8');
        foreach ($terms as $term) {
            if ($term === '') {
                continue;
            }
            $tLen = mb_strlen($term, 'UTF-8');
            if ($tLen === 0) {
                continue;
            }
            $start = 0;
            while ($start <= $len - $tLen) {
                $slice = mb_substr($text, $start, $tLen, 'UTF-8');
                if ($slice === $term) {
                    $ranges[] = [$start, $start + $tLen];
                    $start += $tLen;

                    continue;
                }
                $start++;
            }
        }
        if ($ranges === []) {
            return View::e($text);
        }
        usort($ranges, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $merged = [];
        foreach ($ranges as $r) {
            if ($merged === []) {
                $merged[] = $r;

                continue;
            }
            $last = $merged[count($merged) - 1];
            if ($r[0] <= $last[1]) {
                $merged[count($merged) - 1] = [$last[0], max($last[1], $r[1])];
            } else {
                $merged[] = $r;
            }
        }
        $out = '';
        $pos = 0;
        foreach ($merged as [$s, $e]) {
            if ($s > $pos) {
                $out .= View::e(mb_substr($text, $pos, $s - $pos, 'UTF-8'));
            }
            $out .= '<mark class="search-hit">' . View::e(mb_substr($text, $s, $e - $s, 'UTF-8')) . '</mark>';
            $pos = $e;
        }
        if ($pos < $len) {
            $out .= View::e(mb_substr($text, $pos, null, 'UTF-8'));
        }

        return $out;
    }
}