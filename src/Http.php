<?php

declare(strict_types=1);

namespace Poetry;

final class Http
{
    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        return (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

  /**
   * @param bool $isApi 为 true 时仅返回 JSON，CSP 从简；HTML 页需允许百度统计等内联脚本
   */
    public static function applySecurityHeaders(bool $isApi = false): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Frame-Options: SAMEORIGIN');
        if (!$isApi) {
            header('Permissions-Policy: unload=(self)');
        }

        $baiduImg = "https://hm.baidu.com https://*.baidu.com";
        $baiduConnect = "https://hm.baidu.com https://*.baidu.com";

        if ($isApi) {
            header(
                "Content-Security-Policy: default-src 'none'; frame-ancestors 'self'"
            );
        } else {
            $scriptSrc = "'self' 'unsafe-inline' https://esm.sh https://cdn.jsdelivr.net "
                . "https://hm.baidu.com "
                . "'sha256-r4gjtTM12iEOJk8RRpNQbLoFDqOIHZZaGts5PxPwWyg='";
            header(
                "Content-Security-Policy: default-src 'self' blob:; "
                . "script-src {$scriptSrc}; "
                . "style-src 'self' 'unsafe-inline'; "
                . "img-src 'self' data: {$baiduImg}; "
                . "font-src 'self'; "
                . "connect-src 'self' https://esm.sh https://cdn.jsdelivr.net {$baiduConnect}; "
                . "base-uri 'self'; "
                . "form-action 'self'; "
                . "frame-ancestors 'self'"
            );
        }

        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public static function jsonPublic(mixed $data, int $maxAge = 3600, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header(
            'Cache-Control: public, max-age=' . $maxAge . ', stale-while-revalidate=86400'
        );
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public static function dbUnavailable(): void
    {
        self::json(['error' => '数据库不可用'], 503);
    }

    public static function parseInt(?string $raw, int $fallback, int $min, int $max): int
    {
        if ($raw === null || $raw === '') {
            return $fallback;
        }
        if (!ctype_digit($raw) && !preg_match('/^-?\d+$/', $raw)) {
            return $fallback;
        }
        $n = (int) $raw;

        return min($max, max($min, $n));
    }
}
