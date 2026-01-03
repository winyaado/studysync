<?php
/**
 * ファイル: NotifierInterface.php
 * 説明: 通知実装の共通インターフェース。
 */
namespace App\Notifications;

interface NotifierInterface
{
    /**
     * 通知を送信します。
     *
     * @param string $message メッセージ
     * @param array $details 追加情報
     * @return bool 成功時 true
     */
    public function notify(string $message, array $details): bool;
}
