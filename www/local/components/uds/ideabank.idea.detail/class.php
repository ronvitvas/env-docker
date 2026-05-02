<?php
declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\UserTable;
use Uds\Ideabank2\Domain\PublicDataService;

final class UdsIdeabankIdeaDetailComponent extends CBitrixComponent
{
    public function executeComponent(): void
    {
        if (!Loader::includeModule('uds.ideabank2')) {
            ShowError('Модуль uds.ideabank2 не установлен');
            return;
        }

        $query = Application::getInstance()->getContext()->getRequest()->getQueryList()->toArray();
        $domainData = PublicDataService::getIdeaDetailData($query);

        $this->arResult = $this->buildPpuDetailViewModel($domainData, $query);
        $this->includeComponentTemplate();
    }

    private function buildPpuDetailViewModel(array $domainData, array $query): array
    {
        $idea = is_array($domainData['idea'] ?? null) ? $domainData['idea'] : null;
        $comments = is_array($domainData['comments'] ?? null) ? $domainData['comments'] : [];
        $workflow = is_array($domainData['workflow'] ?? null) ? $domainData['workflow'] : [];
        $feedback = is_array($domainData['feedback'] ?? null) ? $domainData['feedback'] : [];
        $expertReviews = is_array($domainData['expertReviews'] ?? null) ? $domainData['expertReviews'] : [];
        $committeeDecision = is_array($domainData['committeeDecision'] ?? null) ? $domainData['committeeDecision'] : null;
        $reactions = is_array($domainData['reactions'] ?? null) ? $domainData['reactions'] : ['like' => 0, 'dislike' => 0];
        $runtimeMeta = is_array($domainData['meta'] ?? null) ? $domainData['meta'] : PublicDataService::getRuntimeMeta();
        $features = is_array($runtimeMeta['features'] ?? null) ? $runtimeMeta['features'] : [];
        $permissions = is_array($runtimeMeta['permissions'] ?? null) ? $runtimeMeta['permissions'] : [];
        $profiles = $this->loadUserProfiles($this->collectUserIds($idea, $comments, $workflow, $feedback, $expertReviews, $committeeDecision !== null ? [$committeeDecision] : []));
        $preparedIdea = $idea !== null ? $this->prepareIdea($idea, $profiles) : null;
        $workflowWidget = $this->buildWorkflow($workflow, $preparedIdea, $profiles);
        $preparedComments = $this->prepareComments($comments, $profiles);
        $preparedFeedback = $this->prepareMessages($feedback, $profiles, 'MESSAGE');
        $preparedExpertReviews = $this->prepareMessages($expertReviews, $profiles, 'COMMENT');

        return [
            'shell' => is_array($domainData['shell'] ?? null) ? $domainData['shell'] : PublicDataService::getShellData('management'),
            'page' => [
                'title' => $preparedIdea['TITLE'] ?? 'Карточка идеи',
                'subtitle' => $preparedIdea !== null
                    ? (($preparedIdea['CODE'] ?? ('#' . (int)$preparedIdea['ID'])) . ' · ' . ($preparedIdea['STATUS']['NAME'] ?? 'Маршрут не задан'))
                    : 'Идея не найдена',
                'eyebrow' => 'Операционная карточка',
                'idea' => $preparedIdea,
                'sections' => $preparedIdea !== null ? $this->buildContentSections($preparedIdea) : [],
                'actions' => $preparedIdea !== null ? $this->buildActions($preparedIdea, $permissions) : [],
                'empty' => [
                    'title' => 'Идея не найдена',
                    'text' => 'Вернитесь в реестр и выберите существующую запись.',
                    'action' => ['title' => 'Открыть реестр', 'url' => '/ideabank/management.php'],
                ],
            ],
            'widgets' => [
                'metaCards' => $preparedIdea !== null ? $this->buildMetaCards($preparedIdea) : [],
                'discussion' => [
                    'enabled' => !empty($features['feature_comments']) || !empty($features['feature_reactions']),
                    'reactionsEnabled' => !empty($features['feature_reactions']),
                    'commentsEnabled' => !empty($features['feature_comments']),
                    'reactions' => !empty($features['feature_reactions']) ? ['like' => (int)($reactions['like'] ?? 0), 'dislike' => (int)($reactions['dislike'] ?? 0)] : ['like' => 0, 'dislike' => 0],
                    'comments' => !empty($features['feature_comments']) ? $preparedComments : [],
                    'empty' => 'Комментариев пока нет.',
                ],
                'related' => $preparedIdea !== null ? $this->buildRelatedData($preparedIdea) : [],
                'files' => $preparedIdea['FILES'] ?? [],
                'workflow' => $workflowWidget,
                'feedback' => !empty($permissions['canViewModeration']) || !empty($permissions['canAdminIdeabank']) ? $preparedFeedback : [],
                'expertReviews' => !empty($features['feature_expertise']) && (!empty($permissions['canReviewExpertise']) || !empty($permissions['canAdminIdeabank'])) ? $preparedExpertReviews : [],
                'committeeDecision' => !empty($features['feature_committee']) && (!empty($permissions['canMakeCommitteeDecision']) || !empty($permissions['canAdminIdeabank'])) && $committeeDecision !== null ? $this->prepareCommitteeDecision($committeeDecision, $profiles) : null,
                'authors' => $preparedIdea['AUTHORS'] ?? [],
                'history' => $this->buildHistory($preparedIdea, $workflow),
            ],
            'meta' => [
                'query' => $query,
                'id' => (int)($query['id'] ?? 0),
                'found' => $preparedIdea !== null,
                'hasComments' => $preparedComments !== [],
                'hasWorkflow' => $workflowWidget['items'] !== [],
            ] + $runtimeMeta,
            // backward compatibility
            'idea' => $preparedIdea,
            'comments' => $preparedComments,
            'reactions' => $reactions,
            'workflow' => $workflowWidget['items'],
            'feedback' => $preparedFeedback,
            'expertReviews' => $preparedExpertReviews,
            'committeeDecision' => $committeeDecision,
        ];
    }

