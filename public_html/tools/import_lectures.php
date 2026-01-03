<?php
$pageTitle = '講義データの一括取り込み';
require_once __DIR__ . '/../parts/_header.php';

// --- 管理者チェック ---
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    echo '<main class="col-md-9 ms-sm-auto col-lg-10 p-4"><div class="alert alert-danger">このページにアクセスする権限がありません。</div></main>';
    require_once __DIR__ . '/../parts/_footer.php';
    exit();
}

// --- DB接続 ---
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("データベースに接続できませんでした: " . $e->getMessage());
}

$tenants = $pdo->query('SELECT id, name FROM tenants ORDER BY name')->fetchAll();
$messages = [];
$error_messages = [];

// --- POSTリクエスト処理 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        $error_messages[] = "不正な操作が検出されました。";
    } elseif (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK && !empty($_POST['tenant_id'])) {
        $tenantId = (int)$_POST['tenant_id'];
        $filePath = $_FILES['csv_file']['tmp_name'];
        
        $insertedCount = 0;
        $updatedCount = 0;
        $failedRows = [];

        try {
            $pdo->beginTransaction();
            
            $file = new SplFileObject($filePath);
            $file->setFlags(SplFileObject::READ_CSV);
            $file->setCsvControl(',', '"', '\\');

            $header = $file->fgetcsv(); // ヘッダー行を読み飛ばし
            if ($header !== ['lecture_code', 'name', 'quarter']) {
                throw new Exception('CSVのヘッダーが不正です。`lecture_code,name,quarter` の形式である必要があります。');
            }
            
            $stmt = $pdo->prepare(
                'INSERT INTO lectures (tenant_id, lecture_code, name, quarter) 
                 VALUES (:tenant_id, :lecture_code, :name, :quarter)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), quarter = VALUES(quarter)'
            );

            foreach ($file as $rowNumber => $row) {
                if (count($row) < 3 || empty($row[0])) {
                    continue; // 空行や不正な行はスキップ
                }
                
                $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
                $stmt->bindValue(':lecture_code', $row[0]);
                $stmt->bindValue(':name', $row[1]);
                $stmt->bindValue(':quarter', (int)$row[2], PDO::PARAM_INT);
                
                $stmt->execute();
                
                if ($stmt->rowCount() === 1) {
                    $insertedCount++;
                } elseif ($stmt->rowCount() > 1) { // MySQLのON DUPLICATE KEY UPDATEは更新時に2を返す
                    $updatedCount++;
                }
            }
            
            $pdo->commit();
            $messages[] = "処理が完了しました。新規作成: {$insertedCount}件, 更新: {$updatedCount}件";
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_messages[] = "エラーが発生しました: " . $e->getMessage();
        }
    } else {
        $error_messages[] = "ファイルまたはテナントが選択されていません。";
    }
}
?>

                <!-- サイドバー -->
                <?php require_once __DIR__ . '/../parts/_sidebar.php'; ?>

                <!-- メインコンテンツ -->
                <main class="col-md-9 ms-sm-auto col-lg-10 p-4">
                    <h1 class="h4"><?= htmlspecialchars($pageTitle) ?></h1>
                    <hr class="mb-4">

                    <?php foreach ($messages as $msg): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
                    <?php endforeach; ?>
                    <?php foreach ($error_messages as $msg): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($msg) ?></div>
                    <?php endforeach; ?>

                    <div class="card">
                        <div class="card-header">
                            講義データCSVアップロード
                        </div>
                        <div class="card-body">
                            <p class="card-text text-muted">
                                以下の形式のCSVファイルをアップロードしてください。<br>
                                ヘッダー行: <code>lecture_code,name,quarter</code><br>
                                `lecture_code`が既に存在する場合は更新、存在しない場合は新規作成されます。
                            </p>
                            <form action="import_lectures.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(get_csrf_token()); ?>">
                                <div class="mb-3">
                                    <label for="tenant_id" class="form-label">対象テナント</label>
                                    <select class="form-select" id="tenant_id" name="tenant_id" required>
                                        <option value="" selected disabled>テナントを選択してください</option>
                                        <?php foreach ($tenants as $tenant): ?>
                                            <option value="<?= $tenant['id'] ?>"><?= htmlspecialchars($tenant['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="csv_file" class="form-label">CSVファイル</label>
                                    <input class="form-control" type="file" id="csv_file" name="csv_file" accept=".csv" required>
                                </div>
                                <button type="submit" class="btn btn-primary">アップロードして取り込み</button>
                            </form>
                        </div>
                    </div>
                </main>

<?php
require_once __DIR__ . '/../parts/_footer.php';
?>
