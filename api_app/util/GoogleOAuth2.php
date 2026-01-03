<?php
/**
 * ファイル: GoogleOAuth2.php
 * 説明: Google OAuth 認証の初期化とコールバック処理を提供します。
 */
class GoogleOAuth2
{
    private $clientId;
    private $clientSecret;
    private $redirectUri;

    /**
     * コンストラクタ: OAuth設定を初期化します。
     */
    public function __construct()
    {
        $this->clientId = GOOGLE_CLIENT_ID;
        $this->clientSecret = GOOGLE_CLIENT_SECRET;
        $this->redirectUri = APP_URL . '/api/system/google_oauth_callback';

        if (empty($this->clientId) || empty($this->clientSecret)) {
            die('Google Client ID or Secret is not configured in config/config.php.');
        }
    }

    /**
     * Googleログインを開始します。
     */
    public function initiateLogin()
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;

        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'scope' => 'email profile',
            'access_type' => 'offline',
            'include_granted_scopes' => 'true',
            'state' => $state,
            'redirect_uri' => $this->redirectUri,
            'client_id' => $this->clientId,
            'response_type' => 'code'
        ]);

        header('Location: ' . $authUrl);
        exit();
    }

    /**
     * コールバックからアクセストークンを取得し、ユーザ情報を返します。
     * @param string $code 認可コード
     * @param string $state ステート
     * @return array|null ユーザ情報
     */
    public function handleCallback($code, $state)
    {
        if (!isset($_SESSION['oauth_state']) || $state !== $_SESSION['oauth_state']) {
            unset($_SESSION['oauth_state']);
            error_log("Invalid OAuth state.");
            return null;
        }
        unset($_SESSION['oauth_state']);

        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $params = [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $tokenData = json_decode($response, true);

        if ($httpCode !== 200 || !isset($tokenData['access_token'])) {
            error_log("Failed to get access token: " . print_r($tokenData, true));
            return null;
        }

        $_SESSION['access_token'] = $tokenData['access_token'];
        if (isset($tokenData['refresh_token'])) {
            $_SESSION['refresh_token'] = $tokenData['refresh_token'];
        }

        return $this->getUserProfile($tokenData['access_token']);
    }

    /**
     * アクセストークンからユーザ情報を取得します。
     * @param string $accessToken アクセストークン
     * @return array|null ユーザ情報
     */
    private function getUserProfile($accessToken)
    {
        $userInfoUrl = 'https://www.googleapis.com/oauth2/v3/userinfo';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $userInfoUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $userData = json_decode($response, true);

        if ($httpCode !== 200) {
            error_log("Failed to get user profile: " . print_r($userData, true));
            return null;
        }

        return $userData;
    }
}
