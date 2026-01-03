<?php
/**
 * API名: APIルーティングエントリ
 * 説明: 単一エントリでルートを解決し、ミドルウェア適用後にAPIファイルへ振り分けます。
 * 認証: routes.php の静的定義に従う（認証不要ルートは除外）
 */
require_once __DIR__ . '/../../api_app/util/api_helpers.php';

/**
 * ルート文字列を取得します。
 * @return string
 */
function get_route(): string
{
    $route = $_GET['route'] ?? '';
    if ($route !== '') {
        return trim($route, '/');
    }

    $pathInfo = $_SERVER['PATH_INFO'] ?? '';
    if ($pathInfo !== '') {
        return trim($pathInfo, '/');
    }

    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
    $uriPath = trim($uriPath, '/');
    if (strpos($uriPath, 'api/index.php/') === 0) {
        return substr($uriPath, strlen('api/index.php/'));
    }
    if (strpos($uriPath, 'api/') === 0) {
        return substr($uriPath, strlen('api/'));
    }

    return '';
}

/**
 * ルート文字列の妥当性を検証します。
 * @param string $route
 * @return bool
 */
function is_valid_route(string $route): bool
{
    return $route !== '' && preg_match('/^[a-zA-Z0-9_\\/]+$/', $route) === 1;
}

/**
 * ルート定義を取得します。
 * @return array<string,array{file:string,require_admin:bool}>
 */
function get_routes(): array
{
    $routes = require __DIR__ . '/../../api_app/routes.php';
    return is_array($routes) ? $routes : [];
}

/**
 * ルートごとの認証ポリシーを返します。
 * @param string $route
 * @return array{require_auth:bool,require_admin:bool}
 */
function get_auth_policy(string $route, array $routes): array
{
    $routeConfig = $routes[$route] ?? [];
    return [
        'require_auth' => !is_public_route($route),
        'require_admin' => (bool)($routeConfig['require_admin'] ?? true),
    ];
}

/**
 * 認証不要ルートかどうかを判定します。
 * @param string $route
 * @return bool
 */
function is_public_route(string $route): bool
{
    $publicRoutes = [
        'system/google_oauth_callback',
    ];
    return in_array($route, $publicRoutes, true);
}

/**
 * ミドルウェアを適用します。
 * @param string $route
 * @return void
 */
function apply_middlewares(string $route, array $routes): void
{
    $authPolicy = get_auth_policy($route, $routes);
    if ($authPolicy['require_auth']) {
        require_authentication();
        if ($authPolicy['require_admin'] && (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin'])) {
            send_json_response(403, ['error' => '管理者権限が必要です。']);
        }
    }
}

/**
 * ルートを解決してAPIを実行します。
 * @param string $route
 * @param array<string,string> $map
 * @return void
 */
function dispatch(string $route, array $routes): void
{
    if (!isset($routes[$route]['file'])) {
        send_json_response(404, ['error' => 'APIが見つかりません。']);
    }
    apply_middlewares($route, $routes);
    require_once $routes[$route]['file'];
}

$route = get_route();
if (!is_valid_route($route)) {
    send_json_response(404, ['error' => 'APIが見つかりません。']);
}

$routes = get_routes();
dispatch($route, $routes);
