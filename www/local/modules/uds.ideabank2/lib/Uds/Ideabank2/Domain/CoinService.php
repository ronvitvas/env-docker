<?php

declare(strict_types=1);

namespace Uds\Ideabank2\Domain;

use Uds\Ideabank2\Table\IdeaCoinTable;
use Uds\Ideabank2\Table\IdeaRewardRuleTable;

class CoinService
{
    /**
     * Агрегированные итоги по начислениям/списаниям и балансу
     *
     * @param int $userId
     * @return array{earned:int,spent:int,balance:int}
     */
    public static function getTotals(int $userId): array
    {
        $connection = \Bitrix\Main\Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();
        $tableName = $sqlHelper->quote(IdeaCoinTable::getTableName());

        $result = $connection->query(
            'SELECT '
            . 'COALESCE(SUM(CASE WHEN COINS > 0 THEN COINS ELSE 0 END), 0) AS EARNED, '
            . 'COALESCE(SUM(CASE WHEN COINS < 0 THEN ABS(COINS) ELSE 0 END), 0) AS SPENT, '
            . 'COALESCE(SUM(COINS), 0) AS BALANCE '
            . 'FROM ' . $tableName . ' '
            . 'WHERE USER_ID = ' . (int)$userId
        );

        $row = $result->fetch() ?: [];

        return [
            'earned' => (int)($row['EARNED'] ?? 0),
            'spent' => (int)($row['SPENT'] ?? 0),
            'balance' => (int)($row['BALANCE'] ?? 0),
        ];
    }

    /**
     * Начислить коины по событию
     *
     * @param string $event
     * @param int $userId
     * @param int|null $ideaId
     * @return array
     */
    public static function award(string $event, int $userId, ?int $ideaId = null): array
    {
        // Get reward rule
        $rule = IdeaRewardRuleTable::getList([
            'filter' => ['=EVENT' => $event],
            'limit' => 1,
        ])->fetch();

        if (!$rule) {
            return ['success' => false, 'error' => 'Правило награды не найдено'];
        }

        $coins = (int)$rule['COINS'];
        if ($coins <= 0) {
            return ['success' => true, 'coins' => 0, 'skipped' => true];
        }

        $addResult = IdeaCoinTable::add([
            'USER_ID' => $userId,
            'IDEA_ID' => $ideaId,
            'EVENT' => $event,
            'COINS' => $coins,
            'DESCRIPTION' => $rule['DESCRIPTION'] ?? $rule['LABEL'] ?? '',
            'CREATED_AT' => new \Bitrix\Main\Type\DateTime(),
        ]);

        if (!$addResult->isSuccess()) {
            return [
                'success' => false,
                'error' => 'Failed to award coins: ' . implode('; ', $addResult->getErrorMessages()),
            ];
        }

        return [
            'success' => true,
            'coins' => $coins,
            'event' => $event,
            'label' => $rule['LABEL'] ?? $event,
        ];
    }

    /**
     * Получить баланс пользователя (SQL-агрегация)
     *
     * @param int $userId
     * @return int
     */
    public static function getBalance(int $userId): int
    {
        $connection = \Bitrix\Main\Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();
        $tableName = $sqlHelper->quote(IdeaCoinTable::getTableName());

        $result = $connection->query("
            SELECT COALESCE(SUM(COINS), 0) AS TOTAL
            FROM {$tableName}
            WHERE USER_ID = " . (int)$userId . "
        ");

        $row = $result->fetch();

        return (int)($row['TOTAL'] ?? 0);
    }

    /**
     * Получить историю коинов пользователя
     *
     * @param int $userId
     * @param int $limit
     * @return array
     */
    public static function getHistory(int $userId, int $limit = 50): array
    {
        $items = [];
        foreach (IdeaCoinTable::getList([
            'filter' => ['=USER_ID' => $userId],
            'order' => ['CREATED_AT' => 'DESC'],
            'limit' => $limit,
        ]) as $row) {
            $items[] = $row;
        }

        return $items;
    }

    /**
     * Получить все правила наград
     *
     * @return array
     */
    public static function getRules(): array
    {
        $items = [];
        foreach (IdeaRewardRuleTable::getList([
            'order' => ['ID' => 'ASC'],
        ]) as $row) {
            $items[] = $row;
        }

        return $items;
    }

    /**
     * Лидерборд по коинам (агрегация через SQL для больших датасетов)
     *
     * @param int $limit
     * @return array
     */
    public static function getLeaderboard(int $limit = 10): array
    {
        $limit = max(1, min($limit, 100));

        $connection = \Bitrix\Main\Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();
        $tableName = $sqlHelper->quote(IdeaCoinTable::getTableName());

        $result = $connection->query("
            SELECT USER_ID, SUM(COINS) AS TOTAL_COINS
            FROM {$tableName}
            GROUP BY USER_ID
            ORDER BY TOTAL_COINS DESC
            LIMIT " . (int)$limit . "
        ");

        $rows = $result->fetchAll();

        $resultData = [];
        $rank = 1;
        foreach ($rows as $row) {
            $resultData[] = [
                'USER_ID' => (int)$row['USER_ID'],
                'COINS' => (int)$row['TOTAL_COINS'],
                'RANK' => $rank,
            ];
            $rank++;
        }

        return $resultData;
    }
}