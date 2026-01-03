<?php
/**
 * ファイル: User.php
 * 説明: ユーザー情報の取得・作成・更新を提供します。
 */
class User
{
    private $pdo;

    /**
     * コンストラクタ: DB接続を初期化します。
     */
    public function __construct()
    {
        require_once __DIR__ . '/api_helpers.php';
        $this->pdo = get_pdo_connection();
    }

    /**
     * Google ID からユーザー情報を取得します。
     * @param string $googleId GoogleのサブID
     * @return array|false ユーザー情報
     */
    public function findByGoogleId($googleId)
    {
        $stmt = $this->pdo->prepare('
            SELECT 
                u.*, 
                t.banned_at as tenant_banned_at 
            FROM users u 
            LEFT JOIN tenants t ON u.tenant_id = t.id 
            WHERE u.google_id = ?
        ');
        $stmt->execute([$googleId]);
        return $stmt->fetch();
    }

    /**
     * メールアドレスからテナントIDを取得または作成します。
     * @param string $email ユーザーのメールアドレス
     * @return int 取得または作成されたテナントID
     */
    private function getTenantIdFromEmail(string $email): int
    {
        // メールアドレスからドメイン識別子を抽出
        // 例: user@aaa.bbb.ccc.com -> bbb.ccc.com
        // 例: user@aaa.bbb.co.jp -> aaa.bbb.co.jp
        // 例: user@aaa.bbb.com -> aaa.bbb.com
        // 例: user@bbb.com -> bbb.com

        $parts = explode('@', $email);
        $domain = $parts[1] ?? '';

        $domainIdentifier = '';
        if (empty($domain)) {
            $domainIdentifier = 'default';
        } else {
            $domainParts = explode('.', $domain);
            $domainPartsCount = count($domainParts);
            
            // ドメインパーツが3つ以上の場合、末尾から3つを結合
            if ($domainPartsCount >= 3) {
                $domainIdentifier = implode('.', array_slice($domainParts, -3));
            } elseif ($domainPartsCount == 2) {
                // ドメインパーツが2つの場合、そのまま結合
                $domainIdentifier = implode('.', $domainParts);
            } else {
                // ドメインパーツが1つの場合、そのまま
                $domainIdentifier = $domain;
            }
        }
        
        if (empty($domainIdentifier)) {
            // フォールバック
            $domainIdentifier = 'default';
        }

        // 既存のテナントを検索
        $stmt = $this->pdo->prepare('SELECT id FROM tenants WHERE domain_identifier = ?');
        $stmt->execute([$domainIdentifier]);
        $tenant = $stmt->fetch();

        if ($tenant) {
            return $tenant['id'];
        }

        // 新しいテナントとして登録
        $tenantName = $domainIdentifier;
        $stmt = $this->pdo->prepare('INSERT INTO tenants (name, domain_identifier) VALUES (?, ?)');
        $stmt->execute([$tenantName, $domainIdentifier]);
        return $this->pdo->lastInsertId();
    }

    /**
     * Google OAuthのユーザー情報から登録または更新を行います。
     * @param array $userData Google OAuthのユーザー情報
     * @return array|false ユーザー情報
     */
    public function createOrUpdate($userData)
    {
        $user = $this->findByGoogleId($userData['sub']);
        $tenantId = $this->getTenantIdFromEmail($userData['email']);

        if ($user) {
            // Update existing user: DO NOT update the name
            $stmt = $this->pdo->prepare('
                UPDATE users SET 
                    email = ?, 
                    picture = ?, 
                    tenant_id = ?,
                    updated_at = NOW() 
                WHERE google_id = ?
            ');
            $stmt->execute([
                $userData['email'],
                $userData['picture'],
                $tenantId,
                $userData['sub']
            ]);
        } else {
            // Create new user
            // Use the local part of the email as the initial name
            $stmt = $this->pdo->prepare('
                INSERT INTO users (google_id, name, email, picture, tenant_id, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, NOW(), NOW()) -- nameに文字列"NULL"を設定
            ');
            $stmt->execute([
                //ユーザー名とプロフィール画像は記録しない
                $userData['sub'],
                "NULL",
                $userData['email'],
                "NULL",
                $tenantId
            ]);
        }

        // Return the latest user data
        return $this->findByGoogleId($userData['sub']);
    }
}
