<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\UserTable;
use Uds\Ideabank2\Domain\PublicDataService;
use Uds\Ideabank2\Table\IdeaTable;

final class UdsIdeabankHallComponent extends CBitrixComponent
{
    private const PAGE_SIZE = 9;
    private const MODE_AUTHOR = 'author';
    private const MODE_USAGE = 'usage';

    public function executeComponent(): void
    {
        if (!Loader::includeModule('uds.ideabank2')) {
            ShowError('Модуль uds.ideabank2 не установлен');
            return;
        }

        $domainData = PublicDataService::getHallData();
        $query = Application::getInstance()->getContext()->getRequest()->getQueryList()->toArray();
        $mode = $this->resolveMode((string)($query['mode'] ?? ''));
        $items = $this->prepareItems(
            $mode === self::MODE_USAGE
                ? $this->loadReplicationLeaderboard(50)
                : (is_array($domainData['items'] ?? null) ? $domainData['items'] : []),
            $mode
        );
        $page = max(1, (int)($query['page'] ?? 1));
        $total = count($items);
        $pages = max(1, (int)ceil($total / self::PAGE_SIZE));
        $currentPage = min($page, $pages);
        $visibleItems = array_slice($items, ($currentPage - 1) * self::PAGE_SIZE, self::PAGE_SIZE);
        $isUsageMode = $mode === self::MODE_USAGE;

        $this->arResult = [
            'shell' => is_array($domainData['shell'] ?? null) ? $domainData['shell'] : [],
            'page' => [
                'title' => 'Аллея славы',
                'subtitle' => $isUsageMode
                    ? 'Рейтинг сотрудников, чьи идеи чаще берут за основу и тиражируют на другие участки.'
                    : 'Рейтинг авторов и амбассадоров улучшений по коинам.',
                'eyebrow' => 'Лидеры банка идей',
                'description' => $isUsageMode
                    ? 'Показываем сотрудников, чьи идеи чаще берут за основу и тиражируют на другие участки.'
                    : 'Показываем лидеров по накопленным коинам за инициативность, качество оформления и доведение идей до результата.',
                'items' => $visibleItems,
            ],
            'widgets' => [
                'summary' => [
                    'total' => $total,
                    'shown' => count($visibleItems),
                    'topCoins' => (int)($items[0]['SCORE'] ?? $items[0]['COINS'] ?? 0),
                    'topLabel' => $isUsageMode ? 'Лидер по тиражированию' : 'Баланс лидера',
                    'scoreCaption' => $isUsageMode ? 'коинов за тиражирование' : 'в рейтинге',
                ],
            ],
            'meta' => [
                'mode' => $mode,
                'tabs' => [
                    ['code' => self::MODE_AUTHOR, 'title' => 'Коины авторов'],
                    ['code' => self::MODE_USAGE, 'title' => 'Коины за тиражирование'],
                ],
                'hasItems' => $items !== [],
                'emptyMessage' => $isUsageMode ? 'Рейтинг по тиражированию пока пуст.' : 'Рейтинг пока пуст.',
                'pagination' => [
                    'page' => $currentPage,
                    'pageSize' => self::PAGE_SIZE,
                    'total' => $total,
                    'pages' => $pages,
                ],
            ],
            // backward compatibility
            'items' => $visibleItems,
        ];

        $this->includeComponentTemplate();
    }

    private function resolveMode(string $mode): string
    {
        return $mode === self::MODE_USAGE ? self::MODE_USAGE : self::MODE_AUTHOR;
    }

