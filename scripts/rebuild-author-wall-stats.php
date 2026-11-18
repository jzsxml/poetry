<?php

declare(strict_types=1);

/**
 * 重建名人墙聚合与随机池（离线全表扫描，线上 O(1) 读取）。
 * 用法: composer run rebuild-author-wall
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Poetry\AuthorWallStats;
use Poetry\Config;
use Poetry\Database;
use Poetry\PoetryRepository;
use Poetry\RecommendTodayCache;
use Poetry\SeasonRecommendPool;
use Poetry\WorkRandomPool;

Config::load(dirname(__DIR__) . '/.env');

if (!Database::available()) {
    fwrite(STDERR, "数据库不可用\n");
    exit(1);
}

$pdo = Database::pdo();

$t0 = microtime(true);
AuthorWallStats::rebuild($pdo);
$sec = round(microtime(true) - $t0, 2);
$rows = (int) $pdo->query('SELECT COUNT(*) FROM author_wall_stats')->fetchColumn();
echo "author_wall_stats: {$rows} 行，耗时 {$sec}s\n";

$t1 = microtime(true);
WorkRandomPool::rebuild($pdo);
$sec2 = round(microtime(true) - $t1, 2);
$poolRows = (int) $pdo->query('SELECT COUNT(*) FROM random_work_pool')->fetchColumn();
echo "random_work_pool: {$poolRows} 行，耗时 {$sec2}s\n";

$t2 = microtime(true);
$repo = new PoetryRepository();
SeasonRecommendPool::rebuild($pdo, $repo);
$sec3 = round(microtime(true) - $t2, 2);
$seasonRows = (int) $pdo->query('SELECT COUNT(*) FROM season_recommend_pool')->fetchColumn();
RecommendTodayCache::clear();
echo "season_recommend_pool: {$seasonRows} 行，耗时 {$sec3}s（已清空荐诗文件缓存）\n";