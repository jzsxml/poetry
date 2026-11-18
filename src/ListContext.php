<?php

declare(strict_types=1);

namespace Poetry;

/** 列表页 → 阅读页：携带返回列表所需的分页/筛选参数 */
final class ListContext
{
    /**
     * @return array{
     *   type: string,
     *   corpusId?: string,
     *   corpusName?: string,
     *   segment?: string|null,
     *   page: int,
     *   q?: string,
     *   kind?: string
     * }|null
     */
    public static function fromRequest(): ?array
    {
        $list = isset($_GET['list']) && is_string($_GET['list'])
            ? strtolower(trim($_GET['list']))
            : '';

        if ($list === 'corpus') {
            $corpusId = isset($_GET['corpus']) && is_string($_GET['corpus'])
                ? trim($_GET['corpus'])
                : '';
            if ($corpusId === '') {
                return null;
            }
            $meta = CorpusCatalog::byId($corpusId);
            if ($meta === null) {
                return null;
            }
            $page = Http::parseInt(
                isset($_GET['page']) && is_string($_GET['page']) ? $_GET['page'] : null,
                1,
                1,
                80
            );
            $segment = isset($_GET['segment']) && is_string($_GET['segment'])
                ? trim($_GET['segment'])
                : null;
            if ($segment === '') {
                $segment = null;
            }
            if ($segment !== null && !empty($meta['segments'])) {
                $segmentOk = false;
                foreach ($meta['segments'] as $seg) {
                    if (($seg['id'] ?? '') === $segment) {
                        $segmentOk = true;
                        break;
                    }
                }
                if (!$segmentOk) {
                    $segment = null;
                }
            }

            return [
                'type' => 'corpus',
                'corpusId' => $corpusId,
                'corpusName' => (string) ($meta['name'] ?? $corpusId),
                'segment' => $segment,
                'page' => $page,
            ];
        }

        if ($list === 'search') {
            $q = isset($_GET['q']) && is_string($_GET['q'])
                ? mb_substr(trim($_GET['q']), 0, 64, 'UTF-8')
                : '';
            if ($q === '') {
                return null;
            }
            $page = Http::parseInt(
                isset($_GET['page']) && is_string($_GET['page']) ? $_GET['page'] : null,
                1,
                1,
                80
            );
            $kind = KindFilter::parse(
                isset($_GET['kind']) && is_string($_GET['kind']) ? $_GET['kind'] : 'all'
            );

            return [
                'type' => 'search',
                'q' => $q,
                'page' => $page,
                'kind' => $kind,
            ];
        }

        return null;
    }

    /** @param array<string, mixed>|null $ctx */
    public static function toQueryString(?array $ctx): string
    {
        if ($ctx === null) {
            return '';
        }
        $type = (string) ($ctx['type'] ?? '');
        if ($type === 'corpus') {
            $params = [
                'list' => 'corpus',
                'corpus' => (string) ($ctx['corpusId'] ?? ''),
            ];
            $page = (int) ($ctx['page'] ?? 1);
            if ($page > 1) {
                $params['page'] = (string) $page;
            }
            $seg = $ctx['segment'] ?? null;
            if (is_string($seg) && $seg !== '') {
                $params['segment'] = $seg;
            }

            return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }
        if ($type === 'search') {
            $params = [
                'list' => 'search',
                'q' => (string) ($ctx['q'] ?? ''),
                'kind' => (string) ($ctx['kind'] ?? 'all'),
            ];
            $page = (int) ($ctx['page'] ?? 1);
            if ($page > 1) {
                $params['page'] = (string) $page;
            }

            return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }

        return '';
    }

    /** @param array<string, mixed>|null $ctx */
    public static function listHref(?array $ctx): ?string
    {
        if ($ctx === null) {
            return null;
        }
        $type = (string) ($ctx['type'] ?? '');
        if ($type === 'corpus') {
            $id = (string) ($ctx['corpusId'] ?? '');
            if ($id === '') {
                return null;
            }
            $qs = [];
            $page = (int) ($ctx['page'] ?? 1);
            if ($page > 1) {
                $qs['page'] = (string) $page;
            }
            $seg = $ctx['segment'] ?? null;
            if (is_string($seg) && $seg !== '') {
                $qs['segment'] = $seg;
            }
            $path = '/corpus/' . rawurlencode($id);
            if ($qs === []) {
                return $path;
            }

            return $path . '?' . http_build_query($qs, '', '&', PHP_QUERY_RFC3986);
        }
        if ($type === 'search') {
            $qs = [
                'q' => (string) ($ctx['q'] ?? ''),
                'kind' => (string) ($ctx['kind'] ?? 'all'),
            ];
            $page = (int) ($ctx['page'] ?? 1);
            if ($page > 1) {
                $qs['page'] = (string) $page;
            }

            return '/?' . http_build_query($qs, '', '&', PHP_QUERY_RFC3986);
        }

        return null;
    }

    /** @param array<string, mixed>|null $ctx */
    public static function workHrefSuffix(?array $ctx): string
    {
        $qs = self::toQueryString($ctx);

        return $qs === '' ? '' : '?' . $qs;
    }
}