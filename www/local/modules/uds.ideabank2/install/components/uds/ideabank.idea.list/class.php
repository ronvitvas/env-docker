<?php
declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\UserTable;
use Uds\Ideabank2\Domain\PublicDataService;

final class UdsIdeabankIdeaListComponent extends CBitrixComponent
{
    private const DEFAULT_MODE = 'all';

    public function executeComponent(): void
    {
        if (!Loader::includeModule('uds.ideabank2')) {
            ShowError('Модуль uds.ideabank2 не установлен');
            return;
        }

        $query = Application::getInstance()->getContext()->getRequest()->getQueryList()->toArray();
        $domainData = PublicDataService::getIdeaListData($query);

        $this->arResult = $this->buildManagementViewModel($domainData, $query);
        $this->includeComponentTemplate();
    }

    private function buildManagementViewModel(array $domainData, array $query): array
    {
        $items = is_array($domainData['items'] ?? null) ? $domainData['items'] : [];
        $statuses = is_array($domainData['statuses'] ?? null) ? $domainData['statuses'] : [];
        $categories = is_array($domainData['categories'] ?? null) ? $domainData['categories'] : [];
        $pagination = is_array($domainData['pagination'] ?? null) ? $domainData['pagination'] : [];
        $runtimeMeta = is_array($domainData['meta'] ?? null) ? $domainData['meta'] : PublicDataService::getRuntimeMeta();
        $permissions = is_array($runtimeMeta['permissions'] ?? null) ? $runtimeMeta['permissions'] : [];
        $mode = $this->resolveMode((string)($domainData['mode'] ?? $query['mode'] ?? self::DEFAULT_MODE));
        $search = trim((string)($domainData['search'] ?? $query['q'] ?? ''));
        $preparedItems = $this->prepareIdeaCards($items, $this->loadUserProfiles($this->collectUserIds($items)), $permissions);
        $modeTabs = $this->buildModeTabs($mode, $query, $permissions);
        $filters = $this->buildFilters($query, $search, $statuses, $categories);
        $summary = $this->buildSummary($preparedItems, $pagination, $mode, $filters);

        return [
            'shell' => is_array($domainData['shell'] ?? null) ? $domainData['shell'] : PublicDataService::getShellData('management'),
            'page' => [
                'title' => 'Реестр инициатив',
                'subtitle' => 'Рабочий реестр инициатив, черновиков, лучших практик и очередей рассмотрения.',
                'eyebrow' => 'Операционный контур',
                'description' => 'Фильтруйте идеи по роли, статусу, категории и тексту. Открывайте карточку для обсуждения и маршрута.',
                'actions' => array_values(array_filter([
                    !empty($permissions['canSubmitIdea']) ? ['title' => 'Создать идею', 'url' => '/ideabank/ppu-form.php', 'primary' => true] : null,
                    ['title' => 'Статистика', 'url' => '/ideabank/stats.php?scope=general'],
                ])),
                'modeTabs' => $modeTabs,
                'items' => $preparedItems,
                'empty' => $this->buildEmptyState($mode, $search, $permissions),
            ],
            'widgets' => [
                'filters' => $filters,
                'summary' => $summary,
                'views' => $this->buildViewCards($modeTabs, $summary),
                'operations' => $this->buildOperationCards($summary),
            ],
            'meta' => [
                'mode' => $mode,
                'search' => $search,
                'query' => $query,
                'pagination' => $pagination,
                'hasItems' => $preparedItems !== [],
            ] + $runtimeMeta,
            // backward compatibility
            'items' => $preparedItems,
            'statuses' => $statuses,
            'categories' => $categories,
            'query' => $query,
            'mode' => $mode,
            'search' => $search,
            'pagination' => $pagination,
        ];
    }

    private function resolveMode(string $mode): string
    {
        return in_array($mode, ['all', 'mine', 'drafts', 'best', 'moderation'], true) ? $mode : self::DEFAULT_MODE;
    }

    private function buildModeTabs(string $currentMode, array $query, array $permissions): array
    {
        $titles = [
            'all' => ['title' => 'Все', 'caption' => 'Полный реестр инициатив'],
            'mine' => ['title' => 'Мои', 'caption' => 'Идеи текущего автора'],
            'drafts' => ['title' => 'Черновики', 'caption' => 'Заявки в подготовке'],
            'best' => ['title' => 'Лучшие', 'caption' => 'Реализованные практики'],
            'moderation' => ['title' => 'Модерация', 'caption' => 'Очередь первичной проверки'],
        ];

        if (empty($permissions['canUseDrafts'])) {
            unset($titles['drafts']);
        }
        if (empty($permissions['canViewModeration'])) {
            unset($titles['moderation']);
        }

        $tabs = [];
        foreach ($titles as $mode => $data) {
            $tabs[] = [
                'code' => $mode,
                'title' => $data['title'],
                'caption' => $data['caption'],
                'active' => $mode === $currentMode,
                'url' => '?' . http_build_query(array_merge($query, ['mode' => $mode, 'page' => 1])),
            ];
        }

        return $tabs;
    }

    private function buildFilters(array $query, string $search, array $statuses, array $categories): array
    {
        return [
            'search' => $search,
            'statusId' => (int)($query['status_id'] ?? 0),
            'categoryId' => (int)($query['category_id'] ?? 0),
            'statuses' => $statuses,
            'categories' => $categories,
            'resetUrl' => '?' . http_build_query(array_merge($query, [
                'q' => '',
                'status_id' => '',
                'category_id' => '',
                'page' => 1,
            ])),
        ];
    }

