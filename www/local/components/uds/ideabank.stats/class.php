<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Uds\Ideabank2\Domain\PublicDataService;
use Uds\Ideabank2\Table\IdeaTable;

final class UdsIdeabankStatsComponent extends CBitrixComponent
{
    private const SCOPE_GENERAL = 'general';
    private const SCOPE_MINE = 'mine';

    public function executeComponent(): void
    {
        if (!Loader::includeModule('uds.ideabank2')) {
            ShowError('Модуль uds.ideabank2 не установлен');
            return;
        }

        $query = Application::getInstance()->getContext()->getRequest()->getQueryList()->toArray();
        $scope = $this->resolveScope((string)($query['scope'] ?? ''));
        $selectedCategoryId = max(0, (int)($query['category_id'] ?? 0));
        $domainData = PublicDataService::getStatsData();
        $statusDictionary = $this->indexById(PublicDataService::getStatuses());
        $categoryDictionary = $this->indexById(PublicDataService::getCategories());
        $rows = $this->loadIdeaRows($scope, $selectedCategoryId);
        $snapshot = $this->buildSnapshot($rows, $statusDictionary, $categoryDictionary);

        $this->arResult = [
            'shell' => is_array($domainData['shell'] ?? null) ? $domainData['shell'] : [],
            'page' => [
                'title' => 'Статистика',
                'subtitle' => $scope === self::SCOPE_MINE
                    ? 'Фокус на инициативах, где вы участвуете как автор или соавтор.'
                    : 'Воронка идей, распределение по статусам и категориям.',
                'eyebrow' => 'Аналитика портала',
                'description' => $scope === self::SCOPE_MINE
                    ? 'Персональный срез по вашим инициативам, прогрессу и эффекту.'
                    : 'Сводный срез по всем идеям и статусам в банке идей.',
                'metrics' => $this->buildPrimaryMetrics($snapshot),
                'secondaryMetrics' => $this->buildSecondaryMetrics($snapshot),
                'filters' => [
                    'scope' => $scope,
                    'categoryId' => $selectedCategoryId,
                ],
            ],
            'widgets' => [
                'filters' => [
                    'activeSlice' => $scope === self::SCOPE_MINE ? 'Мои инициативы' : 'Вся база идей',
                    'totalIdeas' => $snapshot['total'],
                    'potentialEffect' => $snapshot['potentialEffect'],
                ],
                'charts' => [
                    'statuses' => $snapshot['statusRows'],
                    'categories' => $snapshot['categoryRows'],
                    'funnel' => $snapshot['funnelRows'],
                    'ratio' => $snapshot['ratioRows'],
                    'trends' => $snapshot['trendRows'],
                ],
                'summary' => [
                    'total' => $snapshot['total'],
                    'published' => $snapshot['published'],
                    'implemented' => $snapshot['implemented'],
                ],
            ],
            'meta' => [
                'scope' => $scope,
                'selectedCategoryId' => $selectedCategoryId,
                'categoryOptions' => $this->buildCategoryOptions($categoryDictionary),
                'tabs' => [
                    ['code' => self::SCOPE_MINE, 'title' => 'Моя статистика'],
                    ['code' => self::SCOPE_GENERAL, 'title' => 'Общая статистика'],
                ],
                'hasData' => $snapshot['total'] > 0,
                'emptyMessage' => 'Статистика пока недоступна.',
            ],
            // backward compatibility
            'stats' => [
                'total' => $snapshot['total'],
                'published' => $snapshot['published'],
                'implemented' => $snapshot['implemented'],
            ],
            'statuses' => $snapshot['statusRows'],
            'categories' => $snapshot['categoryRows'],
        ];

        $this->includeComponentTemplate();
    }

    private function resolveScope(string $scope): string
    {
        return $scope === self::SCOPE_MINE ? self::SCOPE_MINE : self::SCOPE_GENERAL;
    }

    private function buildPrimaryMetrics(array $snapshot): array
    {
        return [
            ['label' => 'Число идей', 'value' => $snapshot['total'], 'caption' => 'В работе и в истории'],
            ['label' => 'Вовлеченность', 'value' => $snapshot['involvement'], 'caption' => 'Уникальных авторов'],
            ['label' => 'Экономический эффект', 'value' => number_format((int)$snapshot['potentialEffect'], 0, ',', ' ') . ' руб', 'caption' => 'Потенциал по выборке'],
            ['label' => 'Средний срок реализации', 'value' => $snapshot['avgImplementationDays'], 'caption' => 'Среднее по дням'],
        ];
    }

    private function buildSecondaryMetrics(array $snapshot): array
    {
        return [
            ['label' => 'Средний срок модерации', 'value' => $snapshot['avgModerationDays'], 'caption' => 'От отправки до решения'],
            ['label' => 'Возвраты на доработку', 'value' => $snapshot['revisions'], 'caption' => 'Требуются уточнения'],
            ['label' => 'Отклонено', 'value' => $snapshot['rejected'], 'caption' => 'По итогам отбора'],
            ['label' => 'Опубликовано', 'value' => $snapshot['published'], 'caption' => 'Доступны для обсуждения'],
        ];
    }

