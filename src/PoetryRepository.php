<?php

declare(strict_types=1);

namespace Poetry;

use PDO;

final class PoetryRepository
{
    private const SEARCH_PREVIEW_MAX = 72;
    private const SEARCH_QUERY_MAX_LEN = 64;
    /** 1～4 字走 meta 全文（author+title+rhythmic），≥5 字走 trb（title+rhythmic+body，不含 author 减轻 2G 机负担） */
    private const SEARCH_SHORT_MAX_LEN = 4;
    private const AUTHOR_MORE_LIMIT = 8;
    /** 列表查询从 body 截取的字节上限（避免读 paragraphs JSON） */
    private const SEARCH_BODY_SNIP = 220;
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::pdo();
    }

    /** @return array<string, mixed>|null */
    public function getWorkById(string $id): ?array
    {
        if (!WorkId::isValid($id)) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM works WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        return $this->rowToWork($row);
    }

    /** @return array<string, mixed>|null */
    public function getWorkReaderPayload(string $id): ?array
    {
        $work = $this->getWorkById($id);
        if (!$work) {
            return null;
        }
        $bio = $this->getAuthorBio($work['author'], $work['kind']);
        $more = $this->getWorksBySameAuthor($work['author'], $work['id']);
        $adj = $this->getAdjacentWorksInCorpus($id);

        return [
            'work' => $work,
            'bio' => $bio,
            'authorMore' => $more['hits'],
            'authorMoreCount' => $more['moreCount'],
            'prev' => $adj['prev'],
            'next' => $adj['next'],
        ];
    }

    public function getAuthorBio(string $name, string $kind): ?string
    {
        $stmt = $this->db->prepare(
            'SELECT description FROM authors WHERE name = ? AND kind = ?'
        );
        $stmt->execute([$name, $kind]);
        $row = $stmt->fetch();

        return $row ? (string) $row['description'] : null;
    }

    /** @return array{hits: list<array<string, mixed>>, total: int, totalCapped?: bool} */
    public function searchWorks(
        string $query,
        string $kind,
        int $limit,
        int $offset,
        ?string $afterId = null
    ): array {
        $q = mb_substr(trim($query), 0, self::SEARCH_QUERY_MAX_LEN, 'UTF-8');
        if ($q === '') {
            return ['hits' => [], 'total' => 0];
        }
        $cleaned = trim(preg_replace('/[\'"*\\\\]/', ' ', $q) ?? '');
        if ($cleaned === '') {
            return ['hits' => [], 'total' => 0];
        }

        if (self::shouldUseAuthorIndexSearch($cleaned)) {
            $fast = $this->searchWorksAuthor($cleaned, $kind, $limit, $offset, $cleaned);
            if ($fast['hits'] !== [] || $fast['total'] > 0) {
                return $fast;
            }
        }

        $tradQuery = Chinese::normalizeForSearch($cleaned);
        $repeatHead = Chinese::isRepeatedCharHeadSearch($cleaned);

        if (!$repeatHead) {
            $fts = $this->searchWorksFts($tradQuery, $kind, $limit, $offset, $cleaned, $afterId);
            if ($fts !== null) {
                return $fts;
            }
        }

        return $this->searchWorksLike($cleaned, $kind, $limit, $offset, $cleaned, $afterId);
    }

    /**
     * 节令荐诗：只做列表查询、跳过 COUNT，标题/词牌优先排序。
     *
     * @return array{hits: list<array<string, mixed>>, total: int}
     */
    public function searchThemeKeyword(
        string $keyword,
        int $limit,
        int $offset
    ): array {
        $q = mb_substr(trim($keyword), 0, 24, 'UTF-8');
        if ($q === '') {
            return ['hits' => [], 'total' => 0];
        }
        $trad = Chinese::toTraditional($q);
        $like = '%' . $trad . '%';
        $likePlain = '%' . $q . '%';
        $fetchLimit = min(max($limit * 2, $limit + 8), 64);
        $term = $trad !== '' ? $trad : $q;
        $snip = self::SEARCH_BODY_SNIP;

        $listSql = "
            SELECT id, kind, author, title, rhythmic, LEFT(body, {$snip}) AS body_snip
            FROM works
            WHERE MATCH(author, title, rhythmic, body) AGAINST (:match IN NATURAL LANGUAGE MODE)
            ORDER BY
              (title LIKE :like OR title LIKE :likePlain) DESC,
              (rhythmic LIKE :like OR rhythmic LIKE :likePlain) DESC,
              id
            LIMIT :lim OFFSET :off
        ";

        try {
            $stmt = $this->db->prepare($listSql);
            $stmt->bindValue(':match', $term);
            $stmt->bindValue(':like', $like);
            $stmt->bindValue(':likePlain', $likePlain);
            $stmt->bindValue(':lim', $fetchLimit, PDO::PARAM_INT);
            $stmt->bindValue(':off', max(0, $offset), PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            $hits = $this->dedupeSearchHitsForPage($this->mapRowsToHits($rows), $limit);

            return ['hits' => $hits, 'total' => count($hits)];
        } catch (\PDOException $e) {
            // 无 FULLTEXT 或查询失败时回退标题/词牌 LIKE（仍不 COUNT），但必须留痕
            self::logFtsFallback($e, $term, 'theme');
        }

        $listSql = "
            SELECT id, kind, author, title, rhythmic, paragraphs FROM works
            WHERE title LIKE :like OR title LIKE :likePlain
               OR rhythmic LIKE :like OR rhythmic LIKE :likePlain
            ORDER BY
              (title LIKE :like OR title LIKE :likePlain) DESC,
              id
            LIMIT :lim OFFSET :off
        ";
        $stmt = $this->db->prepare($listSql);
        $stmt->bindValue(':like', $like);
        $stmt->bindValue(':likePlain', $likePlain);
        $stmt->bindValue(':lim', $fetchLimit, PDO::PARAM_INT);
        $stmt->bindValue(':off', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $hits = $this->dedupeSearchHitsForPage($this->mapRowsToHits($rows), $limit);

        return ['hits' => $hits, 'total' => count($hits)];
    }

    /**
     * @param list<string> $ids
     * @return list<array<string, mixed>>
     */
    public function getSearchHitsByIds(array $ids): array
    {
        $ids = array_values(array_filter($ids, static fn ($id) => is_string($id) && WorkId::isValid($id)));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT id, kind, author, title, rhythmic, paragraphs FROM works
             WHERE id IN ({$placeholders})"
        );
        $stmt->execute($ids);
        $rows = $stmt->fetchAll();
        $byId = [];
        foreach ($this->mapRowsToHits($rows) as $hit) {
            $byId[$hit['id']] = $hit;
        }
        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }

        return $ordered;
    }

    /**
     * @param list<array<string, mixed>> $hits
     * @param callable(): array{total: int, capped: bool} $countFn
     * @return array{0: int, 1: bool}
     */
    private function resolveSearchTotalAfterList(
        string $cacheKey,
        int $offset,
        array $hits,
        int $limit,
        callable $countFn
    ): array {
        if ($offset > 0) {
            $cached = SearchTotalCache::get($cacheKey);
            if ($cached !== null) {
                return [$cached['total'], $cached['capped']];
            }
        }
        if (count($hits) < $limit && $offset === 0) {
            $total = count($hits);
            SearchTotalCache::set($cacheKey, $total, false);

            return [$total, false];
        }
        if (count($hits) < $limit && $offset > 0) {
            $cached = SearchTotalCache::get($cacheKey);
            if ($cached !== null) {
                return [$cached['total'], $cached['capped']];
            }
            $total = $offset + count($hits);
            SearchTotalCache::set($cacheKey, $total, false);

            return [$total, false];
        }
        $result = $countFn();
        SearchTotalCache::set($cacheKey, $result['total'], $result['capped']);

        return [$result['total'], $result['capped']];
    }

    /**
     * @return array{total: int, capped: bool}
     */
    private function resolveSearchTotal(
        string $cacheKey,
        int $offset,
        callable $countFn
    ): array {
        if ($offset > 0) {
            $cached = SearchTotalCache::get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }
        $result = $countFn();
        SearchTotalCache::set($cacheKey, $result['total'], $result['capped']);

        return $result;
    }

    /**
     * @param callable(\PDOStatement): void $bind
     * @return array{total: int, capped: bool}
     */
    private function countSearchCapped(string $fromWhereSql, callable $bind): array
    {
        $cap = SearchTotalCache::cap();
        $sql = "SELECT COUNT(*) AS c FROM (SELECT 1 FROM works {$fromWhereSql} LIMIT {$cap}) AS capped";
        $stmt = $this->db->prepare($sql);
        $bind($stmt);
        $stmt->execute();
        $c = (int) ($stmt->fetchColumn() ?: 0);

        return [
            'total' => $c,
            'capped' => $c >= $cap,
        ];
    }

    /**
     * @param array<string, mixed> $extra
     * @return array{hits: list<array<string, mixed>>, total: int, totalCapped: bool}
     */
    private function packSearchResult(
        array $hits,
        int $total,
        bool $totalCapped
    ): array {
        $nextAfterId = $hits !== [] ? (string) end($hits)['id'] : null;

        return [
            'hits' => $hits,
            'total' => $total,
            'totalCapped' => $totalCapped,
            'nextAfterId' => $nextAfterId,
        ];
    }

    /** @return array{hits: list<array<string, mixed>>, total: int, totalCapped?: bool}|null */
    private function searchWorksFts(
        string $cleaned,
        string $kind,
        int $limit,
        int $offset,
        string $cacheQuery,
        ?string $afterId = null
    ): ?array {
        $term = $cleaned;
        if (str_contains($cleaned, ' ')) {
            $parts = array_filter(preg_split('/\s+/u', $cleaned) ?: []);
            $term = implode(' ', $parts);
        }
        $tier = self::ftsTierForTerm($term);
        $booleanMode = false;
        $matchParam = self::ftsMatchBinding($term, $booleanMode);

        $kindClause = $kind === 'all' ? '' : ' AND kind = :kind';
        $afterClause = ($afterId !== null && $afterId !== '') ? ' AND id > :after_id' : '';
        $useCursor = $afterClause !== '';
        $ftCols = $tier['cols'];
        $modeLabel = $booleanMode ? 'BOOLEAN MODE' : 'NATURAL LANGUAGE MODE';
        $matchSql = "MATCH{$ftCols} AGAINST (:match IN {$modeLabel})";
        $relevanceSort = !$useCursor && $offset === 0 && self::isPhraseLineQuery($term);
        $scoreSelect = $relevanceSort
            ? ", MATCH{$ftCols} AGAINST (:match IN {$modeLabel}) AS _fts_score"
            : '';
        $orderBy = $relevanceSort ? 'ORDER BY _fts_score DESC, id' : 'ORDER BY id';
        $snip = self::SEARCH_BODY_SNIP;

        try {
            $cacheKey = SearchTotalCache::key($cacheQuery, $kind);
            $fetchLimit = $this->searchPageOverfetchLimit($limit);
            $params = [':match' => $matchParam, ':lim' => $fetchLimit];
            if (!$useCursor) {
                $params[':off'] = $offset;
            }
            if ($useCursor) {
                $params[':after_id'] = $afterId;
            }
            if ($kind !== 'all') {
                $params[':kind'] = $kind;
            }
            $phraseLiteralSql = self::phraseLiteralWhereSql($term, $params);
            $listSql = "
            SELECT id, kind, author, title, rhythmic, LEFT(body, {$snip}) AS body_snip{$scoreSelect}
            FROM works
            WHERE {$matchSql}{$phraseLiteralSql}
            {$kindClause}{$afterClause}
            {$orderBy}
            LIMIT :lim" . ($useCursor ? '' : ' OFFSET :off') . '
        ';

            $stmt = $this->db->prepare($listSql);
            foreach ($params as $k => $v) {
                $type = ($k === ':lim' || $k === ':off') ? PDO::PARAM_INT : PDO::PARAM_STR;
                $stmt->bindValue($k, $v, $type);
            }
            $stmt->execute();
            $rows = $stmt->fetchAll();
            $hits = $this->dedupeSearchHitsForPage(
                $this->mapRowsToHits($rows),
                $limit
            );
            if ($hits === [] && $offset === 0) {
                return null;
            }
            if ($hits === []) {
                $cached = SearchTotalCache::get($cacheKey);

                return $this->packSearchResult(
                    [],
                    $cached['total'] ?? 0,
                    $cached['capped'] ?? false
                );
            }

            [$total, $capped] = $this->resolveSearchTotalAfterList(
                $cacheKey,
                $useCursor ? 1 : $offset,
                $hits,
                $limit,
                function () use ($matchSql, $phraseLiteralSql, $kindClause, $params): array {
                    $fromWhere = "WHERE {$matchSql}{$phraseLiteralSql}{$kindClause}";

                    return $this->countSearchCapped(
                        $fromWhere,
                        static function (\PDOStatement $stmt) use ($params): void {
                            foreach ($params as $k => $v) {
                                if ($k === ':lim' || $k === ':off') {
                                    continue;
                                }
                                $stmt->bindValue($k, $v);
                            }
                        }
                    );
                }
            );

            return $this->packSearchResult($hits, $total, $capped);
        } catch (\PDOException $e) {
            self::logFtsFallback($e, $term, $kind);

            return null;
        }
    }

    /**
     * FTS 失败会静默降级成 LIKE 全表扫描，线上只表现为「某些关键词一查就崩」。
     * 必须留痕，否则根本看不出是缺索引。
     */
    private static function logFtsFallback(\PDOException $e, string $term, string $kind): void
    {
        $errno = (int) ($e->errorInfo[1] ?? 0);
        $hint = $errno === 1191
            ? '缺少与该列清单完全一致的 FULLTEXT 索引（短档 ft_works_meta / 长档 ft_works_trb，执行 composer run ensure-search-indexes）'
            : 'FTS 执行失败';
        error_log(sprintf(
            '[poetry-search] %s: term="%s" kind=%s errno=%d msg=%s',
            $hint,
            $term,
            $kind,
            $errno,
            $e->getMessage()
        ));
    }

    /**
     * @return array{cols: string, booleanMode: bool, shortTier: bool}
     */
    private static function ftsTierForTerm(string $term): array
    {
        $termLen = mb_strlen($term, 'UTF-8');
        if ($termLen <= self::SEARCH_SHORT_MAX_LEN) {
            return [
                'cols' => '(author, title, rhythmic)',
                'booleanMode' => $termLen < 2,
                'shortTier' => true,
            ];
        }

        return [
            'cols' => '(title, rhythmic, body)',
            'booleanMode' => false,
            'shortTier' => false,
        ];
    }

    /** 诊断脚本复用：返回该词走哪套 MATCH 列清单（避免两处各写一遍而失同步） */
    public static function ftsColumnsFor(string $term): string
    {
        return self::ftsTierForTerm($term)['cols'];
    }

    /** 连续汉字、无空格：篇名短语（相关度排序 + LIKE 校验） */
    private static function isPhraseLineQuery(string $term): bool
    {
        if (preg_match('/\s/u', $term)) {
            return false;
        }
        $len = mb_strlen($term, 'UTF-8');

        return $len >= 4 && $len <= 48 && (bool) preg_match('/^\p{Han}+$/u', $term);
    }

    /**
     * @param-out bool $booleanMode
     */
    private static function ftsMatchBinding(string $term, bool &$booleanMode): string
    {
        $tier = self::ftsTierForTerm($term);
        $sanitized = preg_replace('/[+\-><()~*"@]+/', '', $term) ?? $term;
        if ($tier['booleanMode']) {
            $booleanMode = true;

            return '+' . $sanitized;
        }
        if (self::isPhraseLineQuery($term)) {
            // ngram 下 BOOLEAN 引号常无结果；用 NATURAL + phraseLiteralWhereSql 保序。
            $booleanMode = false;

            return $sanitized;
        }
        $booleanMode = false;

        return $term;
    }

    /**
     * ngram FTS 的 BOOLEAN 引号并不保证连续子串；诗句短语须再按原文 LIKE 校验。
     *
     * @param array<string, mixed> $params
     */
    private static function phraseLiteralWhereSql(string $term, array &$params): string
    {
        if (!self::isPhraseLineQuery($term)) {
            return '';
        }
        $params[':phr_trad'] = '%' . $term . '%';
        $params[':phr_simp'] = '%' . Chinese::toSimplified($term) . '%';

        return ' AND (
            body LIKE :phr_trad OR body LIKE :phr_simp OR
            title LIKE :phr_trad OR title LIKE :phr_simp OR
            rhythmic LIKE :phr_trad OR rhythmic LIKE :phr_simp OR
            author LIKE :phr_trad OR author LIKE :phr_simp
        )';
    }

    private static function shouldUseAuthorIndexSearch(string $q): bool
    {
        $len = mb_strlen($q, 'UTF-8');
        if ($len < 2 || $len > 12) {
            return false;
        }

        return (bool) preg_match('/^\p{Han}+$/u', $q);
    }

    /** 短纯汉字：优先走 author 列前缀匹配（可走 idx_works_author） */
    /** @return array{hits: list<array<string, mixed>>, total: int, totalCapped: bool} */
    private function searchWorksAuthor(
        string $q,
        string $kind,
        int $limit,
        int $offset,
        string $cacheQuery
    ): array {
        $trad = Chinese::toTraditional($q);
        $prefixTrad = $trad . '%';
        $prefixPlain = $q . '%';
        $kindClause = $kind === 'all' ? '' : ' AND kind = ?';
        $snip = self::SEARCH_BODY_SNIP;

        $where = '(author = ? OR author = ? OR author LIKE ? OR author LIKE ?)';
        $listSql = "
            SELECT id, kind, author, title, rhythmic, LEFT(body, {$snip}) AS body_snip FROM works
            WHERE {$where}{$kindClause}
            ORDER BY author, title, source_file, source_index
            LIMIT ? OFFSET ?
        ";

        $bind = static function (\PDOStatement $stmt, string $k) use ($trad, $q, $prefixTrad, $prefixPlain, $kind): void {
            $stmt->bindValue(1, $trad);
            $stmt->bindValue(2, $q);
            $stmt->bindValue(3, $prefixTrad);
            $stmt->bindValue(4, $prefixPlain);
            $pos = 5;
            if ($k !== 'all') {
                $stmt->bindValue($pos++, $k);
            }
        };

        $fetchLimit = $this->searchPageOverfetchLimit($limit);
        $stmt = $this->db->prepare($listSql);
        $bind($stmt, $kind);
        $pos = $kind === 'all' ? 5 : 6;
        $stmt->bindValue($pos++, $fetchLimit, PDO::PARAM_INT);
        $stmt->bindValue($pos, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $hits = $this->dedupeSearchHitsForPage($this->mapRowsToHits($rows), $limit);
        if ($hits === [] && $offset > 0) {
            $cacheKey = SearchTotalCache::key($cacheQuery, $kind) . ':author';
            $cached = SearchTotalCache::get($cacheKey);

            return $this->packSearchResult(
                [],
                $cached['total'] ?? 0,
                $cached['capped'] ?? false
            );
        }
        if ($hits === []) {
            return $this->packSearchResult([], 0, false);
        }

        $cacheKey = SearchTotalCache::key($cacheQuery, $kind) . ':author';
        [$total, $capped] = $this->resolveSearchTotalAfterList(
            $cacheKey,
            $offset,
            $hits,
            $limit,
            function () use ($where, $kindClause, $bind, $kind): array {
                $fromWhere = "WHERE {$where}{$kindClause}";

                return $this->countSearchCapped(
                    $fromWhere,
                    function (\PDOStatement $stmt) use ($bind, $kind): void {
                        $bind($stmt, $kind);
                    }
                );
            }
        );

        return $this->packSearchResult($hits, $total, $capped);
    }

    /** @return array{hits: list<array<string, mixed>>, total: int, totalCapped: bool} */
    private function searchWorksLike(
        string $q,
        string $kind,
        int $limit,
        int $offset,
        string $cacheQuery,
        ?string $afterId = null
    ): array {
        $trad = Chinese::toTraditional($q);
        $like = '%' . $trad . '%';
        $likePlain = '%' . $q . '%';

        $longLike = mb_strlen($q, 'UTF-8') > self::SEARCH_SHORT_MAX_LEN;
        $rhythmicLike = $longLike
            ? ' OR rhythmic LIKE :like OR rhythmic LIKE :likePlain'
            : '';
        $where = "
            author LIKE :like OR author LIKE :likePlain OR
            title LIKE :like OR title LIKE :likePlain{$rhythmicLike}
        ";
        $kindClause = $kind === 'all' ? '' : ' AND kind = :kind';

        $useCursor = $afterId !== null && $afterId !== '';
        $afterClause = $useCursor ? ' AND id > :after_id' : '';
        $fromWhere = "WHERE ({$where}){$kindClause}{$afterClause}";
        $snip = self::SEARCH_BODY_SNIP;
        $listSql = "
            SELECT id, kind, author, title, rhythmic, LEFT(body, {$snip}) AS body_snip FROM works
            {$fromWhere}
            ORDER BY
              CASE
                WHEN title LIKE :like OR title LIKE :likePlain THEN 1
                WHEN rhythmic LIKE :like OR rhythmic LIKE :likePlain THEN 2
                WHEN author LIKE :like OR author LIKE :likePlain THEN 3
                ELSE 4
              END,
              author,
              title,
              id
            LIMIT :lim" . ($useCursor ? '' : ' OFFSET :off') . '
        ';

        $fetchLimit = $this->searchPageOverfetchLimit($limit);
        $params = [
            ':like' => $like,
            ':likePlain' => $likePlain,
            ':lim' => $fetchLimit,
        ];
        if (!$useCursor) {
            $params[':off'] = $offset;
        }
        if ($useCursor) {
            $params[':after_id'] = $afterId;
        }
        if ($kind !== 'all') {
            $params[':kind'] = $kind;
        }

        $cacheKey = SearchTotalCache::key($cacheQuery, $kind);
        try {
            $stmt = $this->db->prepare($listSql);
            $this->bindParams($stmt, $params, true);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            $hits = $this->dedupeSearchHitsForPage($this->mapRowsToHits($rows), $limit);

            [$total, $capped] = $this->resolveSearchTotalAfterList(
                $cacheKey,
                $useCursor ? 1 : $offset,
                $hits,
                $limit,
                function () use ($fromWhere, $params): array {
                    return $this->countSearchCapped(
                        $fromWhere,
                        function (\PDOStatement $stmt) use ($params): void {
                            $this->bindParams($stmt, $params, false);
                        }
                    );
                }
            );
        } catch (\PDOException $e) {
            if (!self::isStatementTimeout($e)) {
                throw $e;
            }
            error_log(sprintf(
                '[poetry-search] LIKE 兜底扫描超时，已放弃本次检索: term="%s" kind=%s',
                $cacheQuery,
                $kind
            ));

            return $this->packSearchResult([], 0, false);
        }

        return $this->packSearchResult($hits, $total, $capped);
    }

    private static function isStatementTimeout(\PDOException $e): bool
    {
        $errno = (int) ($e->errorInfo[1] ?? 0);

        // 3024=MySQL max_execution_time，1969=MariaDB max_statement_time
        return $errno === 3024 || $errno === 1969;
    }

    /** @param array<string, int|string> $params */
    private function bindParams(\PDOStatement $stmt, array $params, bool $withLimit): void
    {
        foreach ($params as $k => $v) {
            if (!$withLimit && ($k === ':lim' || $k === ':off')) {
                continue;
            }
            $type = ($k === ':lim' || $k === ':off') ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($k, $v, $type);
        }
    }

    public function countWorksByCorpus(string $corpus, ?array $kinds = null): int
    {
        if ($kinds !== null && $kinds !== []) {
            $placeholders = implode(',', array_fill(0, count($kinds), '?'));
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) AS c FROM works WHERE corpus = ? AND kind IN ({$placeholders})"
            );
            $stmt->execute([$corpus, ...$kinds]);

            return (int) ($stmt->fetchColumn() ?: 0);
        }
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS c FROM works WHERE corpus = ?'
        );
        $stmt->execute([$corpus]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /** @return array{hits: list<array<string, mixed>>, total: int} */
    public function browseWorksByCorpus(
        string $corpus,
        ?array $kinds,
        int $limit,
        int $offset
    ): array {
        $total = $this->countWorksByCorpus($corpus, $kinds);
        $kindClause = '';
        if ($kinds !== null && $kinds !== []) {
            if (count($kinds) === 1) {
                $kindClause = ' AND kind = ?';
            } else {
                $placeholders = implode(',', array_fill(0, count($kinds), '?'));
                $kindClause = " AND kind IN ({$placeholders})";
            }
        }
        $sql = "
            SELECT id, kind, author, title, rhythmic, paragraphs FROM works
            WHERE corpus = ?{$kindClause}
            ORDER BY source_file, source_index
            LIMIT ? OFFSET ?
        ";
        $stmt = $this->db->prepare($sql);
        $pos = 1;
        $stmt->bindValue($pos++, $corpus);
        if ($kinds !== null && $kinds !== []) {
            foreach ($kinds as $k) {
                $stmt->bindValue($pos++, $k);
            }
        }
        $stmt->bindValue($pos++, $limit, PDO::PARAM_INT);
        $stmt->bindValue($pos++, $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'hits' => $this->mapRowsToHits($stmt->fetchAll()),
            'total' => $total,
        ];
    }

    /** @return array{hits: list<array<string, mixed>>, moreCount: int} */
    public function getWorksBySameAuthor(
        string $author,
        string $excludeId,
        int $limit = self::AUTHOR_MORE_LIMIT
    ): array {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS c FROM works WHERE author = ? AND id != ?'
        );
        $stmt->execute([$author, $excludeId]);
        $moreCount = (int) ($stmt->fetchColumn() ?: 0);
        if ($moreCount === 0) {
            return ['hits' => [], 'moreCount' => 0];
        }
        $stmt = $this->db->prepare(
            'SELECT id, kind, author, title, rhythmic, paragraphs FROM works
             WHERE author = ? AND id != ?
             ORDER BY RAND()
             LIMIT ?'
        );
        $stmt->bindValue(1, $author);
        $stmt->bindValue(2, $excludeId);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'hits' => $this->mapRowsToHits($stmt->fetchAll()),
            'moreCount' => $moreCount,
        ];
    }

    /** @return list<array{author: string, count: int}> */
    public function getAuthorWall(string $kind, int $limit): array
    {
        $cap = min(max($limit, 20), 200);
        $cached = AuthorWallCache::get($kind, $cap);
        if ($cached !== null) {
            return $cached;
        }

        $items = AuthorWallStats::isReady($this->db)
            ? $this->fetchAuthorWallFromStats($kind, $cap)
            : $this->fetchAuthorWallFromWorks($kind, $cap);

        AuthorWallCache::set($kind, $cap, $items);

        return $items;
    }

    /** @return list<array{author: string, count: int}> */
    private function fetchAuthorWallFromStats(string $kind, int $cap): array
    {
        $statsKind = $kind === 'all' ? AuthorWallStats::KIND_ALL : $kind;
        $stmt = $this->db->prepare(
            'SELECT author, work_count AS c FROM author_wall_stats
             WHERE kind = ?
             ORDER BY work_count DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $statsKind);
        $stmt->bindValue(2, $cap, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return array_map(
            static fn (array $r) => [
                'author' => $r['author'],
                'count' => (int) $r['c'],
            ],
            $rows
        );
    }

    /** @return list<array{author: string, count: int}> */
    private function fetchAuthorWallFromWorks(string $kind, int $cap): array
    {
        if ($kind === 'all') {
            $stmt = $this->db->prepare(
                "SELECT author, COUNT(*) AS c FROM works
                 WHERE author IS NOT NULL AND TRIM(author) != ''
                 GROUP BY author ORDER BY c DESC LIMIT ?"
            );
            $stmt->bindValue(1, $cap, PDO::PARAM_INT);
        } else {
            $stmt = $this->db->prepare(
                "SELECT author, COUNT(*) AS c FROM works
                 WHERE kind = ? AND author IS NOT NULL AND TRIM(author) != ''
                 GROUP BY author ORDER BY c DESC LIMIT ?"
            );
            $stmt->bindValue(1, $kind);
            $stmt->bindValue(2, $cap, PDO::PARAM_INT);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return array_map(
            static fn (array $r) => [
                'author' => $r['author'],
                'count' => (int) $r['c'],
            ],
            $rows
        );
    }

    /** @return array<string, mixed>|null */
    public function randomWork(?string $kind): ?array
    {
        if (WorkRandomPool::isReady($this->db)) {
            $id = WorkRandomPool::pickWorkId($this->db, $kind);
            if ($id !== null) {
                return $this->getWorkById($id);
            }
        }

        $id = $this->randomWorkIdFallback($kind);
        if ($id !== null) {
            return $this->getWorkById($id);
        }

        return null;
    }

    private function randomWorkIdFallback(?string $kind): ?string
    {
        $cap = (int) (Config::get('RANDOM_FALLBACK_SAMPLE', '2048') ?: 2048);
        $cap = max(256, min(8192, $cap));
        if ($kind !== null) {
            $stmt = $this->db->prepare(
                'SELECT id FROM works WHERE kind = ? ORDER BY id LIMIT ?'
            );
            $stmt->bindValue(1, $kind);
            $stmt->bindValue(2, $cap, PDO::PARAM_INT);
        } else {
            $stmt = $this->db->prepare(
                'SELECT id FROM works ORDER BY id LIMIT ?'
            );
            $stmt->bindValue(1, $cap, PDO::PARAM_INT);
        }
        $stmt->execute();
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if ($ids === []) {
            return null;
        }

        $pick = $ids[random_int(0, count($ids) - 1)];

        return is_string($pick) && $pick !== '' ? $pick : null;
    }

    /** @return array{prev: ?array<string, string>, next: ?array<string, string>} */
    public function getAdjacentWorksInCorpus(string $id): array
    {
        if (!WorkId::isValid($id)) {
            return ['prev' => null, 'next' => null];
        }
        $stmt = $this->db->prepare(
            'SELECT corpus, source_file, source_index FROM works WHERE id = ?'
        );
        $stmt->execute([$id]);
        $cur = $stmt->fetch();
        if (!$cur) {
            return ['prev' => null, 'next' => null];
        }

        $stmt = $this->db->prepare(
            'SELECT id, author, title, rhythmic FROM works
             WHERE corpus = ? AND (
               source_file < ? OR (source_file = ? AND source_index < ?)
             )
             ORDER BY source_file DESC, source_index DESC
             LIMIT 1'
        );
        $stmt->execute([
            $cur['corpus'],
            $cur['source_file'],
            $cur['source_file'],
            $cur['source_index'],
        ]);
        $prev = $stmt->fetch();

        $stmt = $this->db->prepare(
            'SELECT id, author, title, rhythmic FROM works
             WHERE corpus = ? AND (
               source_file > ? OR (source_file = ? AND source_index > ?)
             )
             ORDER BY source_file ASC, source_index ASC
             LIMIT 1'
        );
        $stmt->execute([
            $cur['corpus'],
            $cur['source_file'],
            $cur['source_file'],
            $cur['source_index'],
        ]);
        $next = $stmt->fetch();

        return [
            'prev' => $prev ? $this->rowToNav($prev) : null,
            'next' => $next ? $this->rowToNav($next) : null,
        ];
    }

    /** @return list<string> */
    private static function decodeStringListJson(string $json): array
    {
        try {
            $raw = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            if (is_string($item) && trim($item) !== '') {
                $out[] = $item;
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $row */
    private function rowToWork(array $row): array
    {
        return [
            'id' => $row['id'],
            'corpus' => $row['corpus'],
            'kind' => $row['kind'],
            'author' => $row['author'],
            'title' => Chinese::stripUnpairedBookTitleMarks((string) $row['title']),
            'rhythmic' => Chinese::stripUnpairedBookTitleMarks((string) $row['rhythmic']),
            'paragraphs' => WorkParagraphs::flattenList(
                json_decode((string) $row['paragraphs'], true, 512, JSON_THROW_ON_ERROR) ?: []
            ),
            'notes' => self::decodeStringListJson((string) $row['notes']),
        ];
    }

    /** @param array<string, mixed> $row */
    private function rowToNav(array $row): array
    {
        return [
            'id' => $row['id'],
            'author' => $row['author'],
            'title' => Chinese::stripUnpairedBookTitleMarks((string) $row['title']),
            'rhythmic' => Chinese::stripUnpairedBookTitleMarks((string) $row['rhythmic']),
        ];
    }

    /** @param list<array<string, mixed>> $rows */
    private function mapRowsToHits(array $rows): array
    {
        $hits = [];
        foreach ($rows as $row) {
            if (isset($row['body_snip']) && $row['body_snip'] !== null && $row['body_snip'] !== '') {
                $preview = preg_replace('/\s+/u', '', (string) $row['body_snip']) ?? '';
            } else {
                $paras = json_decode((string) ($row['paragraphs'] ?? '[]'), true) ?? [];
                $preview = preg_replace('/\s+/u', '', (string) ($paras[0] ?? '')) ?? '';
            }
            if (mb_strlen($preview, 'UTF-8') > self::SEARCH_PREVIEW_MAX) {
                $preview = mb_substr($preview, 0, self::SEARCH_PREVIEW_MAX, 'UTF-8') . '…';
            }
            $preview = Chinese::stripUnpairedBookTitleMarks($preview);
            $hits[] = [
                'id' => $row['id'],
                'kind' => $row['kind'],
                'author' => $row['author'],
                'title' => Chinese::stripUnpairedBookTitleMarks((string) $row['title']),
                'rhythmic' => Chinese::stripUnpairedBookTitleMarks((string) $row['rhythmic']),
                'preview' => $preview,
            ];
        }

        return $hits;
    }

    /** @param list<array<string, mixed>> $hits */
    private function dedupeSearchHitsForPage(array $hits, int $pageSize): array
    {
        $seenId = [];
        $seenDisplay = [];
        $unique = [];
        foreach ($hits as $hit) {
            if (isset($seenId[$hit['id']])) {
                continue;
            }
            $displayKey = trim($hit['author']) . "\0" . trim($hit['title'] ?: $hit['rhythmic'] ?? '');
            if (isset($seenDisplay[$displayKey])) {
                continue;
            }
            $seenId[$hit['id']] = true;
            $seenDisplay[$displayKey] = true;
            $unique[] = $hit;
            if (count($unique) >= $pageSize) {
                break;
            }
        }

        return $unique;
    }

    private function searchPageOverfetchLimit(int $pageSize): int
    {
        return min(max($pageSize * 3, $pageSize + 6), 48);
    }
}
