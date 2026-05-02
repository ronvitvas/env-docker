<?php

declare(strict_types=1);

namespace Uds\Ideabank2\Domain;

use Bitrix\Main\Config\Option;
use Bitrix\Main\SystemException;
use Bitrix\Main\Type\DateTime;
use Uds\Ideabank2\Table\IdeaExpertReviewTable;
use Uds\Ideabank2\Table\IdeaTable;

class ExpertReviewService
{
    public const MODULE_ID = 'uds.ideabank2';

    /**
     * Проверить, включена ли экспертная оценка
     */
    public static function isEnabled(): bool
    {
        return Option::get(self::MODULE_ID, 'enable_expert_review', 'N') === 'Y';
    }

    /**
     * Добавить экспертную оценку
     *
     * @param int $ideaId
     * @param int $expertUserId
     * @param int $score
     * @param string $comment
     * @return int ID созданной оценки
     */
    public static function addReview(int $ideaId, int $expertUserId, int $score, string $comment = ''): int
    {
        if (!self::isEnabled()) {
            throw new SystemException('Expert review is disabled', 0, null, 'expert_review_disabled');
        }

        $idea = IdeaTable::getList([
            'filter' => ['=ID' => $ideaId],
            'limit' => 1,
        ])->fetch();

        if (!$idea) {
            throw new SystemException('Idea not found: ' . $ideaId, 0, null, 'idea_not_found');
        }

        // Validate score range
        $score = max(0, min(100, $score));

        // Remove existing review by this expert
        $existing = IdeaExpertReviewTable::getList([
            'filter' => [
                '=IDEA_ID' => $ideaId,
                '=EXPERT_USER_ID' => $expertUserId,
            ],
            'limit' => 1,
        ])->fetch();

        if ($existing) {
            $result = IdeaExpertReviewTable::delete((int)$existing['ID']);
            if (!$result->isSuccess()) {
                throw new SystemException(
                    'Failed to delete existing review: ' . implode('; ', $result->getErrorMessages()),
                    0,
                    null,
                    'review_delete_failed'
                );
            }
        }

        $addResult = IdeaExpertReviewTable::add([
            'IDEA_ID' => $ideaId,
            'EXPERT_USER_ID' => $expertUserId,
            'SCORE' => $score,
            'COMMENT' => $comment,
            'CREATED_AT' => new DateTime(),
        ]);

        if (!$addResult->isSuccess()) {
            throw new SystemException(
                'Failed to create review: ' . implode('; ', $addResult->getErrorMessages()),
                0,
                null,
                'review_create_failed'
            );
        }

        return (int)$addResult->getId();
    }

    /**
     * Получить все оценки для идеи
     *
     * @param int $ideaId
     * @return array
     */
    public static function getReviewsForIdea(int $ideaId): array
    {
        $items = IdeaExpertReviewTable::getList([
            'filter' => ['=IDEA_ID' => $ideaId],
            'order' => ['CREATED_AT' => 'DESC'],
        ])->fetchAll();

        return $items;
    }

    /**
     * Получить среднюю оценку для идеи
     *
     * @param int $ideaId
     * @return float
     */
    public static function getAverageScore(int $ideaId): float
    {
        $row = IdeaExpertReviewTable::getList([
            'select' => ['AVG_SCORE' => 'AVG(SCORE)'],
            'filter' => ['=IDEA_ID' => $ideaId],
        ])->fetch();

        return (float)($row['AVG_SCORE'] ?? 0);
    }

    /**
     * Получить количество оценок для идеи
     *
     * @param int $ideaId
     * @return int
     */
    public static function getReviewCount(int $ideaId): int
    {
        return (int)IdeaExpertReviewTable::getCount([
            '=IDEA_ID' => $ideaId,
        ]);
    }

    /**
     * Получить оценку эксперта для идеи
     *
     * @param int $ideaId
     * @param int $expertUserId
     * @return array|null
     */
    public static function getReviewByExpert(int $ideaId, int $expertUserId): ?array
    {
        $review = IdeaExpertReviewTable::getList([
            'filter' => [
                '=IDEA_ID' => $ideaId,
                '=EXPERT_USER_ID' => $expertUserId,
            ],
            'limit' => 1,
        ])->fetch();

        return $review ?: null;
    }

    /**
     * Получить идеи, ожидающие оценки экспертом
     *
     * @param int $expertUserId
     * @param int $limit
     * @return array
     */
    public static function getPendingReviews(int $expertUserId, int $limit = 20): array
    {
        // Get ideas that don't have a review from this expert yet
        $reviewedIdeaIds = IdeaExpertReviewTable::getList([
            'select' => ['IDEA_ID'],
            'filter' => ['=EXPERT_USER_ID' => $expertUserId],
        ])->fetchAll();

        $excludeIds = array_column($reviewedIdeaIds, 'IDEA_ID');

        $filter = ['!=STATUS_ID' => 0]; // published ideas
        if (!empty($excludeIds)) {
            $filter['!ID'] = $excludeIds;
        }

        $items = IdeaTable::getList([
            'select' => ['ID', 'TITLE', 'STATUS_ID', 'CREATED_AT'],
            'filter' => $filter,
            'order' => ['CREATED_AT' => 'DESC'],
            'limit' => max(1, min($limit, 100)),
        ])->fetchAll();

        return $items;
    }
}