    private function prepareIdea(array $idea, array $profiles): array
    {
        $ownerUserId = (int)($idea['OWNER_USER_ID'] ?? $idea['USER_ID'] ?? 0);
        $idea['OWNER_LABEL'] = $this->resolveFullName($profiles[$ownerUserId] ?? [], $ownerUserId > 0 ? 'Пользователь ' . $ownerUserId : 'Автор идеи');
        $idea['OWNER_ROLE'] = trim((string)($profiles[$ownerUserId]['WORK_POSITION'] ?? '')) ?: 'Участник банка идей';
        $idea['DETAIL_URL'] = '/ideabank/ppu-detail.php?id=' . (int)($idea['ID'] ?? 0);
        $idea['EDIT_URL'] = '/ideabank/ppu-form.php?id=' . (int)($idea['ID'] ?? 0);
        $idea['BASED_ON_URL'] = '/ideabank/ppu-form.php?source_id=' . (int)($idea['ID'] ?? 0);
        $idea['ROUTE_LABEL'] = $this->resolveRouteLabel((string)($idea['STAGE'] ?? ''), is_array($idea['STATUS'] ?? null) ? $idea['STATUS'] : []);
        $idea['AUTHORS'] = $this->prepareAuthors(is_array($idea['AUTHORS'] ?? null) ? $idea['AUTHORS'] : [], $profiles, $idea['OWNER_LABEL'], $idea['OWNER_ROLE']);

        return $idea;
    }

    private function buildContentSections(array $idea): array
    {
        return [
            [
                'title' => 'Описание инициативы',
                'rows' => [
                    ['label' => 'Текущее состояние', 'value' => $idea['CURRENT_STATE'] ?? $idea['DESCRIPTION'] ?? ''],
                    ['label' => 'Проблема', 'value' => $idea['PROBLEM'] ?? ''],
                    ['label' => 'Потери', 'value' => $idea['LOSSES'] ?? ''],
                    ['label' => 'Идея', 'value' => $idea['PROPOSAL'] ?? ''],
                ],
            ],
            [
                'title' => 'Эффект и внедрение',
                'rows' => [
                    ['label' => 'Прогноз', 'value' => $idea['ECONOMIC_EFFECT'] ?? 0, 'type' => 'money'],
                    ['label' => 'Подтверждено', 'value' => $idea['CONFIRMED_EFFECT'] ?? 0, 'type' => 'money'],
                    ['label' => 'Оценка эффекта', 'value' => $idea['ECONOMIC_EFFECT_TEXT'] ?? ''],
                    ['label' => 'План внедрения', 'value' => $idea['IMPLEMENTATION_PLAN'] ?? ''],
                    ['label' => 'Ресурсы', 'value' => $idea['RESOURCES_NEEDED'] ?? ''],
                    ['label' => 'Риски', 'value' => $idea['RISKS'] ?? ''],
                ],
            ],
        ];
    }

