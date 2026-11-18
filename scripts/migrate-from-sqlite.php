<?php

declare(strict_types=1);

/**
 * 从 Next 项目的 poetry.db 迁入 MySQL（需 PDO sqlite + mysql）
 * 用法: composer run migrate
 *
 * 环境变量（可选）:
 *   MIGRATE_ROWS_PER_INSERT  每条 INSERT 的行数，默认 120
 *   MIGRATE_COMMIT_EVERY     每多少行提交一次事务，默认 6000
 *   MIGRATE_BODY_MAX         限制 body 长度（0=不截断），导入后可重建 FTS
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Poetry\AuthorWallStats;
use Poetry\Config;
use Poetry\WorkRandomPool;

Config::load(dirname(__DIR__) . '/.env');

$sqlitePath = Config::get('POETRY_SQLITE_PATH', dirname(__DIR__, 2) . '/web/poetry.db');
$sqlitePath = realpath($sqlitePath) ?: $sqlitePath;
if (!is_file($sqlitePath)) {
    fwrite(STDERR, "找不到 SQLite: {$sqlitePath}\n");
    exit(1);
}

$rowsPerInsert = max(10, (int) (Config::get('MIGRATE_ROWS_PER_INSERT') ?: 120));
$commitEvery = max($rowsPerInsert, (int) (Config::get('MIGRATE_COMMIT_EVERY') ?: 6000));
$bodyMax = (int) (Config::get('MIGRATE_BODY_MAX') ?: 0);

$collation = Config::get('DB_COLLATION', 'utf8mb4_unicode_ci');
$mysql = new PDO(
    Config::dbDsn(),
    Config::get('DB_USERNAME', 'root'),
    Config::get('DB_PASSWORD', ''),
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE {$collation}",
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    ]
);

$sqlite = new PDO('sqlite:' . $sqlitePath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

echo "SQLite: {$sqlitePath}\n";
echo "MySQL: " . Config::get('DB_DATABASE') . "\n";
echo "批量: {$rowsPerInsert} 行/INSERT，每 {$commitEvery} 行提交\n";

$t0 = microtime(true);

$mysql->exec('SET SESSION foreign_key_checks=0');
$mysql->exec('SET SESSION unique_checks=0');
try {
    $mysql->exec('SET SESSION sql_log_bin=0');
} catch (PDOException) {
    /* 无 SUPER 权限时忽略 */
}
$mysql->exec('TRUNCATE TABLE works');
$mysql->exec('TRUNCATE TABLE authors');
$mysql->exec('SET SESSION foreign_key_checks=1');
$mysql->exec('SET SESSION unique_checks=1');

/** @param list<array<int, mixed>> $rows */
function flushWorkBatch(PDO $mysql, array $rows): void
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
 * 同一批内按 (kind,name) 去重，避免多值 INSERT 主键重复。
 *
 * @param list<array{0: string, 1: string, 2: string}> $rows
 *
 * @return list<array{0: string, 1: string, 2: string}>
 */
function dedupeAuthorBatch(array $rows): array
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

/** @param list<array{0: string, 1: string, 2: string}> $rows */
function flushAuthorBatch(PDO $mysql, array $rows): void
{
    $rows = dedupeAuthorBatch($rows);
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

function workBodyFromParagraphs(string $paragraphsJson): string
{
    $paras = json_decode($paragraphsJson, true);
    if (!is_array($paras)) {
        return '';
    }

    return \Poetry\WorkParagraphs::bodyFromLines(\Poetry\WorkParagraphs::flattenList($paras));
}

function truncateBody(string $body, int $max): string
{
    if ($max <= 0 || strlen($body) <= $max) {
        return $body;
    }

    return mb_substr($body, 0, $max, 'UTF-8');
}

// 只读 works 表，避免扫虚拟表 works_fts（大库极慢）
$sqlite->exec('PRAGMA query_only = ON');
$works = $sqlite->query('SELECT * FROM works');

$batch = [];
$total = 0;
$sinceCommit = 0;
$mysql->beginTransaction();

while ($row = $works->fetch(PDO::FETCH_ASSOC)) {
    $body = workBodyFromParagraphs((string) $row['paragraphs']);
    $body = truncateBody($body, $bodyMax);

    $batch[] = [
        $row['id'],
        $row['corpus'],
        $row['kind'],
        $row['author'],
        $row['title'],
        $row['rhythmic'],
        $row['paragraphs'],
        $row['notes'],
        $body,
        $row['source_file'],
        (int) $row['source_index'],
    ];
    $total++;

    if (count($batch) >= $rowsPerInsert) {
        $n = count($batch);
        flushWorkBatch($mysql, $batch);
        $batch = [];
        $sinceCommit += $n;

        if ($sinceCommit >= $commitEvery) {
            $mysql->commit();
            $mysql->beginTransaction();
            $sinceCommit = 0;
            $sec = round(microtime(true) - $t0, 1);
            echo "…{$total} 作品 ({$sec}s)\n";
        }
    }
}

if ($batch !== []) {
    flushWorkBatch($mysql, $batch);
}
$mysql->commit();

$sec = round(microtime(true) - $t0, 1);
echo "作品迁入: {$total} ({$sec}s)\n";

unset($works, $batch);
$sqlite->exec('PRAGMA query_only = OFF');
$mysql->exec('SET NAMES utf8mb4 COLLATE ' . $collation);
$mysql->exec('SET SESSION unique_checks=0');

$authors = $sqlite->query('SELECT name, kind, description FROM authors ORDER BY rowid');
$ab = [];
$ac = 0;
$authorChunk = 300;
$mysql->beginTransaction();
while ($a = $authors->fetch(PDO::FETCH_ASSOC)) {
    $ab[] = [$a['name'], $a['kind'], $a['description']];
    $ac++;
    if (count($ab) >= $authorChunk) {
        flushAuthorBatch($mysql, $ab);
        $ab = [];
    }
}
if ($ab !== []) {
    flushAuthorBatch($mysql, $ab);
}
$mysql->commit();
echo "作者迁入: {$ac}\n";

$tStats = microtime(true);
AuthorWallStats::rebuild($mysql);
WorkRandomPool::rebuild($mysql);
echo '聚合+随机池: ' . round(microtime(true) - $tStats, 1) . "s\n";

$totalSec = round(microtime(true) - $t0, 1);
$rate = $total > 0 ? (int) round($total / max(0.1, $totalSec)) : 0;
echo "完成，总耗时 {$totalSec}s（约 {$rate} 条/秒）。可选执行 sql/fulltext-ngram.sql。\n";
