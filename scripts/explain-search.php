<?php

declare(strict_types=1);

/**
 * 用法：php scripts/explain-search.php 日
 */
require dirname(__DIR__) . '/vendor/autoload.php';

use Poetry\Chinese;
use Poetry\Config;
use Poetry\Database;
use Poetry\PoetryRepository;

Config::load(dirname(__DIR__) . '/.env');

$q = $argv[1] ?? '日';
$kind = $argv[2] ?? 'all';

if (!Database::available()) {
    fwrite(STDERR, "DB unavailable\n");
    exit(1);
}

$pdo = Database::pdo();
$trad = Chinese::normalizeForSearch($q);
$termLen = mb_strlen($trad, 'UTF-8');
$booleanMode = $termLen < 2;
$match = $booleanMode ? '+' . $trad : $trad;
$ftCols = PoetryRepository::ftsColumnsFor($trad);
$modeSql = $booleanMode ? 'IN BOOLEAN MODE' : 'IN NATURAL LANGUAGE MODE';
$kindClause = $kind === 'all' ? '' : ' AND kind = :kind';

$sql = "EXPLAIN SELECT id FROM works WHERE MATCH{$ftCols} AGAINST (:m {$modeSql}){$kindClause} ORDER BY id LIMIT 15";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':m', $match);
if ($kind !== 'all') {
    $stmt->bindValue(':kind', $kind);
}
$stmt->execute();

$tier = mb_strlen($trad, 'UTF-8') <= 4 ? 'short(meta)' : 'long(trb)';
echo "query={$q} kind={$kind} tier={$tier} cols={$ftCols} boolean=" . ($booleanMode ? 'yes' : 'no') . "\n\n";
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    foreach ($row as $k => $v) {
        echo "{$k}\t{$v}\n";
    }
    echo "---\n";
}