    private function buildActions(array $idea, array $permissions): array
    {
        $id = (int)($idea['ID'] ?? 0);
        $isDraft = (string)($idea['IS_DRAFT'] ?? 'N') === 'Y' || (string)($idea['STAGE'] ?? '') === 'draft';
        $isOwn = (int)($idea['OWNER_USER_ID'] ?? $idea['USER_ID'] ?? 0) === (int)($permissions['userId'] ?? 0);
        $canEdit = ($isOwn && $isDraft && !empty($permissions['canUseDrafts']))
            || ($isOwn && !empty($permissions['canEditAfterSubmit']))
            || !empty($permissions['canAdminIdeabank']);

        return array_values(array_filter([
            $canEdit ? ['title' => $isDraft ? 'Продолжить редактирование' : 'Редактировать', 'url' => $idea['EDIT_URL'] ?? '#', 'primary' => true] : null,
            !empty($permissions['canSubmitIdea']) ? ['title' => 'Создать на основе идеи', 'url' => $idea['BASED_ON_URL'] ?? '#', 'primary' => !$canEdit] : null,
            ['title' => 'Открыть в моем списке', 'url' => '/ideabank/management.php?mode=' . ($isDraft ? 'drafts' : 'mine')],
            ['title' => 'Вернуться в реестр', 'url' => '/ideabank/management.php'],
            !empty($permissions['canViewModeration']) ? ['title' => 'Открыть очередь модерации', 'url' => '/ideabank/management.php?mode=moderation'] : null,
        ]));
    }

    private function buildMetaCards(array $idea): array
    {
        return [
            ['label' => 'Тип', 'value' => (string)($idea['REQUEST_TYPE'] ?? $idea['TYPE'] ?? 'Инициатива')],
            ['label' => 'Категория', 'value' => (string)($idea['CATEGORY']['NAME'] ?? 'Без категории')],
            ['label' => 'Потенциальный эффект', 'value' => (float)($idea['ECONOMIC_EFFECT'] ?? 0), 'type' => 'money'],
            ['label' => 'Подтвержденный эффект', 'value' => (float)($idea['CONFIRMED_EFFECT'] ?? 0), 'type' => 'money'],
        ];
    }

    private function buildRelatedData(array $idea): array
    {
        return [
            ['label' => 'Категория', 'value' => (string)($idea['CATEGORY']['NAME'] ?? '')],
            ['label' => 'Статус', 'value' => (string)($idea['STATUS']['NAME'] ?? ''), 'statusCode' => (string)($idea['STATUS']['CODE'] ?? $idea['STAGE'] ?? '')],
            ['label' => 'Этап', 'value' => (string)($idea['ROUTE_LABEL'] ?? '')],
            ['label' => 'Бизнес-направление', 'value' => (string)($idea['BUSINESS_DIRECTION'] ?? '')],
            ['label' => 'Ключевые слова', 'value' => (string)($idea['KEYWORDS'] ?? '')],
            ['label' => 'Дата подачи', 'value' => $idea['SUBMITTED_AT'] ?? $idea['CREATED_AT'] ?? null, 'type' => 'date'],
        ];
    }

    private function buildWorkflow(array $workflow, ?array $idea, array $profiles): array
    {
        $items = [];
        foreach ($workflow as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $userId = (int)($entry['USER_ID'] ?? 0);
            $items[] = $entry + [
                'USER_LABEL' => $this->resolveFullName($profiles[$userId] ?? [], $userId > 0 ? 'Пользователь ' . $userId : 'Система'),
                'TITLE' => (string)($entry['STAGE'] ?? $entry['STATUS'] ?? 'Этап маршрута'),
                'TEXT' => (string)($entry['COMMENT'] ?? ''),
            ];
        }

        return [
            'title' => 'Маршрут идеи и SLA',
            'current' => $idea['ROUTE_LABEL'] ?? 'Маршрут не задан',
            'assignee' => $idea['ASSIGNEE_LABEL'] ?? 'Не назначен',
            'targetDate' => $idea['TARGET_DATE'] ?? null,
            'progress' => max(0, min(100, (int)($idea['PROGRESS_PERCENT'] ?? 0))),
            'items' => $items,
            'empty' => 'История маршрута пока не заполнена.',
        ];
    }

