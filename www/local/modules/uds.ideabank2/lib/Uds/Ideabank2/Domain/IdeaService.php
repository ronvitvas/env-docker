<?php

declare(strict_types=1);

namespace Uds\Ideabank2\Domain;

use Bitrix\Main\Loader;
use Bitrix\Main\ORM\Query\Query;
use Bitrix\Main\SystemException;
use Bitrix\Main\Event;
use Bitrix\Main\EventManager;
use Uds\Ideabank2\Table\IdeaTable;
use Uds\Ideabank2\Table\IdeaAuthorTable;
use Uds\Ideabank2\Table\IdeaStatusTable;
use Uds\Ideabank2\Table\IdeaCategoryTable;
use Uds\Ideabank2\Table\IdeaReactionTable;
use Uds\Ideabank2\Table\IdeaCommentTable;
use Uds\Ideabank2\Table\IdeaWorkflowTable;
use Uds\Ideabank2\Table\IdeaFeedbackTable;
use Uds\Ideabank2\Table\IdeaCoinTable;
use Uds\Ideabank2\Table\IdeaFileTable;

class IdeaService
{
    // Status codes mapped to seed data — use codes instead of hardcoded IDs
    public const STATUS_CODE_MODERATION    = 'moderation';
    public const STATUS_CODE_PUBLISHED     = 'published';
    public const STATUS_CODE_REVISE        = 'revise';
    public const STATUS_CODE_REJECTED      = 'rejected';
    public const STATUS_CODE_ACCEPTED      = 'accepted';
    public const STATUS_CODE_IMPLEMENTED   = 'implemented';
    private const MAX_ATTACHMENT_SIZE = 20971520;
    private const ALLOWED_ATTACHMENT_EXTENSIONS = 'pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,gif,webp,txt,csv,zip,rar';

    /**
     * Resolve status ID by code (cached)
     */
    protected static ?array $statusCache = null;

    public static function getStatusIdByCode(string $code): int
    {
        if (self::$statusCache === null) {
            self::$statusCache = [];
            foreach (IdeaStatusTable::getList(['select' => ['ID', 'CODE']]) as $row) {
                self::$statusCache[$row['CODE']] = (int)$row['ID'];
            }
        }

        return self::$statusCache[$code] ?? 0;
    }

    /**
     * Send module event
     */
    protected static function sendEvent(string $eventName, array $params = []): void
    {
        if (!Loader::includeModule('uds.ideabank2')) {
            return;
        }

        $event = new Event('uds.ideabank2', $eventName, $params);
        $event->send();
    }
    /** @var int|null */
    protected static ?int $currentUserId = null;

    public static function currentUser(): int
    {
        if (self::$currentUserId === null) {
            global $USER;
            self::$currentUserId = $USER instanceof \CUser ? (int)$USER->GetID() : 0;
        }

        return self::$currentUserId;
    }

    // ------------------- List -------------------

    /**
     * @param array $filter
     * @param array $orderBy
     * @param array $limit - ['n' => int, 'offset' => int]
     * @return array
     */
    public static function list(array $filter = [], array $orderBy = [], array $limit = []): array
    {
        $userFilter = self::applyUserFilter($filter);
        $query = IdeaTable::getQuery();

        foreach ($userFilter as $k => $v) {
            $query->addFilter('=' . $k, $v);
        }

        if (!empty($orderBy)) {
            foreach ($orderBy as $k => $d) {
                $query->addOrder($k, $d);
            }
        } else {
            $query->addOrder('ID', 'DESC');
        }

        if (isset($limit['n']) && $limit['n'] > 0) {
            $query->setLimit($limit['n']);
        }
        if (isset($limit['offset']) && $limit['offset'] > 0) {
            $query->setOffset($limit['offset']);
        }

        $result = $query->exec();
        $items = $result ? $result->fetchCollection() : [];

        return iterator_to_array($items);
    }

    /**
     * @param int $id
     * @return array|null
     */
    public static function getById(int $id): ?array
    {
        $item = IdeaTable::getList([
            'filter' => ['=ID' => $id],
            'limit' => 1,
        ])->fetch();

        return $item ?: null;
    }

