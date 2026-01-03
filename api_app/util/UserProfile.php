<?php
/**
 * ファイル: UserProfile.php
 * 説明: ユーザープロフィール情報の取得・更新を提供します。
 */
class UserProfile
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
     * ユーザーIDからプロフィール情報を取得します。
     * @param int $userId ユーザーID
     * @return array|false プロフィール情報
     */
    public function findByUserId($userId)
    {
        $stmt = $this->pdo->prepare('SELECT * FROM user_profiles WHERE user_id = ?');
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }

    /**
     * デフォルトのプロフィールを作成します。
     * @param int $userId ユーザーID
     * @return array|false 作成されたプロフィール情報
     */
    public function createDefaultProfile($userId)
    {
        $activeIdenticon = $this->generateDefaultIdenticonString();
        $stmt = $this->pdo->prepare('INSERT INTO user_profiles (user_id, active_identicon) VALUES (?, ?)');
        $stmt->execute([$userId, $activeIdenticon]);
        return $this->findByUserId($userId);
    }

    /**
     * デフォルトの識別用シードを生成します。
     * @return string シード文字列
     */
    private function generateDefaultIdenticonString()
    {
        $colorSeed = random_int(0, 2147483647);
        $p1Seed = random_int(0, 2147483647);
        $p2Seed = random_int(0, 2147483647);
        return implode(',', [$colorSeed, $p1Seed, $p2Seed]);
    }

    /**
     * ユーザー名を取得します。
     * @param int $userId ユーザーID
     * @return string ユーザー名
     */
    public function getUsername($userId)
    {
        $profile = $this->findByUserId($userId);
        if ($profile) {
            return $profile['username'];
        }
        return '名もなき猫';
    }

    /**
     * プロフィール情報を取得します。
     * @param int $userId ユーザーID
     * @return array プロフィール情報
     */
    public function getProfile($userId)
    {
        $profile = $this->findByUserId($userId);
        if (!$profile) {
            $this->createDefaultProfile($userId);
            $profile = $this->findByUserId($userId);
        }
        return [
            'username' => $profile['username'] ?? '名もなき猫',
            'bio' => $profile['bio'] ?? ''
        ];
    }

    /**
     * ユーザー名を更新します。
     * @param int $userId ユーザーID
     * @param string $newUsername 新しいユーザー名
     * @return bool 更新結果
     */
    public function updateUsername($userId, $newUsername)
    {
        $stmt = $this->pdo->prepare('UPDATE user_profiles SET username = ? WHERE user_id = ?');
        return $stmt->execute([$newUsername, $userId]);
    }

    /**
     * 自己紹介文を更新します。
     * @param int $userId ユーザーID
     * @param string $newBio 新しい自己紹介
     * @return bool 更新結果
     */
    public function updateBio($userId, $newBio)
    {
        $stmt = $this->pdo->prepare('UPDATE user_profiles SET bio = ? WHERE user_id = ?');
        return $stmt->execute([$newBio, $userId]);
    }
}