<?php
/**
 * ファイル: LogNotifier.php
 * 説明: ログへ通知を書き込む実装。
 */
namespace App\Notifications;

require_once __DIR__ . '/NotifierInterface.php';

class LogNotifier implements NotifierInterface
{
    /**
     * 通知内容をログファイルへ追記します。
     *
     * @param string $message メッセージ
     * @param array $details 追加情報
     * @return bool 成功時 true
     */
    public function notify(string $message, array $details): bool
    {
        $logMessage = "[" . date('Y-m-d H:i:s') . "] REPORT: " . $message . "\n";
        $logMessage .= "Details: " . json_encode($details, JSON_UNESCAPED_UNICODE) . "\n";
        $logMessage .= "--------------------------------------------------\n";

        $logFilePath = __DIR__ . '/../../../reports.log';

        if (file_put_contents($logFilePath, $logMessage, FILE_APPEND | LOCK_EX) !== false) {
            return true;
        }

        error_log("Failed to write report to log file: " . $logFilePath);
        return false;
    }
}
