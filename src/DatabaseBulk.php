<?php

declare(strict_types=1);

namespace Poetry;

use PDO;

final class DatabaseBulk
{
    public static function truncateBody(string $body, int $max): string
    {
        if ($max <= 0 || strlen($body) <= $max) {
            return $body;
        }

        return mb_substr($body, 0, $max, 'UTF-8');
    }

    public static function workBodyFromParagraphsJson(string $paragraphsJson): string
    {
        $paras = json_decode($paragraphsJson, true);
        if (!is_array($paras)) {
            return '';
        }

        return WorkParagraphs::bodyFromLines(WorkParagraphs::flattenList($paras));
    }

    /**
     * @param list<array<int, mixed>> $rows
     */
    public static function flushWorkBatch(PDO $mysql, array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $cols =
            'id, corpus, kind, author, title, rhythmic, paragraphs, notes, body, source_file, source_index';
        $tuple = '(?,?,?,?,?,?,?,?,?,?,?)';
        $parts = array_fill(0, count($rows), $tuple);
        $flat = [];
        foreach ($rows as $r) {
            array_push($flat, ...$r);
        }
        $sql = 'INSERT INTO works (' . $cols . ') VALUES ' . implode(',', $parts);
        $mysql->prepare($sql)->execute($flat);
    }

    /**
     * @param list<array{0: string, 1: string, 2: string}> $rows
     *
     * @return list<array{0: string, 1: string, 2: string}>
     */
    public static function dedupeAuthorBatch(array $rows): array
    {
        /** @var array<string, array{0: string, 1: string, 2: string}> */
        $map = [];
        foreach ($rows as $r) {
            $key = $r[1] . "\0" . $r[0];
            if (!isset($map[$key]) || strlen($r[2]) > strlen($map[$key][2])) {
                $map[$key] = $r;
            }
        }

        return array_values($map);
    }

    /**
     * @param list<array{0: string, 1: string, 2: string}> $rows
     */
    public static function flushAuthorBatch(PDO $mysql, array $rows): void
    {
        $rows = self::dedupeAuthorBatch($rows);
        if ($rows === []) {
            return;
        }
        $sql = <<<'SQL'
INSERT INTO authors (name, kind, description) VALUES (?,?,?)
ON DUPLICATE KEY UPDATE
  description = IF(
    CHAR_LENGTH(VALUES(description)) > CHAR_LENGTH(description),
    VALUES(description),
    description
  )
SQL;
        $st = $mysql->prepare($sql);
        foreach ($rows as $r) {
            $st->execute([$r[0], $r[1], $r[2]]);
        }
    }
}