<?php

declare(strict_types=1);

namespace Poetry;

final class Router
{
    public static function dispatch(string $method, string $path): void
    {
        $path = rtrim($path, '/') ?: '/';
        View::boot();
        $repo = new PoetryRepository();

        if ($method === 'GET' && $path === '/api/corpora') {
            if (!Database::available()) {
                Http::dbUnavailable();

                return;
            }
            $out = [];
            foreach (CorpusCatalog::all() as $c) {
                $item = $c;
                if (!$c['inDb']) {
                    $item['total'] = 0;
                    $out[] = $item;
                    continue;
                }
                $total = $repo->countWorksByCorpus($c['id']);
                $segments = $c['segments'] ?? null;
                if ($segments) {
                    $item['segments'] = array_map(
                        static fn (array $s) => [
                            ...$s,
                            'total' => $repo->countWorksByCorpus($c['id'], $s['kinds']),
                        ],
                        $segments
                    );
                }
                $item['total'] = $total;
                $out[] = View::locCorpusEntry($item);
            }
            Http::json(['corpora' => $out]);

            return;
        }

        if ($method === 'GET' && preg_match('#^/api/corpora/([^/]+)/works$#u', $path, $m)) {
            if (!Database::available()) {
                Http::dbUnavailable();

                return;
            }
            $id = rawurldecode($m[1]);
            $corpus = CorpusCatalog::byId($id);
            if (!$corpus) {
                Http::json(['error' => '未知典籍'], 404);

                return;
            }
            if (!$corpus['inDb']) {
                Http::json(['error' => '该典籍未入库'], 400);

                return;
            }
            $segment = $_GET['segment'] ?? null;
            $filter = CorpusCatalog::browseFilter($corpus, is_string($segment) ? $segment : null);
            if (!$filter) {
                Http::json(['error' => '无效分段'], 400);

                return;
            }
            $page = Http::parseInt($_GET['page'] ?? null, 1, 1, 60);
            $limit = Http::parseInt($_GET['limit'] ?? null, 20, 1, 30);
            $offset = ($page - 1) * $limit;
            $result = $repo->browseWorksByCorpus(
                $filter['corpus'],
                $filter['kinds'] ?? null,
                $limit,
                $offset
            );
            Http::json([
                'corpusId' => $id,
                'hits' => View::locHits($result['hits']),
                'total' => $result['total'],
                'page' => $page,
                'limit' => $limit,
            ]);

            return;
        }

        if ($method === 'GET' && $path === '/api/search') {
            $q = mb_substr(trim($_GET['q'] ?? ''), 0, 64, 'UTF-8');
            if ($q !== '' && !Chinese::hasChineseForSearch($q)) {
                Http::json(
                    ['error' => '请至少输入一个汉字后再检索', 'hits' => [], 'total' => 0],
                    400
                );

                return;
            }
            if (!Database::available()) {
                Http::dbUnavailable();

                return;
            }
            $kind = KindFilter::parse($_GET['kind'] ?? 'all');
            $limit = Http::parseInt($_GET['limit'] ?? null, 15, 1, 20);
            $browseMax = SearchTotalCache::browseMax();
            $maxPageByCap = (int) ceil(SearchTotalCache::cap() / $limit);
            $maxPageByBrowse = max(1, (int) ceil($browseMax / $limit));
            $maxPage = min(80, $maxPageByCap, $maxPageByBrowse);
            $page = Http::parseInt($_GET['page'] ?? null, 1, 1, max(1, $maxPage));
            $afterRaw = $_GET['after_id'] ?? null;
            $afterId = null;
            if (is_string($afterRaw) && $afterRaw !== '') {
                $afterId = WorkId::normalize($afterRaw);
                if ($afterId === null) {
                    Http::json(
                        ['error' => '无效的作品标识', 'hits' => [], 'total' => 0],
                        400
                    );

                    return;
                }
            }
            if (($page - 1) * $limit >= $browseMax) {
                Http::json(
                    [
                        'error' => '已超过分页上限',
                        'hits' => [],
                        'total' => 0,
                        'browseMax' => $browseMax,
                    ],
                    400
                );

                return;
            }
            $offset = ($afterId !== null && $page > 1) ? 0 : ($page - 1) * $limit;
            $result = $repo->searchWorks($q, $kind, $limit, $offset, $afterId);
            Http::json([
                'hits' => View::locHits($result['hits']),
                'total' => $result['total'],
                'totalCapped' => !empty($result['totalCapped']),
                'nextAfterId' => $result['nextAfterId'] ?? null,
                'page' => $page,
                'limit' => $limit,
                'browseMax' => $browseMax,
                'highlightTerms' => SearchHighlight::terms($q),
            ]);

            return;
        }

        if ($method === 'GET' && preg_match('#^/api/work/(.+)$#u', $path, $m)) {
            if (!Database::available()) {
                Http::dbUnavailable();

                return;
            }
            $id = WorkId::normalize($m[1]);
            if (!$id) {
                Http::json(['error' => '无效的作品标识'], 400);

                return;
            }
            $payload = $repo->getWorkReaderPayload($id);
            if (!$payload) {
                Http::json(['error' => '未找到作品'], 404);

                return;
            }
            Http::json(View::locReaderPayload($payload));

            return;
        }

        if ($method === 'GET' && $path === '/api/random') {
            if (!Database::available()) {
                Http::dbUnavailable();

                return;
            }
            $raw = $_GET['kind'] ?? null;
            $kind = KindFilter::isWorkKind(is_string($raw) ? $raw : null)
                ? strtolower((string) $raw)
                : null;
            $work = $repo->randomWork($kind);
            if (!$work) {
                Http::json(['error' => '未找到作品'], 404);

                return;
            }
            Http::json(View::locWork($work));

            return;
        }

        if ($method === 'GET' && $path === '/api/authors/wall') {
            if (!Database::available()) {
                Http::dbUnavailable();

                return;
            }
            $kind = KindFilter::parse($_GET['kind'] ?? 'all');
            $limit = Http::parseInt($_GET['limit'] ?? null, 100, 20, 120);
            $items = $repo->getAuthorWall($kind, $limit);
            $maxAge = (int) (Config::get('AUTHOR_WALL_HTTP_MAX_AGE', '3600') ?: 3600);
            $localized = array_map(
                static fn (array $row) => [
                    'author' => View::loc($row['author']),
                    'count' => $row['count'],
                ],
                $items
            );
            header('Vary: Cookie');
            Http::jsonPublic(['items' => $localized, 'kind' => $kind], max(60, $maxAge));

            return;
        }

        if ($method === 'GET' && $path === '/api/recommend/today') {
            if (!Database::available()) {
                Http::dbUnavailable();

                return;
            }
            $offset = Http::parseInt($_GET['offset'] ?? null, 0, 0, 200);
            $poemLimit = Http::parseInt($_GET['limit'] ?? null, 6, 3, 20);
            $calendar = SeasonCalendar::resolveToday();
            $cacheKey = RecommendTodayCache::key(
                (string) $calendar['solarYmd'],
                $offset,
                $poemLimit,
                View::variant()
            );
            $cached = RecommendTodayCache::get($cacheKey);
            if ($cached !== null) {
                header('Vary: Cookie');
                Http::jsonPublic($cached, RecommendTodayCache::httpMaxAge());

                return;
            }
            $engine = new RecommendToday($repo);
            $rec = $engine->recommend(null, 2, $poemLimit, $offset);
            $rec = $engine->enrichFeatured($rec);
            $payload = $engine->toApiPayload($rec, $offset);
            RecommendTodayCache::set($cacheKey, $payload);
            header('Vary: Cookie');
            Http::jsonPublic($payload, RecommendTodayCache::httpMaxAge());

            return;
        }

        if ($method === 'GET' && $path === '/') {
            require dirname(__DIR__) . '/views/home.php';

            return;
        }

        if ($method === 'GET' && $path === '/work' && isset($_GET['id']) && is_string($_GET['id'])) {
            $legacyId = WorkId::normalize($_GET['id']);
            if ($legacyId === null) {
                http_response_code(400);
                header('Content-Type: text/plain; charset=utf-8');
                echo '无效的作品标识';
                exit;
            }
            header('Location: /work/' . rawurlencode($legacyId), true, 301);
            exit;
        }

        if ($method === 'GET' && preg_match('#^/work/(.+)$#u', $path, $m)) {
            if (!Database::available()) {
                http_response_code(503);
                echo '数据库不可用';
                exit;
            }
            $id = WorkId::normalize(rawurldecode($m[1]));
            if (!$id) {
                http_response_code(404);
                echo '无效的作品标识';
                exit;
            }
            $payload = $repo->getWorkReaderPayload($id);
            if (!$payload) {
                http_response_code(404);
                echo '未找到作品';
                exit;
            }
            require dirname(__DIR__) . '/views/work.php';

            return;
        }

        if ($method === 'GET' && preg_match('#^/corpus/([^/]+)$#u', $path, $m)) {
            $corpusId = rawurldecode($m[1]);
            $meta = CorpusCatalog::byId($corpusId);
            if (!$meta) {
                http_response_code(404);
                echo '未知典籍';
                exit;
            }
            $corpus = $meta;
            if ($meta['inDb'] && Database::available()) {
                $corpus['total'] = $repo->countWorksByCorpus($meta['id']);
                if (!empty($meta['segments'])) {
                    $corpus['segments'] = array_map(
                        static fn (array $s) => [
                            ...$s,
                            'total' => $repo->countWorksByCorpus($meta['id'], $s['kinds']),
                        ],
                        $meta['segments']
                    );
                }
            } else {
                $corpus['total'] = 0;
            }
            require dirname(__DIR__) . '/views/corpus.php';

            return;
        }

        if ($method === 'GET' && $path === '/authors') {
            require dirname(__DIR__) . '/views/authors.php';

            return;
        }

        Http::json(['error' => 'Not Found'], 404);
    }
}
