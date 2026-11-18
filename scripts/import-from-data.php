<?php

declare(strict_types=1);

/**
 * 从 chinese-poetry 本地 JSON 直导入 MySQL（与 web/scripts/import.ts 规则对齐）
 * 用法: composer run import
 *
 * 需先 clone 数据到 POETRY_DATA_PATH（默认 ./data，不纳入 Git）
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Poetry\Config;
use Poetry\Database;
use Poetry\DatabaseBulk;
use Poetry\PoemJson;
use Poetry\WorkParagraphs;

Config::load(dirname(__DIR__) . '/.env');

if (!Database::available()) {
    fwrite(STDERR, "数据库不可用，请先配置 .env 并执行 sql/schema.sql\n");
    exit(1);
}

$dataRoot = Config::resolveDataPath();
if (!is_dir($dataRoot)) {
    fwrite(STDERR, "数据目录不存在: {$dataRoot}\n");
    fwrite(STDERR, "请在本项目根执行:\n");
    fwrite(STDERR, "  git clone --depth 1 https://github.com/chinese-poetry/chinese-poetry.git data\n");
    exit(1);
}

$rowsPerInsert = max(10, (int) (Config::get('MIGRATE_ROWS_PER_INSERT') ?: 120));
$commitEvery = max($rowsPerInsert, (int) (Config::get('MIGRATE_COMMIT_EVERY') ?: 6000));
$bodyMax = (int) (Config::get('MIGRATE_BODY_MAX') ?: 0);
$collation = Config::get('DB_COLLATION', 'utf8mb4_unicode_ci') ?? 'utf8mb4_unicode_ci';

$pdo = Database::pdo();

echo "数据: {$dataRoot}\n";
echo "MySQL: " . Config::get('DB_DATABASE') . "\n";
echo "批量: {$rowsPerInsert} 行/INSERT，每 {$commitEvery} 行提交\n";
if ($bodyMax > 0) {
    echo "MIGRATE_BODY_MAX={$bodyMax}：body 仅保留前 {$bodyMax} 字节\n";
}

$t0 = microtime(true);

$pdo->exec('SET SESSION foreign_key_checks=0');
$pdo->exec('SET SESSION unique_checks=0');
try {
    $pdo->exec('SET SESSION sql_log_bin=0');
} catch (PDOException) {
    /* 无 SUPER 权限时忽略 */
}
$pdo->exec('TRUNCATE TABLE works');
$pdo->exec('TRUNCATE TABLE authors');
$pdo->exec('SET SESSION foreign_key_checks=1');
$pdo->exec('SET SESSION unique_checks=1');

$batch = [];
$total = 0;
$sinceCommit = 0;
$pdo->beginTransaction();

foreach (PoemJson::CORPUS_NAMES as $corpus) {
    $corpusRoot = $dataRoot . DIRECTORY_SEPARATOR . $corpus;
    if (!is_dir($corpusRoot)) {
        echo "\n跳过（无目录）: {$corpus}\n";
        continue;
    }
    $files = PoemJson::walkJsonFiles($corpusRoot);
    echo "\n== {$corpus}（" . count($files) . " 个 JSON）==\n";
    $corpusCount = 0;

    foreach ($files as $filePath) {
        $rel = str_replace('\\', '/', substr($filePath, strlen($corpusRoot) + 1));
        $fileName = basename($filePath);
        $kind = PoemJson::kindForFile($corpus, $fileName);

        try {
            $raw = json_decode((string) file_get_contents($filePath), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            echo "\n跳过无效 JSON: {$rel}\n";
            continue;
        }

        $items = PoemJson::flattenPoemCandidates($raw);
        $fileCount = 0;

        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $norm = PoemJson::normalizePoem($item);
            if ($norm === null) {
                continue;
            }
            $id = PoemJson::workId($corpus, $rel, $index);
            $paragraphsJson = json_encode($norm['paragraphs'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $notesJson = json_encode($norm['notes'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $body = DatabaseBulk::truncateBody(
                WorkParagraphs::bodyFromLines($norm['paragraphs']),
                $bodyMax
            );

            $batch[] = [
                $id,
                $corpus,
                $kind,
                $norm['author'],
                $norm['title'],
                $norm['rhythmic'],
                $paragraphsJson,
                $notesJson,
                $body,
                $rel,
                $index,
            ];
            $total++;
            $fileCount++;
            $corpusCount++;

            if (count($batch) >= $rowsPerInsert) {
                $n = count($batch);
                DatabaseBulk::flushWorkBatch($pdo, $batch);
                $batch = [];
                $sinceCommit += $n;
                if ($sinceCommit >= $commitEvery) {
                    $pdo->commit();
                    $pdo->beginTransaction();
                    $sinceCommit = 0;
                    $sec = round(microtime(true) - $t0, 1);
                    echo "\r  …累计 {$total} 篇 ({$sec}s)";
                }
            }
        }

        if ($fileCount > 0) {
            echo "\r  {$rel} +{$fileCount}  累计 {$total}";
        }
    }
    echo "\n  {$corpus} 完成，共 {$corpusCount} 篇\n";
}

if ($batch !== []) {
    DatabaseBulk::flushWorkBatch($pdo, $batch);
}
$pdo->commit();

$sec = round(microtime(true) - $t0, 1);
echo "\n作品导入: {$total} 篇 ({$sec}s)\n";

$authorRows = PoemJson::loadAuthorRows($dataRoot);
$chunk = 300;
$pdo->beginTransaction();
for ($i = 0, $c = count($authorRows); $i < $c; $i += $chunk) {
    DatabaseBulk::flushAuthorBatch($pdo, array_slice($authorRows, $i, $chunk));
}
$pdo->commit();
echo '作者导入: ' . count($authorRows) . "\n";

$totalSec = round(microtime(true) - $t0, 1);
echo "\n全部完成，总耗时 {$totalSec}s。\n";
echo "下一步:\n";
echo "  composer run ensure-search-indexes\n";
echo "  composer run rebuild-author-wall\n";