    private function prepareChartRows(array $rows): array
    {
        $prepared = [];
        $max = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $value = (int)($row['value'] ?? $row['VALUE'] ?? $row['CNT'] ?? 0);
            $max = max($max, $value);
            $prepared[] = [
                'label' => (string)($row['label'] ?? $row['LABEL'] ?? $row['NAME'] ?? ''),
                'value' => $value,
            ];
        }

        foreach ($prepared as &$row) {
            $row['percent'] = $max > 0 ? max(5, (int)round(($row['value'] / $max) * 100)) : 0;
        }
        unset($row);

        return $prepared;
    }

    private function loadIdeaRows(string $scope, int $selectedCategoryId): array
    {
        $filter = [];

        if ($scope === self::SCOPE_MINE) {
            $currentUserId = PublicDataService::getCurrentUserId();
            if ($currentUserId > 0) {
                $filter['=OWNER_USER_ID'] = $currentUserId;
            } else {
                $filter['=ID'] = 0;
            }
        }

        if ($selectedCategoryId > 0) {
            $filter['=CATEGORY_ID'] = $selectedCategoryId;
        }

        return IdeaTable::getList([
            'select' => [
                'ID',
                'OWNER_USER_ID',
                'CATEGORY_ID',
                'STATUS_ID',
                'ECONOMIC_EFFECT',
                'CONFIRMED_EFFECT',
                'IMPLEMENTATION_DAYS',
                'CREATED_AT',
                'SUBMITTED_AT',
                'MODERATED_AT',
                'PUBLISHED_AT',
            ],
            'filter' => $filter,
            'order' => ['ID' => 'DESC'],
        ])->fetchAll();
    }

    private function buildSnapshot(array $rows, array $statusDictionary, array $categoryDictionary): array
    {
        $statusCounts = [];
        $categoryCounts = [];
        $owners = [];
        $implementationDaysSum = 0;
        $implementationDaysCount = 0;
        $moderationDaysSum = 0;
        $moderationDaysCount = 0;
        $potentialEffect = 0;
        $confirmedEffect = 0;
        $total = count($rows);
        $published = 0;
        $accepted = 0;
        $implemented = 0;
        $revisions = 0;
        $rejected = 0;
        $moderation = 0;
        $trendBuckets = $this->buildTrendBuckets();

        foreach ($rows as $row) {
            $status = $statusDictionary[(int)($row['STATUS_ID'] ?? 0)] ?? [];
            $statusCode = (string)($status['CODE'] ?? '');
            $statusName = (string)($status['NAME'] ?? ('#' . (int)($row['STATUS_ID'] ?? 0)));
            $statusCounts[$statusName] = (int)($statusCounts[$statusName] ?? 0) + 1;

            $category = $categoryDictionary[(int)($row['CATEGORY_ID'] ?? 0)] ?? [];
            $categoryName = (string)($category['NAME'] ?? ('#' . (int)($row['CATEGORY_ID'] ?? 0)));
            $categoryCounts[$categoryName] = (int)($categoryCounts[$categoryName] ?? 0) + 1;

            $ownerUserId = (int)($row['OWNER_USER_ID'] ?? 0);
            if ($ownerUserId > 0) {
                $owners[$ownerUserId] = true;
            }

            $potentialEffect += (float)($row['ECONOMIC_EFFECT'] ?? 0);
            $confirmedEffect += (float)($row['CONFIRMED_EFFECT'] ?? 0);

            $implementationDays = (int)($row['IMPLEMENTATION_DAYS'] ?? 0);
            if ($implementationDays > 0) {
                $implementationDaysSum += $implementationDays;
                $implementationDaysCount++;
            }

            if ($statusCode === 'moderation') {
                $moderation++;
            }
            if ($statusCode === 'published') {
                $published++;
            }
            if ($statusCode === 'accepted') {
                $accepted++;
            }
            if ($statusCode === 'implemented') {
                $implemented++;
            }
            if ($statusCode === 'revise') {
                $revisions++;
            }
            if ($statusCode === 'rejected') {
                $rejected++;
            }

            $moderationDays = $this->calculateDaysBetween($row['SUBMITTED_AT'] ?? null, $row['MODERATED_AT'] ?? ($row['PUBLISHED_AT'] ?? null));
            if ($moderationDays !== null) {
                $moderationDaysSum += $moderationDays;
                $moderationDaysCount++;
            }

            $bucketKey = $this->resolveTrendBucketKey($row);
            if ($bucketKey !== null && isset($trendBuckets[$bucketKey])) {
                $trendBuckets[$bucketKey]['submitted']++;
                $trendBuckets[$bucketKey]['potentialEffect'] += (float)($row['ECONOMIC_EFFECT'] ?? 0);
                $trendBuckets[$bucketKey]['confirmedEffect'] += (float)($row['CONFIRMED_EFFECT'] ?? 0);
            }
        }

        $ratioRows = [
            ['label' => 'Принято', 'value' => $accepted],
            ['label' => 'Реализовано', 'value' => $implemented],
            ['label' => 'Опубликовано', 'value' => $published],
            ['label' => 'Прочее', 'value' => max(0, $total - $accepted - $implemented - $published)],
        ];

        return [
            'total' => $total,
            'published' => $published,
            'accepted' => $accepted,
            'implemented' => $implemented,
            'revisions' => $revisions,
            'rejected' => $rejected,
            'moderation' => $moderation,
            'involvement' => count($owners),
            'potentialEffect' => (int)round($potentialEffect),
            'confirmedEffect' => (int)round($confirmedEffect),
            'avgImplementationDays' => $implementationDaysCount > 0 ? round($implementationDaysSum / $implementationDaysCount, 1) . ' дн.' : '0 дн.',
            'avgModerationDays' => $moderationDaysCount > 0 ? round($moderationDaysSum / $moderationDaysCount, 1) . ' дн.' : '0 дн.',
            'statusRows' => $this->prepareChartRows($this->normalizeCounters($statusCounts)),
            'categoryRows' => $this->prepareChartRows($this->normalizeCounters($categoryCounts)),
            'funnelRows' => $this->prepareChartRows([
                ['label' => 'На модерации', 'value' => $moderation],
                ['label' => 'Опубликовано', 'value' => $published],
                ['label' => 'Принято', 'value' => $accepted],
                ['label' => 'Реализовано', 'value' => $implemented],
                ['label' => 'Отклонено', 'value' => $rejected],
            ]),
            'ratioRows' => $this->prepareChartRows($ratioRows),
            'trendRows' => $this->prepareTrendRows(array_values($trendBuckets)),
        ];
    }

    private function normalizeCounters(array $map): array
    {
        $rows = [];
        foreach ($map as $label => $value) {
            $rows[] = ['label' => (string)$label, 'value' => (int)$value];
        }

        usort(
            $rows,
            static fn(array $left, array $right): int => ($right['value'] <=> $left['value']) ?: strcmp((string)$left['label'], (string)$right['label'])
        );

        return $rows;
    }

    private function buildCategoryOptions(array $categoryDictionary): array
    {
        $options = [];
        foreach ($categoryDictionary as $id => $category) {
            $options[] = [
                'id' => (int)$id,
                'title' => (string)($category['NAME'] ?? ('#' . $id)),
            ];
        }

        return $options;
    }

    private function indexById(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $result[(int)($row['ID'] ?? 0)] = $row;
        }

        return $result;
    }

    private function buildTrendBuckets(): array
    {
        $buckets = [];
        $baseTimestamp = strtotime(date('Y-m-01 00:00:00')) ?: time();

        for ($offset = 5; $offset >= 0; $offset--) {
            $timestamp = strtotime('-' . $offset . ' month', $baseTimestamp);
            if ($timestamp === false) {
                continue;
            }

            $key = date('Y-m', $timestamp);
            $buckets[$key] = [
                'key' => $key,
                'label' => date('m.Y', $timestamp),
                'submitted' => 0,
                'potentialEffect' => 0,
                'confirmedEffect' => 0,
            ];
        }

        return $buckets;
    }

    private function resolveTrendBucketKey(array $row): ?string
    {
        $timestamp = $this->extractTimestamp($row['SUBMITTED_AT'] ?? null)
            ?? $this->extractTimestamp($row['CREATED_AT'] ?? null);

        return $timestamp !== null ? date('Y-m', $timestamp) : null;
    }

    private function prepareTrendRows(array $rows): array
    {
        $max = 0;
        foreach ($rows as $row) {
            $max = max($max, (int)($row['submitted'] ?? 0));
        }

        foreach ($rows as &$row) {
            $row['percent'] = $max > 0 ? max(5, (int)round(((int)$row['submitted'] / $max) * 100)) : 0;
        }
        unset($row);

        return $rows;
    }

    private function calculateDaysBetween(mixed $from, mixed $to): ?int
    {
        $fromTimestamp = $this->extractTimestamp($from);
        $toTimestamp = $this->extractTimestamp($to);

        if ($fromTimestamp === null || $toTimestamp === null || $toTimestamp < $fromTimestamp) {
            return null;
        }

        return (int)round(($toTimestamp - $fromTimestamp) / 86400);
    }

    private function extractTimestamp(mixed $value): ?int
    {
        if ($value instanceof \Bitrix\Main\Type\DateTime || $value instanceof \Bitrix\Main\Type\Date) {
            return $value->getTimestamp();
        }

        if (is_string($value) && $value !== '') {
            $timestamp = strtotime($value);
            return $timestamp !== false ? $timestamp : null;
        }

        return null;
    }
}
