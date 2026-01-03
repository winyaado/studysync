<?php
/**
 * ファイル: NotificationService.php
 * 説明: 通知実装の選択と送信を行うサービス。
 */
namespace App\Notifications;

require_once __DIR__ . '/NotifierInterface.php';
require_once __DIR__ . '/DiscordNotifier.php';
require_once __DIR__ . '/LogNotifier.php';

class NotificationService
{
    /**
     * @var NotifierInterface
     */
    private $notifier;

    /**
     * 使用する通知実装を初期化します。
     *
     * @param NotifierInterface|null $notifier 任意の通知実装
     */
    public function __construct($notifier = null)
    {
        if ($notifier instanceof NotifierInterface) {
            $this->notifier = $notifier;
            return;
        }

        $className = defined('NOTIFIER_CLASS') ? NOTIFIER_CLASS : '';
        if ($className) {
            $className = '\\' . ltrim($className, '\\');
            if (class_exists($className)) {
                $this->notifier = new $className();
                return;
            }
        }

        $this->notifier = new LogNotifier();
    }

    /**
     * 通知を送信します。
     *
     * @param string $message メッセージ
     * @param array $details 追加情報
     * @return bool 成功時 true
     */
    public function dispatch(string $message, array $details): bool
    {
        return $this->notifier->notify($message, $details);
    }
}