    private function prepareItems(array $items, string $mode): array
    {
        $prepared = [];
        $rank = 1;
        $profiles = $this->loadUserProfiles($this->collectUserIds($items));

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $userId = (int)($item['USER_ID'] ?? 0);
            $profile = $profiles[$userId] ?? [];
            $score = (int)($item['SCORE'] ?? $item['TOTAL_COINS'] ?? $item['COINS'] ?? 0);

            $item['RANK'] = (int)($item['RANK'] ?? $item['PLACE'] ?? $rank);
            $item['SCORE'] = $score;
            $item['COINS'] = $score;
            $item['USER_LABEL'] = $this->resolveUserLabel($item, $profile);
            $item['ROLE_LABEL'] = $this->resolveUserRole($item, $profile, $mode);
            $item['USER_INITIALS'] = $this->resolveInitials($profile, $item['USER_LABEL']);
            $item['IDEA_COUNT'] = (int)($item['IDEA_COUNT'] ?? 0);
            $prepared[] = $item;
            $rank++;
        }

        return $prepared;
    }

    private function collectUserIds(array $items): array
    {
        $userIds = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $userId = (int)($item['USER_ID'] ?? 0);
            if ($userId > 0) {
                $userIds[] = $userId;
            }
        }

        return array_values(array_unique($userIds));
    }

    private function loadUserProfiles(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows = UserTable::getList([
            'select' => ['ID', 'NAME', 'LAST_NAME', 'SECOND_NAME', 'WORK_POSITION'],
            'filter' => ['@ID' => $userIds],
        ])->fetchAll();

        $profiles = [];
        foreach ($rows as $row) {
            $profiles[(int)$row['ID']] = $row;
        }

        return $profiles;
    }

    private function resolveUserLabel(array $item, array $profile = []): string
    {
        $profileName = trim(implode(' ', array_filter([
            (string)($profile['NAME'] ?? ''),
            (string)($profile['SECOND_NAME'] ?? ''),
            (string)($profile['LAST_NAME'] ?? ''),
        ])));
        if ($profileName !== '') {
            return $profileName;
        }

        $name = trim((string)($item['USER_NAME'] ?? $item['NAME'] ?? $item['FULL_NAME'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $userId = (int)($item['USER_ID'] ?? 0);
        return $userId > 0 ? 'Пользователь ' . $userId : 'Участник';
    }

    private function resolveUserRole(array $item, array $profile, string $mode): string
    {
        $role = trim((string)($profile['WORK_POSITION'] ?? $item['ROLE'] ?? $item['USER_ROLE'] ?? ''));
        if ($role !== '') {
            return $role;
        }

        return $mode === self::MODE_USAGE ? 'Автор тиражируемых практик' : 'Участник банка идей';
    }

    private function resolveInitials(array $profile, string $fallbackName): string
    {
        $parts = array_values(array_filter([
            trim((string)($profile['NAME'] ?? '')),
            trim((string)($profile['LAST_NAME'] ?? '')),
        ]));

        if ($parts === []) {
            $parts = preg_split('/\s+/u', trim($fallbackName)) ?: [];
        }

        $initials = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper((string)mb_substr($part, 0, 1));
        }

        return $initials !== '' ? $initials : 'У';
    }

    private function loadReplicationLeaderboard(int $limit): array
    {
        $limit = max(1, min($limit, 100));

        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();
        $tableName = $sqlHelper->quote(IdeaTable::getTableName());
        $rows = $connection->query(
            'SELECT OWNER_USER_ID AS USER_ID, '
            . 'COALESCE(SUM(REPLICATION_COIN_REWARD), 0) AS TOTAL_COINS, '
            . 'COUNT(*) AS IDEA_COUNT '
            . 'FROM ' . $tableName . ' '
            . 'WHERE COALESCE(REPLICATION_COIN_REWARD, 0) > 0 '
            . 'GROUP BY OWNER_USER_ID '
            . 'ORDER BY TOTAL_COINS DESC '
            . 'LIMIT ' . $limit
        )->fetchAll();

        $result = [];
        $rank = 1;
        foreach ($rows as $row) {
            $result[] = [
                'USER_ID' => (int)($row['USER_ID'] ?? 0),
                'TOTAL_COINS' => (int)($row['TOTAL_COINS'] ?? 0),
                'IDEA_COUNT' => (int)($row['IDEA_COUNT'] ?? 0),
                'RANK' => $rank,
            ];
            $rank++;
        }

        return $result;
    }
}