    private function buildHistory(?array $idea, array $workflow): array
    {
        $history = [];
        if ($idea !== null) {
            $history[] = [
                'label' => 'Создана карточка',
                'description' => (string)($idea['TITLE'] ?? 'Инициатива зарегистрирована'),
                'date' => $idea['CREATED_AT'] ?? null,
            ];
        }

        foreach ($workflow as $entry) {
            if (is_array($entry)) {
                $history[] = [
                    'label' => (string)($entry['STAGE'] ?? $entry['STATUS'] ?? 'Этап'),
                    'description' => (string)($entry['COMMENT'] ?? 'Изменение маршрута идеи'),
                    'date' => $entry['CREATED_AT'] ?? null,
                ];
            }
        }

        return $history;
    }

    private function prepareComments(array $comments, array $profiles): array
    {
        return $this->prepareMessages($comments, $profiles, 'TEXT');
    }

    private function prepareMessages(array $items, array $profiles, string $textField): array
    {
        $prepared = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $userId = (int)($item['USER_ID'] ?? $item['AUTHOR_USER_ID'] ?? 0);
            $prepared[] = $item + [
                'USER_LABEL' => $this->resolveFullName($profiles[$userId] ?? [], $userId > 0 ? 'Пользователь ' . $userId : 'Участник'),
                'TEXT_VALUE' => (string)($item[$textField] ?? $item['MESSAGE'] ?? $item['COMMENT'] ?? $item['TEXT'] ?? ''),
            ];
        }

        return $prepared;
    }

    private function prepareCommitteeDecision(array $decision, array $profiles): array
    {
        $userId = (int)($decision['USER_ID'] ?? $decision['DECIDED_BY'] ?? 0);

        return $decision + [
            'USER_LABEL' => $this->resolveFullName($profiles[$userId] ?? [], $userId > 0 ? 'Пользователь ' . $userId : 'Комитет'),
            'TEXT_VALUE' => (string)($decision['SUMMARY'] ?? $decision['COMMENT'] ?? $decision['DECISION'] ?? ''),
        ];
    }

    private function prepareAuthors(array $authors, array $profiles, string $ownerLabel, string $ownerRole): array
    {
        if ($authors === []) {
            return [['USER_LABEL' => $ownerLabel, 'USER_ROLE' => $ownerRole, 'SHARE' => 100]];
        }

        $prepared = [];
        foreach ($authors as $author) {
            if (!is_array($author)) {
                continue;
            }
            $userId = (int)($author['USER_ID'] ?? 0);
            $prepared[] = $author + [
                'USER_LABEL' => $this->resolveFullName($profiles[$userId] ?? [], $userId > 0 ? 'Пользователь ' . $userId : 'Автор'),
                'USER_ROLE' => trim((string)($profiles[$userId]['WORK_POSITION'] ?? '')) ?: 'Соавтор инициативы',
                'SHARE' => (int)($author['SHARE'] ?? $author['SHARE_PERCENT'] ?? 0),
            ];
        }

        return $prepared;
    }

    private function collectUserIds(?array $idea, array ...$collections): array
    {
        $userIds = [];
        if ($idea !== null) {
            $userIds[] = (int)($idea['OWNER_USER_ID'] ?? $idea['USER_ID'] ?? 0);
            foreach ((array)($idea['AUTHORS'] ?? []) as $author) {
                if (is_array($author)) {
                    $userIds[] = (int)($author['USER_ID'] ?? 0);
                }
            }
        }

        foreach ($collections as $collection) {
            foreach ($collection as $item) {
                if (is_array($item)) {
                    $userIds[] = (int)($item['USER_ID'] ?? $item['AUTHOR_USER_ID'] ?? $item['DECIDED_BY'] ?? 0);
                }
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
