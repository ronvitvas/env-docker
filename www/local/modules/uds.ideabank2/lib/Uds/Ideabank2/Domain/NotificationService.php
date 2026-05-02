<?php
declare(strict_types=1);

namespace Uds\Ideabank2\Domain;

use Bitrix\Main\Application;
use Bitrix\Main\ErrorHandler;
use Bitrix\Main\Error;

class NotificationService
{
    /**
     * Уведомление автору о смене статуса
     */
    public static function notifyAuthorOnStatusChange(int $ideaId, int $newStatusId, int $userId = 0): void
    {
        try {
            $connection = Application::getConnection();
            $idea = $connection->query("SELECT * FROM b_uds_ideabank_idea WHERE ID=" . (int)$ideaId)->fetch();
            if (!$idea) return;

            $status = $connection->query("SELECT * FROM b_uds_ideabank_idea_status WHERE ID=" . (int)$newStatusId)->fetch();
            $statusName = $status['NAME'] ?? 'Неизвестный';
            $authorId = $userId ?: (int)$idea['OWNER_USER_ID'];

            if (!$authorId) return;

            // Email через CMailEvent
            if (\Bitrix\Main\Loader::includeModule('main')) {
                \CEvent::Send(
                    "UDE_IDEABANK_STATUS_CHANGE",
                    "s1",
                    [
                        "USER_ID" => $authorId,
                        "IDEA_ID" => $ideaId,
                        "TITLE" => $idea['TITLE'],
                        "STATUS" => $statusName,
                        "PORTAL_URL" => \COption::GetOptionString("main", "site_name", "https://example.com"),
                    ]
                );
            }
        } catch (\Exception $e) {
            ErrorHandler::getInstance()->handle(Error::create($e->getMessage()));
        }
    }

    /**
     * Уведомление модератору о новой идее
     */
    public static function notifyModeratorOnNewIdea(int $ideaId): void
    {
        try {
            $connection = Application::getConnection();
            $idea = $connection->query("SELECT * FROM b_uds_ideabank_idea WHERE ID=" . (int)$ideaId)->fetch();
            if (!$idea) return;

            if (\Bitrix\Main\Loader::includeModule('main')) {
                \CEvent::Send(
                    "UDE_IDEABANK_NEW_IDEA",
                    "s1",
                    [
                        "IDEA_ID" => $ideaId,
                        "TITLE" => $idea['TITLE'],
                        "AUTHOR_ID" => $idea['OWNER_USER_ID'],
                        "PORTAL_URL" => \COption::GetOptionString("main", "site_name", "https://example.com"),
                    ]
                );
            }
        } catch (\Exception $e) {
            ErrorHandler::getInstance()->handle(Error::create($e->getMessage()));
        }
    }

    /**
     * Push-уведомление пользователю (Bitrix notification)
     */
    public static function sendPush(int $userId, string $title, string $message, string $url = ''): void
    {
        if (!$userId) return;
        try {
            if (!\Bitrix\Main\Loader::includeModule('main')) return;

            $notification = [
                'EVENT_NAME' => 'ON_INFO',
                'MODULE_NAME' => 'uds.ideabank2',
                'USER_ID' => $userId,
                'FROM_USER_ID' => 0,
                'TITLE' => $title,
                'MESSAGE' => $message,
                'TAG' => 'UDE_IDEABANK_' . uniqid(),
                'URL' => $url,
            ];

            $notify = new \Bitrix\Main\Notification\Notification();
            // Use CUser::SendEvent as fallback since Notification API may vary
            \CUser::SendEvent($userId, 'ON_INFO', [
                '=EVENT_NAME' => "'ON_INFO'",
                '=MODULE_ID' => "'uds.ideabank2'",
                '=SUBJECT' => "'" . addslashes($title) . "'",
                '=MESSAGE' => "'" . addslashes($message) . "\n\n<a href='" . addslashes($url) . "'>Перейти</a>",
            ]);
        } catch (\Exception $e) {
            ErrorHandler::getInstance()->handle(Error::create($e->getMessage()));
        }
    }
}
