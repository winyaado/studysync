<?php
/**
 * ファイル: DiscordNotifier.php
 * 説明: Discord Webhook へ通知を送信します。
 */
namespace App\Notifications;

require_once __DIR__ . '/NotifierInterface.php';

class DiscordNotifier implements NotifierInterface
{
    /**
     * @var string
     */
    private $webhookUrl;

    /**
     * コンストラクタ: Webhook URL を初期化します。
     *
     * @param string|null $webhookUrl
     */
    public function __construct($webhookUrl = null)
    {
        if ($webhookUrl !== null) {
            $this->webhookUrl = $webhookUrl;
            return;
        }

        $this->webhookUrl = defined('DISCORD_WEBHOOK_URL') ? DISCORD_WEBHOOK_URL : '';
    }

    /**
     * Discordへ通知を送信します。
     *
     * @param string $message メッセージ
     * @param array $details 追加情報
     * @return bool 成功時 true
     */
    public function notify(string $message, array $details): bool
    {
        if (!$this->webhookUrl) {
            return false;
        }

        $contentLink = $details['content_link'] ?? '#';
        $reportLink = $details['report_link'] ?? '#';
        $reportedBy = $details['reported_by'] ?? 'Unknown User';
        $reason = $details['reason_category'] ?? 'General';
        $reasonDetails = $details['reason_details'] ?? 'No details provided.';

        $payload = [
            'username' => '通報システム',
            'avatar_url' => 'https://example.com/report_icon.png',
            'embeds' => [
                [
                    'title' => '新しい通報がありました: ' . $message,
                    'description' => "**通報者:** {$reportedBy}\n**対象コンテンツ:** [{$details['content_title']}]({$contentLink})\n**カテゴリ:** {$reason}\n**詳細:** {$reasonDetails}",
                    'color' => 15548997,
                    'fields' => [
                        [
                            'name' => '通報ID',
                            'value' => $details['report_id'] ?? 'N/A',
                            'inline' => true,
                        ],
                        [
                            'name' => 'コンテンツID',
                            'value' => $details['content_id'] ?? 'N/A',
                            'inline' => true,
                        ],
                        [
                            'name' => '詳細確認',
                            'value' => "[管理画面で確認]({$reportLink})",
                            'inline' => false,
                        ],
                    ],
                    'timestamp' => date('c'),
                ],
            ],
        ];

        $ch = curl_init($this->webhookUrl);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        }

        error_log("Failed to send Discord notification. HTTP Code: {$httpCode}, Response: {$response}");
        return false;
    }
}
