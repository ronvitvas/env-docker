<?php

declare(strict_types=1);

namespace Uds\Ideabank2\Domain;

use Bitrix\Main\Config\Option;
use Bitrix\Main\SystemException;
use Bitrix\Main\Type\DateTime;
use Uds\Ideabank2\Table\IdeaCommitteeTable;
use Uds\Ideabank2\Table\IdeaTable;

class CommitteeService
{
    public const MODULE_ID = 'uds.ideabank2';

    public const DECISION_APPROVE = 'approve';
    public const DECISION_REJECT = 'reject';
    public const DECISION_DEFER = 'defer';
    public const DECISION_REVISE = 'revise';

    public const VALID_DECISIONS = [
        self::DECISION_APPROVE,
        self::DECISION_REJECT,
        self::DECISION_DEFER,
        self::DECISION_REVISE,
    ];

    /**
     * Проверить, включен ли комитет
     */
    public static function isEnabled(): bool
    {
        return Option::get(self::MODULE_ID, 'enable_committee', 'N') === 'Y';
    }

    /**
     * Добавить решение комитета
     *
     * @param int $ideaId
     * @param int $userId
     * @param string $decision
     * @param string $summary
     * @return int ID созданного решения
     */
    public static function addDecision(int $ideaId, int $userId, string $decision, string $summary = ''): int
    {
        if (!self::isEnabled()) {
            throw new SystemException('Committee is disabled', 0, null, 'committee_disabled');
        }

        if (!in_array($decision, self::VALID_DECISIONS, true)) {
            throw new SystemException('Invalid decision: ' . $decision, 0, null, 'invalid_decision');
        }

        $idea = IdeaTable::getList([
            'filter' => ['=ID' => $ideaId],
            'limit' => 1,
        ])->fetch();

        if (!$idea) {
            throw new SystemException('Idea not found: ' . $ideaId, 0, null, 'idea_not_found');
        }

        $addResult = IdeaCommitteeTable::add([
            'IDEA_ID' => $ideaId,
            'USER_ID' => $userId,
            'DECISION' => $decision,
            'SUMMARY' => $summary,
            'DECIDED_AT' => new DateTime(),
        ]);

        if (!$addResult->isSuccess()) {
            throw new SystemException(
                'Failed to create committee decision: ' . implode('; ', $addResult->getErrorMessages()),
                0,
                null,
                'decision_create_failed'
            );
        }

        return (int)$addResult->getId();
    }

    /**
     * Получить все решения комитета для идеи
     *
     * @param int $ideaId
     * @return array
     */
    public static function getDecisionsForIdea(int $ideaId): array
    {
        $items = IdeaCommitteeTable::getList([
            'filter' => ['=IDEA_ID' => $ideaId],
            'order' => ['DECIDED_AT' => 'DESC'],
        ])->fetchAll();

        return $items;
    }

    /**
     * Получить последнее решение для идеи
     *
     * @param int $ideaId
     * @return array|null
     */
    public static function getLatestDecision(int $ideaId): ?array
    {
        $decision = IdeaCommitteeTable::getList([
            'filter' => ['=IDEA_ID' => $ideaId],
            'order' => ['DECIDED_AT' => 'DESC'],
            'limit' => 1,
        ])->fetch();

        return $decision ?: null;
    }

    /**
     * Получить количество решений для идеи
     *
     * @param int $ideaId
     * @return int
     */
    public static function getDecisionCount(int $ideaId): int
    {
        return (int)IdeaCommitteeTable::getCount([
            '=IDEA_ID' => $ideaId,
        ]);
    }

    /**
     * Получить идеи на рассмотрении комитета (без решений)
     *
     * @param int $limit
     * @return array
     */
    public static function getPendingIdeas(int $limit = 20): array
    {
        $limit = max(1, min($limit, 100));

        // Get idea IDs that already have committee decisions
        $decidedIdeaIds = IdeaCommitteeTable::getList([
            'select' => ['IDEA_ID'],
        ])->fetchAll();

        $excludeIds = array_column($decidedIdeaIds, 'IDEA_ID');

        $filter = ['!=STATUS_ID' => 0];
        if (!empty($excludeIds)) {
            $filter['!ID'] = $excludeIds;
        }

        $items = IdeaTable::getList([
            'select' => ['ID', 'TITLE', 'STATUS_ID', 'CREATED_AT'],
            'filter' => $filter,
            'order' => ['CREATED_AT' => 'DESC'],
            'limit' => $limit,
        ])->fetchAll();

        return $items;
    }

    /**
     * Проверить, есть ли решение для пользователя и идеи
     *
     * @param int $ideaId
     * @param int $userId
     * @return bool
     */
    public static function hasDecisionByUser(int $ideaId, int $userId): bool
    {
        $count = IdeaCommitteeTable::getCount([
            '=IDEA_ID' => $ideaId,
            '=USER_ID' => $userId,
        ]);

        return $count > 0;
    }
}