<?php

declare(strict_types=1);

/**
 * 检索性能：FULLTEXT（ngram）+ kind_id 复合索引。
 * 若索引列清单与代码不一致会自动 DROP 后重建（修复 poetry.sql 与旧版 ft_works_meta 错配）。
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use Poetry\Config;
use Poetry\Database;

Config::load(dirname(__DIR__) . '/.env');

if (!Database::available()) {
    fwrite(STDERR, "Database unavailable.\n");
    exit(1);
}

$pdo = Database::pdo();

/**
 * @return list<string>
 */
function fulltextIndexColumns(\PDO $pdo, string $indexName): array
{
    $stmt = $pdo->query('SHOW INDEX FROM works');
    $bySeq = [];
    foreach ($stmt as $row) {
        if (($row['Key_name'] ?? '') !== $indexName) {
            continue;
        }
        $bySeq[(int) $row['Seq_in_index']] = (string) $row['Column_name'];
    }
    ksort($bySeq);

    return array_values($bySeq);
}

/** @var array<string, array{columns: list<string>, sql: string}> */
$indexes = [
    'idx_works_kind_id' => [
        'type' => 'key',
        'columns' => [],
        'sql' => 'ALTER TABLE works ADD KEY idx_works_kind_id (kind, id)',
    ],
    'ft_works_meta' => [
        'type' => 'fulltext',
        'columns' => ['author', 'title', 'rhythmic'],
        'sql' => 'ALTER TABLE works ADD FULLTEXT INDEX ft_works_meta (author, title, rhythmic) WITH PARSER ngram',
    ],
    'ft_works_trb' => [
        'type' => 'fulltext',
        'columns' => ['title', 'rhythmic', 'body'],
        'sql' => 'ALTER TABLE works ADD FULLTEXT INDEX ft_works_trb (title, rhythmic, body) WITH PARSER ngram',
    ],
    'ft_works_ngram' => [
        'type' => 'fulltext',
        'columns' => ['author', 'title', 'rhythmic', 'body'],
        'sql' => 'ALTER TABLE works ADD FULLTEXT INDEX ft_works_ngram (author, title, rhythmic, body) WITH PARSER ngram',
    ],
];

foreach ($indexes as $name => $spec) {
    $type = $spec['type'];
    $expected = $spec['columns'];
    $sql = $spec['sql'];

    if ($type === 'key') {
        $existing = [];
        foreach ($pdo->query('SHOW INDEX FROM works') as $row) {
            $existing[$row['Key_name']] = true;
        }
        if (isset($existing[$name])) {
            echo "skip {$name}\n";
            continue;
        }
        try {
            $pdo->exec($sql);
            echo "ok {$name}\n";
        } catch (Throwable $e) {
            fwrite(STDERR, "fail {$name}: {$e->getMessage()}\n");
        }

        continue;
    }

    $current = fulltextIndexColumns($pdo, $name);
    if ($current === $expected) {
        echo "skip {$name} (" . implode(',', $current) . ")\n";
        continue;
    }
    if ($current !== []) {
        try {
            $pdo->exec("ALTER TABLE works DROP INDEX {$name}");
            echo "dropped {$name} (was " . implode(',', $current) . ")\n";
        } catch (Throwable $e) {
            fwrite(STDERR, "drop {$name} failed: {$e->getMessage()}\n");
            continue;
        }
    }
    try {
        $pdo->exec($sql);
        echo "ok {$name} (" . implode(',', $expected) . ")\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "fail {$name}: {$e->getMessage()}\n");
    }
}

echo "done.\n";