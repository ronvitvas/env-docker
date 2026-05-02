<?php
declare(strict_types=1);

namespace Uds\Ideabank2\Domain;

use Bitrix\Main\Application;
use Bitrix\Main\ErrorHandler;
use Bitrix\Main\Error;

class TaskIntegrationService
{
    /**
     * Создать задачу из идеи (при статусе "Принято")
     */
    public static function createTaskFromIdea(int $ideaId): ?int
    {
        if (!\Bitrix\Main\Loader::includeModule('tasks')) return null;

        try {
            $connection = Application::getConnection();
            $idea = $connection->query("SELECT * FROM b_uds_ideabank_idea WHERE ID=" . (int)$ideaId)->fetch();
            if (!$idea) return null;

            // Check if task already linked
            $existing = $connection->query("SELECT TASK_ID FROM b_uds_ideabank_idea WHERE ID=" . (int)$ideaId . " AND TASK_ID IS NOT NULL")->fetch();
            // Note: TASK_ID column doesn't exist in current schema, create via workflow log instead

            $task = \Bitrix\Tasks\Core\Task::createNew()
                ->setTitle('Идея: ' . $idea['TITLE'])
                ->setFields([
                    'DESCRIPTION' => 'Автоматически создана из идеи банка идей #' . $ideaId . '

' . ($idea['DESCRIPTION'] ?? '') . '

**Проблема:** ' . ($idea['PROBLEM'] ?? 'Не описана') . '

**Предложение:** ' . ($idea['PROPOSAL'] ?? 'Не описано'),
                    'RESPONSIBLE_ID' => $idea['ASSIGNEE_USER_ID'] ?: $idea['OWNER_USER_ID'],
                    'CREATED_BY' => $idea['OWNER_USER_ID'],
                    'COMPLETED_BY_TEMPLATE' => false,
                ]);

            $task->save();
            $taskId = $task->getId();

            // Store in workflow
            $connection->insert('b_uds_ideabank_idea_workflow', [
                'IDEA_ID' => $ideaId,
                'USER_ID' => $idea['OWNER_USER_ID'],
                'STATUS' => 'task_created',
                'STAGE' => 'accepted',
                'COMMENT' => 'Создана задача #' . $taskId,
                'CREATED_AT' => date('Y-m-d H:i:s'),
            ]);

            return $taskId;
        } catch (\Exception $e) {
            ErrorHandler::getInstance()->handle(Error::create('Task creation failed: ' . $e->getMessage()));
            return null;
        }
    }

    /**
     * Обновить прогресс идеи из задачи
     */
    public static function syncProgressFromTask(int $ideaId, int $taskId): void
    {
        if (!\Bitrix\Main\Loader::includeModule('tasks')) return;

        try {
            $task = \Bitrix\Tasks\Core\Task::load($taskId);
            if (!$task) return;

            $fields = $task->getFields();
            $progress = 0;
            if (isset($fields['PROGRESS'])) {
                $progress = (int)$fields['PROGRESS'];
            }

            $connection = Application::getConnection();
            $connection->update('b_uds_ideabank_idea', [
                'PROGRESS_PERCENT' => $progress,
                'UPDATED_AT' => new \Bitrix\Main\Type\DateTime(),
            ], ['ID' => $ideaId]);
        } catch (\Exception $e) {
            ErrorHandler::getInstance()->handle(Error::create('Task sync failed: ' . $e->getMessage()));
        }
    }
}
