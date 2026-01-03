<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/asset_version.php';
/**
 * 関数: send_json_response
 * 説明: 指定されたステータスコードとデータでJSONレスポンスを送信し、スクリプトの実行を終了します。
 * @param int $statusCode HTTPステータスコード
 * @param array $data レスポンスとして送信するデータ
 * @return void
 */
function send_json_response(int $statusCode, array $data): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * 関数: require_authentication
 * 説明: ユーザーが認証されているか、必要に応じてテナントIDが存在するかを確認します。POSTリクエストの場合はCSRFトークンを検証し、ユーザーまたはテナントがBANされていないかをチェックし、レートリミットを適用します。
 * @param bool $require_tenant_id テナントIDの認証を必須とするか
 * @return void
 */

function require_authentication(bool $require_tenant_id = false): void {
    if (!isset($_SESSION['user_id'])) {
        send_json_response(403, ['error' => 'User not authenticated.']);
    }
    if ($require_tenant_id && !isset($_SESSION['tenant_id'])) {
        send_json_response(403, ['error' => 'User not authenticated or tenant not identified.']);
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        require_csrf_token();
    }

    // --- BAN Check ---
    $pdo = get_pdo_connection();
    $stmt = $pdo->prepare('
        SELECT 
            u.banned_at AS user_banned_at, 
            t.banned_at AS tenant_banned_at 
        FROM users u
        LEFT JOIN tenants t ON u.tenant_id = t.id
        WHERE u.id = ?
    ');
    $stmt->execute([$_SESSION['user_id']]);
    $userBanStatus = $stmt->fetch();

    if ($userBanStatus && ($userBanStatus['user_banned_at'] !== null || $userBanStatus['tenant_banned_at'] !== null)) {
        session_destroy();
        send_json_response(403, ['error' => 'Your account or domain has been banned.']);
    }

    require_rate_limit();
}

/**
 * 関数: get_csrf_token
 * 説明: セッションにCSRFトークンが存在しない場合、新しく生成して返します。
 * @return string 生成または取得されたCSRFトークン
 */

function get_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 関数: validate_csrf_token
 * 説明: 指定されたCSRFトークンがセッションに保存されているものと一致するかを検証します。
 * @param string $token 検証するCSRFトークン
 * @return bool トークンが有効な場合はtrue、そうでない場合はfalse
 */

function validate_csrf_token(string $token): bool {
    if (empty($_SESSION['csrf_token']) || $token === '') {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * 関数: require_csrf_token
 * 説明: POSTデータ、X-CSRF-TOKENヘッダー、またはJSON入力からCSRFトークンを抽出し、検証します。トークンが無効な場合は403エラーレスポンスを送信します。
 * @return void
 */

function require_csrf_token(): void {
    $token = $_POST['csrf_token'] ?? '';
    if ($token === '') {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }
    if ($token === '' && isset($_SERVER['CONTENT_TYPE']) && stripos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $json = get_json_input();
        $token = is_array($json) ? ($json['csrf_token'] ?? '') : '';
    }
    if (!validate_csrf_token($token)) {
        send_json_response(403, ['error' => '無効なCSRFトークンです。']);
    }
}

/**
 * 関数: get_pdo_connection
 * 説明: PDOデータベース接続を確立して返します。接続に失敗した場合は500エラーレスポンスを送信します。
 * @return PDO データベース接続オブジェクト
 */

function get_pdo_connection(): PDO {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        send_json_response(500, ['error' => 'データベース接続に失敗しました。']);
    }
}

/**
 * 関数: get_json_input
 * 説明: リクエストボディから生のJSON入力を読み込み、デコードして連想配列として返します。結果は後続の呼び出しのためにキャッシュされます。
 * @return array デコードされたJSONデータ
 */

function get_json_input(): array {
    if (!array_key_exists('__json_input', $GLOBALS)) {
        $raw = file_get_contents('php://input');
        $GLOBALS['__json_input_raw'] = $raw;
        $decoded = json_decode($raw, true);
        $GLOBALS['__json_input'] = is_array($decoded) ? $decoded : [];
    }
    return $GLOBALS['__json_input'];
}

/**
 * 関数: handle_exception
 * 説明: 例外をログに記録し、適切なHTTPステータスコード（クライアントエラーには4xx、サーバーエラーには5xx）を決定し、JSON形式のエラーレスポンスを送信します。
 * @param Throwable $e 処理する例外オブジェクト
 * @return void
 */

function handle_exception(Throwable $e): void {
    error_log("API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    $code = (int)$e->getCode();
    $statusCode = ($code >= 400 && $code < 500) ? $code : 500;
    $message = ($statusCode >= 500) ? 'サーバーエラーが発生しました。' : $e->getMessage();
    send_json_response($statusCode, ['error' => $message]);
}

/**
 * 関数: get_rate_limit_key
 * 説明: レートリミットのための一意のキーを生成します。認証済みの場合はユーザーIDに基づき、それ以外の場合はクライアントのIPアドレスに基づきます。
 * @return string レートリミットキー
 */

function get_rate_limit_key(): string {
    if (isset($_SESSION['user_id'])) {
        return 'user:' . (string)$_SESSION['user_id'];
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return 'ip:' . $ip;
}

/**
 * 関数: require_rate_limit
 * 説明: 指定されたリミットと時間枠に基づいてレートリミットを適用します。リミットを超過した場合は、429 Too Many Requestsレスポンスを送信します。
 * @param int $limit 許可されるリクエストの最大数
 * @param int $windowSeconds レートリミットの計測期間（秒）
 * @return void
 */

function require_rate_limit(int $limit = RATE_LIMIT_MAX_REQUESTS, int $windowSeconds = RATE_LIMIT_WINDOW_SECONDS): void {
    if ($limit <= 0 || $windowSeconds <= 0) {
        return;
    }

    $rateKey = get_rate_limit_key();
    $nowTs = time();

    $pdo = get_pdo_connection();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT id, UNIX_TIMESTAMP(window_start) AS window_start_ts, count
        FROM rate_limits
        WHERE rate_key = ?
        FOR UPDATE
    ");
    $stmt->execute([$rateKey]);
    $row = $stmt->fetch();

    if (!$row) {
        $stmt = $pdo->prepare("INSERT INTO rate_limits (rate_key, window_start, count) VALUES (?, FROM_UNIXTIME(?), 1)");
        $stmt->execute([$rateKey, $nowTs]);
        $pdo->commit();
        return;
    }

    $windowStartTs = (int)$row['window_start_ts'];
    $count = (int)$row['count'];
    $elapsed = $nowTs - $windowStartTs;

    if ($elapsed >= $windowSeconds) {
        $stmt = $pdo->prepare("UPDATE rate_limits SET window_start = FROM_UNIXTIME(?), count = 1 WHERE id = ?");
        $stmt->execute([$nowTs, $row['id']]);
        $pdo->commit();
        return;
    }

    $newCount = $count + 1;
    $stmt = $pdo->prepare("UPDATE rate_limits SET count = ? WHERE id = ?");
    $stmt->execute([$newCount, $row['id']]);
    $pdo->commit();

    if ($newCount > $limit) {
        $retryAfter = max(1, $windowSeconds - $elapsed);
        header('Retry-After: ' . $retryAfter);
        send_json_response(429, ['error' => 'Rate limit exceeded.']);
    }
}