    private function buildSummary(array $items, array $pagination, string $mode, array $filters): array
    {
        $total = (int)($pagination['total'] ?? count($items));
        $activeFilters = 0;
        foreach (['search', 'statusId', 'categoryId'] as $key) {
            if (!empty($filters[$key])) {
                $activeFilters++;
            }
        }

        return [
            'total' => $total,
            'shown' => count($items),
            'activeFilters' => $activeFilters,
            'mode' => $mode,
            'page' => (int)($pagination['page'] ?? 1),
            'pages' => (int)($pagination['pages'] ?? 1),
        ];
    }

    private function buildViewCards(array $modeTabs, array $summary): array
    {
        $cards = [];
        foreach ($modeTabs as $tab) {
            $cards[] = [
                'title' => $tab['title'],
                'text' => $tab['caption'],
                'url' => $tab['url'],
                'active' => $tab['active'],
                'badge' => $tab['active'] ? (string)$summary['total'] : '',
            ];
        }

        return $cards;
    }

    private function buildOperationCards(array $summary): array
    {
        return [
            ['label' => 'Найдено', 'value' => $summary['total'], 'caption' => 'Всего записей по текущим условиям'],
            ['label' => 'На странице', 'value' => $summary['shown'], 'caption' => 'Карточек в текущей выдаче'],
            ['label' => 'Фильтры', 'value' => $summary['activeFilters'], 'caption' => 'Активных условий отбора'],
        ];
    }

    private function buildEmptyState(string $mode, string $search, array $permissions): array
    {
        $text = $search !== ''
            ? 'По текущему поиску ничего не найдено. Попробуйте изменить запрос или сбросить фильтры.'
            : 'В выбранном режиме пока нет идей.';

        if ($mode === 'drafts') {
            $text = 'Черновиков пока нет. Создайте новую идею и сохраните её для последующей отправки.';
        } elseif ($mode === 'moderation') {
            $text = 'Очередь модерации пуста — новых идей для первичной проверки нет.';
        }

        $emptyState = [
            'title' => 'Нет идей для отображения',
            'text' => $text,
        ];

        if (!empty($permissions['canSubmitIdea'])) {
            $emptyState['action'] = ['title' => 'Создать идею', 'url' => '/ideabank/ppu-form.php'];
        }

        return $emptyState;
    }

    private function prepareIdeaCards(array $items, array $profiles, array $permissions): array
    {
        $prepared = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $ownerUserId = (int)($item['OWNER_USER_ID'] ?? $item['USER_ID'] ?? 0);
            $profile = $profiles[$ownerUserId] ?? [];
            $item['OWNER_LABEL'] = $this->resolveFullName($profile, $ownerUserId > 0 ? 'Пользователь ' . $ownerUserId : 'Автор идеи');
            $item['OWNER_ROLE'] = trim((string)($profile['WORK_POSITION'] ?? '')) ?: 'Участник банка идей';
            $item['EXCERPT'] = trim((string)($item['DESCRIPTION'] ?? $item['PROBLEM'] ?? $item['PROPOSAL'] ?? ''));
            $item['DETAIL_URL'] = '/ideabank/ppu-detail.php?id=' . (int)($item['ID'] ?? 0);
            $item['EDIT_URL'] = '/ideabank/ppu-form.php?id=' . (int)($item['ID'] ?? 0);
            $item['ROUTE_LABEL'] = $this->resolveRouteLabel((string)($item['STAGE'] ?? ''), is_array($item['STATUS'] ?? null) ? $item['STATUS'] : []);
            $item['ACTIONS'] = $this->buildItemActions($item, $permissions);
            $prepared[] = $item;
        }

        return $prepared;
    }

    private function buildItemActions(array $item, array $permissions): array
    {
        $actions = [
            ['title' => 'Открыть карточку', 'url' => (string)($item['DETAIL_URL'] ?? ('/ideabank/ppu-detail.php?id=' . (int)($item['ID'] ?? 0)))],
        ];
        $isOwn = (int)($item['OWNER_USER_ID'] ?? $item['USER_ID'] ?? 0) === (int)($permissions['userId'] ?? 0);
        $isDraft = (string)($item['IS_DRAFT'] ?? 'N') === 'Y' || (string)($item['STAGE'] ?? '') === 'draft';
        $canEdit = ($isOwn && $isDraft && !empty($permissions['canUseDrafts']))
            || ($isOwn && !empty($permissions['canEditAfterSubmit']))
            || !empty($permissions['canAdminIdeabank']);

        if ($canEdit) {
            $actions[] = ['title' => 'Редактировать', 'url' => (string)($item['EDIT_URL'] ?? ('/ideabank/ppu-form.php?id=' . (int)($item['ID'] ?? 0)))];
        }

        return $actions;
    }

    private function collectUserIds(array $items): array
    {
        $userIds = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $userIds[] = (int)($item['OWNER_USER_ID'] ?? $item['USER_ID'] ?? 0);
            }
        }

        return array_values(array_unique(array_filter($userIds, static fn(int $userId): bool => $userId > 0)));
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

    private function resolveRouteLabel(string $stage, array $status): string
    {
        $labels = [
            'draft' => 'Черновик',
            'moderation' => 'Модерация',
            'published' => 'Обсуждение',
            'initial_review' => 'Экспертиза',
            'kpu' => 'Комитет',
            'implementation' => 'В реализации',
            'implemented' => 'Реализовано',
            'accepted' => 'Принято в работу',
        ];

        return $labels[$stage] ?? (string)($status['NAME'] ?? 'Маршрут не задан');
    }

    private function resolveFullName(array $profile, string $fallback): string
    {
        $name = trim(implode(' ', array_filter([
            (string)($profile['NAME'] ?? ''),
            (string)($profile['SECOND_NAME'] ?? ''),
            (string)($profile['LAST_NAME'] ?? ''),
        ])));

        return $name !== '' ? $name : $fallback;
    }
}
