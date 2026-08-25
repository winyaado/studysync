<?php
/**
 * API名: your/endpoint
 * 説明: ここにAPIの概要や役割を記載します。
 * 認証: 必須 / 任意 など
 * HTTPメソッド: GET / POST / PUT / DELETE から適宜選択
 * 引数:
 *   - GET/POST: パラメータ名と説明
 * 返り値:
 *   - JSON: success / error の形式など
 * エラー: 400/403/404/405/500 など
 */

// --- 1. 必須ファイルの読み込み ---

// --- 2. 認証と権限チェック ---

// --- 3. HTTPメソッドの検証 ---
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_response(405, ['error' => '許可されていないメソッドです。']);
}

// --- 4. パラメータの取得 ---
$exampleParam = $_GET['example'] ?? null;

// --- 5. 入力値のバリデーション ---
if ($exampleParam === null) {
    send_json_response(400, ['error' => 'example は必須です。']);
}

// --- 6. メイン処理 ---
try {
    $pdo = get_pdo_connection();

    // ここに実際の処理を記載
    $stmt = $pdo->prepare('SELECT :example AS result');
    $stmt->execute(['example' => $exampleParam]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    send_json_response(200, ['success' => true, 'data' => $data]);
} catch (Exception $e) {
    handle_exception($e);
}
