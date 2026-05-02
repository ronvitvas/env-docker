<?php
declare(strict_types=1);

namespace Uds\Ideabank2\Domain;

use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use Uds\Ideabank2\Table\IdeaChallengePartTable;
use Uds\Ideabank2\Table\IdeaChallengeTable;
use Uds\Ideabank2\Table\IdeaTable;

class ChallengeService
{
    public static function create(array $data): int
    {
        $connection = Application::getConnection();
        return (int)$connection->insert('b_uds_ideabank_idea_challenge', [
            'TITLE' => $data['TITLE'] ?? '',
            'PERIOD' => $data['PERIOD'] ?? '',
            'TARGET' => $data['TARGET'] ?? '',
            'REWARD_BONUS' => (int)($data['REWARD_BONUS'] ?? 0),
            'BUSINESS_DIRECTION' => $data['BUSINESS_DIRECTION'] ?? '',
            'TIPS' => $data['TIPS'] ?? '',
        ]);
    }

    public static function update(int $id, array $data): bool
    {
        $fields = [];
        foreach (['TITLE','PERIOD','TARGET','REWARD_BONUS','BUSINESS_DIRECTION','TIPS'] as $f) {
            if (isset($data[$f])) $fields[$f] = $data[$f];
        }
        if (empty($fields)) return false;
        $connection = Application::getConnection();
        $connection->update('b_uds_ideabank_idea_challenge', $fields, ['ID' => $id]);
        return true;
    }

    public static function delete(int $id): bool
    {
        $connection = Application::getConnection();
        $connection->delete('b_uds_ideabank_idea_challenge_part', ['CHALLENGE_ID' => $id]);
        return (bool)$connection->delete('b_uds_ideabank_idea_challenge', ['ID' => $id]);
    }

    public static function getList(array $filter = []): \Bitrix\Main\ORM\Query\Result
    {
        return IdeaChallengeTable::getList([
            'filter' => $filter,
            'order' => ['ID' => 'DESC'],
        ]);
    }

    public static function getOne(int $id): ?array
    {
        return IdeaChallengeTable::getByPrimary($id)->fetch() ?: null;
    }

    public static function addIdea(int $challengeId, int $ideaId, int $userId = 0): bool
    {
        if ($challengeId <= 0 || $ideaId <= 0) {
            return false;
        }

        if (!self::getOne($challengeId)) {
            return false;
        }

        if ($userId <= 0) {
            $idea = IdeaTable::getByPrimary($ideaId, ['select' => ['OWNER_USER_ID']])->fetch();
            $userId = is_array($idea) ? (int)($idea['OWNER_USER_ID'] ?? 0) : 0;
        }

        if ($userId <= 0) {
            $userId = IdeaService::currentUser();
        }

        $existing = IdeaChallengePartTable::getList([
            'filter' => [
                '=CHALLENGE_ID' => $challengeId,
                '=IDEA_ID' => $ideaId,
            ],
            'limit' => 1,
        ])->fetch();

        if (is_array($existing)) {
            return true;
        }

        $result = IdeaChallengePartTable::add([
            'CHALLENGE_ID' => $challengeId,
            'USER_ID' => max(0, $userId),
            'IDEA_ID' => $ideaId,
            'CREATED_AT' => new DateTime(),
        ]);

        return $result->isSuccess();
    }

    public static function setIdeaChallenge(int $ideaId, int $challengeId, int $userId = 0): bool
    {
        if ($ideaId <= 0) {
            return false;
        }

        $connection = Application::getConnection();
        $connection->delete('b_uds_ideabank_idea_challenge_part', ['IDEA_ID' => $ideaId]);

        if ($challengeId <= 0) {
            return true;
        }

        return self::addIdea($challengeId, $ideaId, $userId);
    }

    public static function getChallengeIdForIdea(int $ideaId): int
    {
        if ($ideaId <= 0) {
            return 0;
        }

        $row = IdeaChallengePartTable::getList([
            'select' => ['CHALLENGE_ID'],
            'filter' => ['=IDEA_ID' => $ideaId],
            'order' => ['ID' => 'DESC'],
            'limit' => 1,
        ])->fetch();

        return is_array($row) ? (int)($row['CHALLENGE_ID'] ?? 0) : 0;
    }

    public static function getStatsForChallenges(array $challengeIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $challengeIds), static fn(int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();
        $partTable = $sqlHelper->quote('b_uds_ideabank_idea_challenge_part');
        $ideaTable = $sqlHelper->quote('b_uds_ideabank_idea');
        $statusTable = $sqlHelper->quote('b_uds_ideabank_idea_status');
        $idList = implode(',', $ids);

        $rows = $connection->query(
            'SELECT p.CHALLENGE_ID AS CHALLENGE_ID,'
            . ' COUNT(p.ID) AS TOTAL,'
            . " SUM(CASE WHEN COALESCE(s.CODE, i.STAGE) IN ('published','moderation','initial_review','kpu') THEN 1 ELSE 0 END) AS PUBLISHED,"
            . " SUM(CASE WHEN COALESCE(s.CODE, i.STAGE) IN ('accepted','implementation','transferred') THEN 1 ELSE 0 END) AS ACCEPTED,"
            . " SUM(CASE WHEN COALESCE(s.CODE, i.STAGE) IN ('implemented','deployed') THEN 1 ELSE 0 END) AS IMPLEMENTED"
            . ' FROM ' . $partTable . ' p'
            . ' INNER JOIN ' . $ideaTable . ' i ON i.ID = p.IDEA_ID'
            . ' LEFT JOIN ' . $statusTable . ' s ON s.ID = i.STATUS_ID'
            . ' WHERE p.CHALLENGE_ID IN (' . $idList . ')'
            . ' GROUP BY p.CHALLENGE_ID'
        )->fetchAll();

        $stats = [];
        foreach ($ids as $id) {
            $stats[$id] = self::emptyStats();
        }

        foreach ($rows as $row) {
            $id = (int)($row['CHALLENGE_ID'] ?? 0);
            $stats[$id] = [
                'total' => (int)($row['TOTAL'] ?? 0),
                'items' => [
                    ['label' => 'обсуждаются', 'value' => (int)($row['PUBLISHED'] ?? 0)],
                    ['label' => 'приняты', 'value' => (int)($row['ACCEPTED'] ?? 0)],
                    ['label' => 'реализованы', 'value' => (int)($row['IMPLEMENTED'] ?? 0)],
                ],
            ];
        }

        return $stats;
    }

    private static function emptyStats(): array
    {
        return [
            'total' => 0,
            'items' => [
                ['label' => 'обсуждаются', 'value' => 0],
                ['label' => 'приняты', 'value' => 0],
                ['label' => 'реализованы', 'value' => 0],
            ],
        ];
    }
}
