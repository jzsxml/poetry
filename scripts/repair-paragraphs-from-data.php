<?php

declare(strict_types=1);

/**
 * 修复导入时 String(对象) 导致的 "[object Object]" 正文。
 * 按 source_file + source_index 从 data/ 重新归一化 paragraphs 与 body。
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use Poetry\Config;
use Poetry\Database;
use Poetry\WorkParagraphs;

Config::load(dirname(__DIR__) . '/.env');

if (!Database::available()) {
    fwrite(STDERR, "Database unavailable.\n");
    exit(1);
}

$dataRoot = dirname(__DIR__, 2) . '/data';
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

/**
 * @return list<array<string, mixed>>
 */
function flattenPoemCandidates(mixed $data): array
{
    $out = [];
    $isPoemLike = static function (array $o): bool {
        if (isset($o['paragraphs']) && is_array($o['paragraphs']) && $o['paragraphs'] !== []) {
            return true;
        }
        if (isset($o['para']) && is_array($o['para']) && $o['para'] !== []) {
            return true;
        }
        if (isset($o['content']) && is_array($o['content']) && $o['content'] !== []
            && is_string($o['content'][0] ?? null)) {
            return true;
        }

        return isset($o['content']) && is_string($o['content']) && trim($o['content']) !== '';
    };
    $walk = static function (mixed $node) use (&$walk, &$out, $isPoemLike): void {
        if (is_array($node)) {
            if (array_is_list($node)) {
                foreach ($node as $item) {
                    $walk($item);
                }

                return;
            }
        }
        if (!is_array($node)) {
            return;
        }
        if ($isPoemLike($node)) {
            $out[] = $node;

            return;
        }
        if (isset($node['content']) && is_array($node['content'])) {
            $walk($node['content']);
        }
        if (isset($node['chapters']) && is_array($node['chapters'])) {
            $walk($node['chapters']);
        }
    };
    $walk($data);

    return $out;
}

/** @param array<string, mixed> $raw */
function normalizeParagraphs(array $raw): array
{
    if (isset($raw['paragraphs']) && is_array($raw['paragraphs'])) {
        return WorkParagraphs::flattenList($raw['paragraphs']);
    }
    if (isset($raw['para']) && is_array($raw['para'])) {
        return WorkParagraphs::flattenList($raw['para']);
    }
    if (isset($raw['content']) && is_array($raw['content'])
        && is_string($raw['content'][0] ?? null)) {
        return WorkParagraphs::flattenList($raw['content']);
    }
    if (isset($raw['content']) && is_string($raw['content'])) {
        return [trim($raw['content'])];
    }

    return [];
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
    $path = $dataRoot . '/' . str_replace('\\', '/', $rel);
    if (!is_file($path)) {
        $path = $dataRoot . '/' . $row['corpus'] . '/' . basename($rel);
    }
    if (!is_file($path)) {
        fwrite(STDERR, "missing file for {$row['id']}: {$rel}\n");
        ++$failed;
        continue;
    }
    if (!isset($cache[$path])) {
        try {
            $cache[$path] = flattenPoemCandidates(
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
    $lines = normalizeParagraphs($item);
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