<?php

declare(strict_types=1);

namespace Poetry;

final class WorkLayout
{
    /**
     * 正文较长（古文、长诗、散曲等）时用左对齐散文版式；短篇仍居中呈「诗笺」样式。
     *
     * @param list<mixed> $paragraphs
     */
    public static function prefersProseBody(array $paragraphs): bool
    {
        if ($paragraphs === []) {
            return false;
        }
        $count = 0;
        $total = 0;
        $maxLen = 0;
        $longLines = 0;
        foreach ($paragraphs as $p) {
            if (!is_string($p)) {
                continue;
            }
            $t = preg_replace('/\s+/u', '', $p) ?? $p;
            $len = mb_strlen($t, 'UTF-8');
            if ($len === 0) {
                continue;
            }
            $count++;
            $total += $len;
            if ($len > $maxLen) {
                $maxLen = $len;
            }
            if ($len >= 28) {
                $longLines++;
            }
        }
        if ($count === 0) {
            return false;
        }
        if ($count >= 12) {
            return true;
        }
        if ($total >= 220) {
            return true;
        }
        if ($maxLen >= 44) {
            return true;
        }

        return $longLines >= 3 && $longLines * 2 >= $count;
    }
}