    // ------------------- Create -------------------

    /**
     * @param array $data
     * @return int|false
     */
    public static function create(array $data)
    {
        if (!isset($data['OWNER_USER_ID'])) {
            $data['OWNER_USER_ID'] = self::currentUser();
        }

        if (!isset($data['CREATED_AT'])) {
            $data['CREATED_AT'] = new \Bitrix\Main\Type\DateTime();
        }

        if (!isset($data['STATUS_ID'])) {
            $data['STATUS_ID'] = 1;
        }

        if (!isset($data['IS_DRAFT'])) {
            $data['IS_DRAFT'] = 'Y';
        }

        if (!isset($data['IS_HIDDEN'])) {
            $data['IS_HIDDEN'] = 'N';
        }

        $addResult = IdeaTable::add($data);
        if (!$addResult->isSuccess()) {
            throw new SystemException(
                'Failed to create idea: ' . implode('; ', $addResult->getErrorMessages()),
                0,
                null,
                'idea_create'
            );
        }

        $ideaId = (int)$addResult->getId();

        // Create default author
        if (!isset($data['skip_author'])) {
            IdeaAuthorTable::add([
                'IDEA_ID' => $ideaId,
                'USER_ID' => $data['OWNER_USER_ID'],
                'SHARE_PERCENT' => 100,
            ]);
        }

        // Create workflow entry
        IdeaWorkflowTable::add([
            'IDEA_ID' => $ideaId,
            'USER_ID' => $data['OWNER_USER_ID'],
            'STATUS' => 'draft',
            'STAGE' => 'draft',
            'COMMENT' => 'Идея создана',
            'CREATED_AT' => new \Bitrix\Main\Type\DateTime(),
        ]);

        return $ideaId;
    }

    // ------------------- Update -------------------

    /**
     * @param int $id
     * @param array $data
     * @return bool
     */
    public static function update(int $id, array $data): bool
    {
        $data['UPDATED_AT'] = new \Bitrix\Main\Type\DateTime();
        $result = IdeaTable::update($id, $data);

        if (!$result->isSuccess()) {
            throw new SystemException(
                'Failed to update idea #' . $id . ': ' . implode('; ', $result->getErrorMessages()),
                0,
                null,
                'idea_update'
            );
        }

        return true;
    }

    // ------------------- Delete -------------------

    /**
     * @param int $id
     * @return bool
     */
    public static function delete(int $id): bool
    {
        foreach (self::getFiles($id) as $file) {
            if (!empty($file['FILE_ID'])) {
                \CFile::Delete((int)$file['FILE_ID']);
            }
        }

        $result = IdeaTable::delete($id);

        if (!$result->isSuccess()) {
            throw new SystemException(
                'Failed to delete idea #' . $id . ': ' . implode('; ', $result->getErrorMessages()),
                0,
                null,
                'idea_delete'
            );
        }

        return true;
    }

