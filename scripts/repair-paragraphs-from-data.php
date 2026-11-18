<?php

declare(strict_types=1);

/**
 * 修复导入时 String(对象) 导致的 "[object Object]" 正文。
 * 按 source_file + source_index 从 POETRY_DATA_PATH 重新归一化 paragraphs 与 body。
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use Poetry\Config;
use Poetry\Database;
use Poetry\PoemJson;
use Poetry\WorkParagraphs;

Config::load(dirname(__DIR__) . '/.env');

if (!Database::available()) {
    fwrite(STDERR, "Database unavailable.\n");
    exit(1);
}

$dataRoot = Config::resolveDataPath();
if (!is_dir($dataRoot)) {
    fwrite(STDERR, "Missing data root: {$dataRoot}\n");
    exit(1);
}

$pdo = Database::pdo();
$stmt = $pdo->query(
    "SELECT id, corpus, source_file, source_index, paragraphs FROM works
     WHERE paragraphs LIKE '%object Object%'"
);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if ($rows === []) {
    echo "No corrupted paragraphs found.\n";
    exit(0);
}

/** @var array<string, list<array<string, mixed>>> */
$cache = [];
$update = $pdo->prepare(
    'UPDATE works SET paragraphs = ?, body = ? WHERE id = ?'
);
$fixed = 0;
$failed = 0;

foreach ($rows as $row) {
    $rel = (string) $row['source_file'];
    $path = $dataRoot . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
    if (!is_file($path)) {
        $path = $dataRoot . DIRECTORY_SEPARATOR . $row['corpus'] . DIRECTORY_SEPARATOR . basename($rel);
    }
    if (!is_file($path)) {
        fwrite(STDERR, "missing file for {$row['id']}: {$rel}\n");
        ++$failed;
        continue;
    }
    if (!isset($cache[$path])) {
        try {
            $cache[$path] = PoemJson::flattenPoemCandidates(
                json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR)
            );
        } catch (Throwable $e) {
            fwrite(STDERR, "json error {$path}: {$e->getMessage()}\n");
            $cache[$path] = [];
        }
    }
    $index = (int) $row['source_index'];
    $item = $cache[$path][$index] ?? null;
    if (!is_array($item)) {
        fwrite(STDERR, "no item index {$index} in {$row['id']}\n");
        ++$failed;
        continue;
    }
    $norm = PoemJson::normalizePoem($item);
    $lines = $norm['paragraphs'] ?? [];
    if ($lines === [] || WorkParagraphs::linesCorrupted($lines)) {
        fwrite(STDERR, "still empty/corrupt: {$row['id']}\n");
        ++$failed;
        continue;
    }
    $body = WorkParagraphs::bodyFromLines($lines);
    $update->execute([
        json_encode($lines, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        $body,
        $row['id'],
    ]);
    ++$fixed;
    echo "fixed {$row['id']}\n";
}

echo "done fixed={$fixed} failed={$failed}\n";