<?php

declare(strict_types=1);

use Poetry\Config;
use Poetry\Http;
use Poetry\Router;
use Poetry\View;

require dirname(__DIR__) . '/vendor/autoload.php';

Config::load(dirname(__DIR__) . '/.env');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH) ?: '/';

Http::applySecurityHeaders(str_starts_with($path, '/api/'));

try {
    View::boot();
    Router::dispatch($method, $path);
} catch (JsonException) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => '服务器错误'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[poetry] ' . $e->getMessage());
    $isApi = str_starts_with($path, '/api/');
    http_response_code(500);
    if ($isApi) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => '服务器错误'], JSON_UNESCAPED_UNICODE);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo '服务器错误';
    }
}