    public static function normalizeUploadedFiles(string $fieldName = 'attachments'): array
    {
        $source = $_FILES[$fieldName] ?? null;
        if (!is_array($source)) {
            return [];
        }

        $files = [];
        if (is_array($source['name'] ?? null)) {
            foreach ($source['name'] as $index => $name) {
                $files[] = [
                    'name' => $name,
                    'type' => $source['type'][$index] ?? '',
                    'tmp_name' => $source['tmp_name'][$index] ?? '',
                    'error' => (int)($source['error'][$index] ?? UPLOAD_ERR_NO_FILE),
                    'size' => (int)($source['size'][$index] ?? 0),
                ];
            }
        } else {
            $files[] = $source;
        }

        return array_values(array_filter($files, static function (array $file): bool {
            return (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        }));
    }

    public static function saveUploadedFiles(int $ideaId, array $files, int $userId = 0): array
    {
        if ($ideaId <= 0 || $files === []) {
            return [];
        }

        if (!Loader::includeModule('main')) {
            throw new SystemException('Main module is not available');
        }

        $saved = [];
        $userId = $userId > 0 ? $userId : self::currentUser();

        foreach ($files as $file) {
            if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ((int)($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                throw new SystemException('Не удалось загрузить файл "' . (string)($file['name'] ?? '') . '"');
            }

            $checkError = \CFile::CheckFile(
                $file,
                self::MAX_ATTACHMENT_SIZE,
                false,
                self::ALLOWED_ATTACHMENT_EXTENSIONS
            );
            if ($checkError !== '') {
                throw new SystemException(trim($checkError));
            }

            $file['MODULE_ID'] = 'uds.ideabank2';
            $fileId = (int)\CFile::SaveFile($file, 'uds.ideabank2/ideas');
            if ($fileId <= 0) {
                throw new SystemException('Не удалось сохранить файл "' . (string)($file['name'] ?? '') . '"');
            }

            $result = IdeaFileTable::add([
                'IDEA_ID' => $ideaId,
                'FILE_ID' => $fileId,
                'ORIGINAL_NAME' => (string)($file['name'] ?? ''),
                'SIZE' => (int)($file['size'] ?? 0),
                'CONTENT_TYPE' => (string)($file['type'] ?? ''),
                'CREATED_BY' => $userId,
                'CREATED_AT' => new \Bitrix\Main\Type\DateTime(),
            ]);

            if (!$result->isSuccess()) {
                \CFile::Delete($fileId);
                throw new SystemException('Не удалось привязать файл к идее: ' . implode('; ', $result->getErrorMessages()));
            }

            $saved[] = (int)$result->getId();
        }

        return $saved;
    }

    public static function getFiles(int $ideaId): array
    {
        if ($ideaId <= 0) {
            return [];
        }

        $rows = IdeaFileTable::getList([
            'filter' => ['=IDEA_ID' => $ideaId],
            'order' => ['ID' => 'ASC'],
        ])->fetchAll();

        foreach ($rows as &$row) {
            $file = \CFile::GetFileArray((int)($row['FILE_ID'] ?? 0));
            $row['NAME'] = (string)($row['ORIGINAL_NAME'] ?: ($file['ORIGINAL_NAME'] ?? $file['FILE_NAME'] ?? 'Файл'));
            $row['URL'] = (string)($file['SRC'] ?? '');
            $row['SIZE_FORMATTED'] = \CFile::FormatSize((int)($row['SIZE'] ?: ($file['FILE_SIZE'] ?? 0)));
            $row['CONTENT_TYPE'] = (string)($row['CONTENT_TYPE'] ?: ($file['CONTENT_TYPE'] ?? ''));
        }
        unset($row);

        return $rows;
    }

    // ------------------- Submit -------------------

    /**
     * @param int $id
     * @return array
     */
    public static function submit(int $id): array
    {
        $idea = self::getById($id);
        if (!$idea) {
            return ['success' => false, 'error' => 'Идея не найдена'];
        }

        $now = new \Bitrix\Main\Type\DateTime();
        $statusId = self::getStatusIdByCode(self::STATUS_CODE_MODERATION);

        self::update($id, [
            'IS_DRAFT' => 'N',
            'STATUS_ID' => $statusId > 0 ? $statusId : 1,
            'SUBMITTED_AT' => $now,
        ]);

        IdeaWorkflowTable::add([
            'IDEA_ID' => $id,
            'USER_ID' => self::currentUser(),
            'STATUS' => self::STATUS_CODE_MODERATION,
            'STAGE' => self::STATUS_CODE_MODERATION,
            'COMMENT' => 'Идея отправлена на модерацию',
            'CREATED_AT' => $now,
        ]);

        // Award submission coins
        CoinService::award('submitted', self::currentUser(), $id);

        return ['success' => true];
    }

    // ------------------- Moderation -------------------

    /**
     * @param int $id
     * @param string $decision - 'publish'|'revise'|'reject'
     * @param string $feedback
     * @return array
     */
    public static function moderate(int $id, string $decision, string $feedback = ''): array
    {
        $idea = self::getById($id);
        if (!$idea) {
            return ['success' => false, 'error' => 'Идея не найдена'];
        }

        $now = new \Bitrix\Main\Type\DateTime();
        $moderatorId = self::currentUser();

        $statusCode = self::STATUS_CODE_PUBLISHED;
        if ($decision === 'revise') {
            $statusCode = self::STATUS_CODE_REVISE;
        } elseif ($decision === 'reject') {
            $statusCode = self::STATUS_CODE_REJECTED;
        }

        $statusId = self::getStatusIdByCode($statusCode);
        if ($statusId <= 0) {
            return ['success' => false, 'error' => 'Статус "' . $statusCode . '" не найден'];
        }

        self::update($id, [
            'STATUS_ID' => $statusId,
            'MODERATED_AT' => $now,
            'MODERATED_BY' => $moderatorId,
            'MODERATION_DECISION' => $decision,
            'MODERATION_FEEDBACK' => $feedback,
            'PUBLISHED_AT' => $decision === 'publish' ? $now : null,
        ]);

        IdeaWorkflowTable::add([
            'IDEA_ID' => $id,
            'USER_ID' => $moderatorId,
            'STATUS' => $decision,
            'STAGE' => 'moderation',
            'COMMENT' => $feedback,
            'CREATED_AT' => $now,
        ]);

        IdeaFeedbackTable::add([
            'IDEA_ID' => $id,
            'USER_ID' => $idea['OWNER_USER_ID'],
            'STAGE' => 'moderation',
            'TONE' => $decision === 'publish' ? 'success' : ($decision === 'revise' ? 'warning' : 'danger'),
            'MESSAGE' => $feedback ?: ($decision === 'publish' ? 'Идея опубликована' : 'Идея отклонена'),
            'CREATED_AT' => $now,
        ]);

        return ['success' => true];
    }

    // ------------------- Status Change -------------------

    /**
     * @param int $id
     * @param int $statusId
     * @param string $comment
     * @return array
     */
    public static function changeStatus(int $id, int $statusId, string $comment = ''): array
    {
        $idea = self::getById($id);
        if (!$idea) {
            return ['success' => false, 'error' => 'Идея не найдена'];
        }

        $now = new \Bitrix\Main\Type\DateTime();
        $userId = self::currentUser();

        $status = IdeaStatusTable::getList([
            'filter' => ['=ID' => $statusId],
            'limit' => 1,
        ])->fetch();

        $statusCode = $status['CODE'] ?? 'unknown';

        // Trigger before event
        self::sendEvent('onBeforeIdeaStatusChange', [
            'IDEA_ID' => $id,
            'OLD_STATUS_ID' => $idea['STATUS_ID'],
            'NEW_STATUS_ID' => $statusId,
            'NEW_STATUS_CODE' => $statusCode,
            'COMMENT' => $comment,
        ]);

        self::update($id, [
            'STATUS_ID' => $statusId,
            'STAGE' => $statusCode,
        ]);

        IdeaWorkflowTable::add([
            'IDEA_ID' => $id,
            'USER_ID' => $userId,
            'STATUS' => $statusCode,
            'STAGE' => $statusCode,
            'COMMENT' => $comment,
            'CREATED_AT' => $now,
        ]);

        // Award coins for status changes
        if ($statusCode === self::STATUS_CODE_ACCEPTED) {
            CoinService::award('accepted', $idea['OWNER_USER_ID'], $id);
        } elseif ($statusCode === self::STATUS_CODE_IMPLEMENTED) {
            CoinService::award('implemented', $idea['OWNER_USER_ID'], $id);
        }

        IdeaFeedbackTable::add([
            'IDEA_ID' => $id,
            'USER_ID' => $idea['OWNER_USER_ID'],
            'STAGE' => $statusCode,
            'TONE' => 'info',
            'MESSAGE' => $comment ?: ('Статус изменён на "' . ($status['NAME'] ?? $statusCode) . '"'),
            'CREATED_AT' => $now,
        ]);

        // Trigger after event
        self::sendEvent('onAfterIdeaStatusChange', [
            'IDEA_ID' => $id,
            'NEW_STATUS_ID' => $statusId,
            'NEW_STATUS_CODE' => $statusCode,
            'COMMENT' => $comment,
        ]);

        return ['success' => true];
    }

    // ------------------- Reactions -------------------

    /**
     * @param int $ideaId
     * @param string $type - 'like'|'dislike'
     * @return array
     */
    public static function addReaction(int $ideaId, string $type): array
    {
        $userId = self::currentUser();

        // Remove existing reaction
        $existing = IdeaReactionTable::getList([
            'filter' => ['=IDEA_ID' => $ideaId, '=USER_ID' => $userId],
            'limit' => 1,
        ])->fetch();

        if ($existing) {
            IdeaReactionTable::delete($existing['ID']);
        }

        IdeaReactionTable::add([
            'IDEA_ID' => $ideaId,
            'USER_ID' => $userId,
            'TYPE' => $type,
            'CREATED_AT' => new \Bitrix\Main\Type\DateTime(),
        ]);

        return ['success' => true];
    }

    /**
     * @param int $ideaId
     * @return array
     */
    public static function getReactionCount(int $ideaId): array
    {
        $counts = ['like' => 0, 'dislike' => 0];
        foreach (IdeaReactionTable::getList([
            'select' => ['TYPE', 'CNT' => 'COUNT(*)'],
            'filter' => ['=IDEA_ID' => $ideaId],
            'group' => ['TYPE'],
        ]) as $row) {
            $type = $row['TYPE'] ?? '';
            if (isset($counts[$type])) {
                $counts[$type] = (int)$row['CNT'];
            }
        }

        return $counts;
    }

    // ------------------- Comments -------------------

    /**
     * @param int $ideaId
     * @param string $text
     * @return array
     */
    public static function addComment(int $ideaId, string $text): array
    {
        IdeaCommentTable::add([
            'IDEA_ID' => $ideaId,
            'USER_ID' => self::currentUser(),
            'TEXT' => $text,
            'CREATED_AT' => new \Bitrix\Main\Type\DateTime(),
        ]);

        return ['success' => true];
    }

    /**
     * @param int $ideaId
     * @return array
     */
    public static function getComments(int $ideaId): array
    {
        $items = [];
        foreach (IdeaCommentTable::getList([
            'filter' => ['=IDEA_ID' => $ideaId],
            'order' => ['CREATED_AT' => 'ASC'],
        ]) as $row) {
            $items[] = $row;
        }

        return $items;
    }

    // ------------------- Filters -------------------

    /**
     * @param array $filter
     * @return array
     */
    protected static function applyUserFilter(array $filter): array
    {
        $userId = self::currentUser();

        // If no explicit owner filter, show only user's own ideas + public
        if (!isset($filter['=OWNER_USER_ID']) && !isset($filter['@OWNER_USER_ID'])) {
            $filter['@OWNER_USER_ID'] = $userId;
        }

        return $filter;
    }

    // ------------------- Stats -------------------

    /**
     * @return array
     */
    public static function getStats(): array
    {
        $publishedId = (int)self::getStatusIdByCode(self::STATUS_CODE_PUBLISHED);
        $implementedId = (int)self::getStatusIdByCode(self::STATUS_CODE_IMPLEMENTED);

        $connection = \Bitrix\Main\Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();
        $tableName = $sqlHelper->quote(IdeaTable::getTableName());

        $result = $connection->query("
            SELECT 
                COUNT(*) AS TOTAL,
                SUM(CASE WHEN STATUS_ID = {$publishedId} THEN 1 ELSE 0 END) AS PUBLISHED,
                SUM(CASE WHEN STATUS_ID = {$implementedId} THEN 1 ELSE 0 END) AS IMPLEMENTED
            FROM {$tableName}
        ");

        $row = $result->fetch();

        return [
            'total' => (int)($row['TOTAL'] ?? 0),
            'published' => (int)($row['PUBLISHED'] ?? 0),
            'implemented' => (int)($row['IMPLEMENTED'] ?? 0),
        ];
    }
}
