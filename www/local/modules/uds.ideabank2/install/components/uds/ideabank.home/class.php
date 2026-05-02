<?php
declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\UserTable;
use Uds\Ideabank2\Config\ModuleOptions;
use Uds\Ideabank2\Config\SelfCheck;
use Uds\Ideabank2\Domain\ChallengeService;
use Uds\Ideabank2\Domain\PublicDataService;

final class UdsIdeabankHomeComponent extends CBitrixComponent
{
    private const SCENARIO_EMPLOYEE = 'employee';
    private const SCENARIO_WORK = 'work';
    private const SCENARIO_ADMIN = 'admin';

    public function executeComponent(): void
    {
        if (!Loader::includeModule('uds.ideabank2')) {
            ShowError('Модуль uds.ideabank2 не установлен');
            return;
        }

        $initialMeta = PublicDataService::getRuntimeMeta();
        $initialPermissions = is_array($initialMeta['permissions'] ?? null) ? $initialMeta['permissions'] : [];
        $initialScenario = $this->resolveScenario($initialPermissions);
        $adminSettingsMessage = $this->processAdminSettingsPost($initialScenario, $initialPermissions);

        $domainData = PublicDataService::getHomeData();
        $mineData = PublicDataService::getIdeaListData(['mode' => 'mine']);
        $currentUserId = PublicDataService::getCurrentUserId();
        $currentUser = $this->loadCurrentUser($currentUserId);
        $stats = is_array($domainData['stats'] ?? null) ? $domainData['stats'] : [];
        $coinTotals = is_array($domainData['coinTotals'] ?? null) ? $domainData['coinTotals'] : [];
        $rewardRules = is_array($domainData['rewardRules'] ?? null) ? $domainData['rewardRules'] : [];
        $news = is_array($domainData['news'] ?? null) ? $domainData['news'] : [];
        $challenges = is_array($domainData['challenges'] ?? null) ? $domainData['challenges'] : [];
        $ideas = is_array($domainData['ideas'] ?? null) ? $domainData['ideas'] : [];
        $leaderboard = is_array($domainData['leaderboard'] ?? null) ? $domainData['leaderboard'] : [];
        $roleQueues = is_array($domainData['roleQueues'] ?? null) ? $domainData['roleQueues'] : [];
        $myIdeas = is_array($mineData['items'] ?? null) ? $mineData['items'] : [];
        $runtimeMeta = is_array($domainData['meta'] ?? null) ? $domainData['meta'] : PublicDataService::getRuntimeMeta();
        $features = is_array($runtimeMeta['features'] ?? null) ? $runtimeMeta['features'] : [];
        $permissions = is_array($runtimeMeta['permissions'] ?? null) ? $runtimeMeta['permissions'] : [];
        $scenario = $this->resolveScenario($permissions);

        $profiles = $this->loadUserProfiles(array_merge(
            [$currentUserId],
            $this->collectUserIds($ideas),
            $this->collectUserIds($leaderboard),
            $this->collectUserIds($myIdeas),
            $this->collectUserIdsFromQueues($roleQueues)
        ));

        $preparedIdeas = $this->prepareIdeaCards($ideas, $profiles);
        $preparedLeaderboard = $this->prepareLeaderboard($leaderboard, $profiles);
        $preparedMyIdeas = $this->prepareIdeaCards($myIdeas, $profiles);
        $preparedRoleQueues = $this->prepareRoleQueues($roleQueues, $profiles);
        $preparedChallenges = $this->prepareChallengeCards($challenges);
        $showcaseIdeas = $this->buildShowcaseIdeas($preparedIdeas);
        $quotes = $this->buildQuotes($showcaseIdeas);
        $shareIdeas = $this->buildShareIdeas($preparedMyIdeas);
        $roleSwitch = $this->buildRoleSwitch($scenario, $currentUser, $permissions);
        $roleWidget = $this->buildRoleWidget($scenario, $currentUser, $preparedMyIdeas, $preparedRoleQueues, $permissions);
        $coinWidget = !empty($features['feature_coins'])
            ? $this->buildCoinWidget($coinTotals, $rewardRules, is_array($domainData['coinHistory'] ?? null) ? $domainData['coinHistory'] : [])
            : ['totals' => [], 'history' => [], 'tiles' => []];

        $this->arResult = [
            'shell' => is_array($domainData['shell'] ?? null) ? $domainData['shell'] : [],
            'page' => [
                'title' => 'Банк идей',
                'subtitle' => $this->buildPageSubtitle($scenario),
                'eyebrow' => $this->buildPageEyebrow($scenario),
                'description' => $this->buildPageDescription($scenario),
                'hero' => $this->buildHero($scenario, $permissions),
                'processSteps' => $this->buildProcessSteps($permissions, !empty($features['feature_public_idea_form']), $shareIdeas),
                'trustMetrics' => $this->buildTrustMetrics($stats),
                'showcaseIdeas' => $showcaseIdeas,
            ],
            'widgets' => [
                'roleSwitch' => $roleSwitch,
                'role' => $roleWidget,
                'coin' => $coinWidget,
                'quickLinks' => [
                    'items' => $this->buildQuickLinks($features),
                ],
                'news' => [
                    'items' => !empty($features['feature_public_news']) ? array_slice($news, 0, 3) : [],
                ],
                'challenges' => [
                    'items' => !empty($features['feature_public_contests']) ? array_slice($preparedChallenges, 0, 4) : [],
                ],
                'guidance' => [
                    'items' => !empty($features['feature_public_docs']) ? $this->buildGuidanceCards() : [],
                ],
                'quotes' => [
                    'items' => !empty($features['feature_public_quotes']) ? $quotes : [],
                ],
                'leaderboard' => [
                    'items' => !empty($features['feature_leaderboard']) ? array_slice($preparedLeaderboard, 0, 5) : [],
                ],
                'shareIdeas' => [
                    'items' => $shareIdeas,
                ],
                'ideaTabs' => $this->buildIdeaTabs($showcaseIdeas, $preparedIdeas, $preparedMyIdeas),
                'adminSettings' => $scenario === self::SCENARIO_ADMIN && !empty($permissions['canAdminIdeabank'])
                    ? $this->buildAdminSettingsWidget($adminSettingsMessage)
                    : [],
                'leaderCta' => !empty($features['feature_public_contests']) && (!empty($permissions['canViewModeration']) || !empty($permissions['canAdminIdeabank'])) ? [
                    'title' => 'Запустите тематический челлендж',
                    'text' => 'Сфокусируйте команду на одной бизнес-задаче и соберите идеи с быстрым эффектом.',
                    'url' => '/ideabank/contests.php',
                    'button' => 'Запустить тематический челлендж',
                ] : [],
            ],
            'meta' => [
                'scenario' => $scenario,
                'hasIdeas' => $showcaseIdeas !== [],
                'hasShareIdeas' => $shareIdeas !== [],
                'hasNews' => $news !== [],
                'hasChallenges' => $challenges !== [],
            ] + $runtimeMeta,
            // backward compatibility
            'stats' => $stats,
            'coinBalance' => $domainData['coinBalance'] ?? 0,
            'coinTotals' => $coinTotals,
            'coinHistory' => is_array($domainData['coinHistory'] ?? null) ? $domainData['coinHistory'] : [],
            'rewardRules' => $rewardRules,
            'leaderboard' => $preparedLeaderboard,
            'news' => $news,
            'challenges' => $preparedChallenges,
            'ideas' => $showcaseIdeas,
            'shareIdeas' => $shareIdeas,
            'roleQueues' => $roleQueues,
        ];

        $this->includeComponentTemplate();
    }

    private function processAdminSettingsPost(string $scenario, array $permissions): array
    {
        $request = Application::getInstance()->getContext()->getRequest();
        if (!$request->isPost() || (string)$request->getPost('uds_ib_action') !== 'save_public_settings') {
            return [];
        }

        if ($scenario !== self::SCENARIO_ADMIN || empty($permissions['canAdminIdeabank'])) {
            return ['type' => 'error', 'text' => 'Недостаточно прав для изменения настроек банка идей.'];
        }

        if (!check_bitrix_sessid()) {
            return ['type' => 'error', 'text' => 'Сессия истекла. Обновите страницу и повторите сохранение.'];
        }

        foreach (['moderation_auto_approve', 'enable_anonymous', 'enable_expert_review', 'enable_committee'] as $name) {
            ModuleOptions::setBool($name, $request->getPost($name) === 'Y');
        }

        foreach (array_keys(ModuleOptions::getFeatures()) as $name) {
            ModuleOptions::setBool($name, $request->getPost($name) === 'Y');
        }

        foreach (['coin_submission', 'coin_accepted', 'coin_implemented', 'max_leaderboard', 'shop_monthly_limit', 'shop_min_balance_after_purchase'] as $name) {
            ModuleOptions::setInt($name, (int)$request->getPost($name));
        }

        $allowedDebugIds = preg_replace('/[^0-9,\s]/', '', (string)$request->getPost('debug_auth_allowed_user_ids')) ?? '';
        ModuleOptions::setString('debug_auth_allowed_user_ids', trim($allowedDebugIds));

        return ['type' => 'success', 'text' => 'Настройки банка идей сохранены.'];
    }

    private function resolveScenario(array $permissions): string
    {
        $canUseWorkScenario = !empty($permissions['canViewModeration'])
            || !empty($permissions['canReviewExpertise'])
            || !empty($permissions['canMakeCommitteeDecision'])
            || !empty($permissions['canAdminIdeabank']);

        if (!$canUseWorkScenario) {
            return self::SCENARIO_EMPLOYEE;
        }

        $requestScenario = (string)Application::getInstance()->getContext()->getRequest()->get('scenario');

        if ($requestScenario === self::SCENARIO_ADMIN && !empty($permissions['canAdminIdeabank'])) {
            return self::SCENARIO_ADMIN;
        }

        return $requestScenario === self::SCENARIO_WORK ? self::SCENARIO_WORK : self::SCENARIO_EMPLOYEE;
    }

    private function buildPageSubtitle(string $scenario): string
    {
        return match ($scenario) {
            self::SCENARIO_WORK => 'Рабочий контур модерации, экспертной оценки и комитета.',
            self::SCENARIO_ADMIN => 'Администрирование банка идей.',
            default => 'Настраивай бизнес. Меняй бизнес.',
        };
    }

    private function buildPageEyebrow(string $scenario): string
    {
        return match ($scenario) {
            self::SCENARIO_WORK => 'Рабочий контур банка идей',
            self::SCENARIO_ADMIN => 'Администратор банка идей',
            default => 'Банк идей UDS',
        };
    }

    private function buildPageDescription(string $scenario): string
    {
        return match ($scenario) {
            self::SCENARIO_WORK => 'В этом сценарии собраны рабочие очереди, аналитика и быстрые переходы для принятия решений.',
            self::SCENARIO_ADMIN => 'В этом сценарии собраны настройки, справочники, права и административные разделы модуля.',
            default => 'Каждая идея может изменить процесс, команду и результат.',
        };
    }

    private function loadCurrentUser(int $userId): array
    {
        if ($userId <= 0) {
            return [
                'ID' => 0,
                'FULL_NAME' => 'Сотрудник банка идей',
                'ROLE' => 'Участник',
            ];
        }

        $profile = $this->loadUserProfiles([$userId])[$userId] ?? [];

        return [
            'ID' => $userId,
            'FULL_NAME' => $this->resolveFullName($profile, 'Сотрудник банка идей'),
            'ROLE' => trim((string)($profile['WORK_POSITION'] ?? '')) ?: 'Участник',
        ];
    }

    private function loadUserProfiles(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $userId): bool => $userId > 0)));
        if ($userIds === []) {
            return [];
        }

        $rows = UserTable::getList([
            'select' => ['ID', 'NAME', 'LAST_NAME', 'SECOND_NAME', 'WORK_POSITION', 'PERSONAL_PHOTO'],
            'filter' => ['@ID' => $userIds],
        ])->fetchAll();

        $profiles = [];
        foreach ($rows as $row) {
            $profiles[(int)$row['ID']] = $row;
        }

        return $profiles;
    }

    private function collectUserIds(array $items): array
    {
        $userIds = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            foreach (['USER_ID', 'OWNER_USER_ID'] as $key) {
                $userId = (int)($item[$key] ?? 0);
                if ($userId > 0) {
                    $userIds[] = $userId;
                }
            }
        }

        return array_values(array_unique($userIds));
    }

    private function collectUserIdsFromQueues(array $queues): array
    {
        $userIds = [];

        foreach ($queues as $items) {
            if (!is_array($items)) {
                continue;
            }

            $userIds = array_merge($userIds, $this->collectUserIds($items));
        }

        return array_values(array_unique($userIds));
    }

    private function prepareIdeaCards(array $items, array $profiles): array
    {
        $prepared = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $ownerUserId = (int)($item['OWNER_USER_ID'] ?? $item['USER_ID'] ?? 0);
            $profile = $profiles[$ownerUserId] ?? [];
            $item['OWNER_LABEL'] = $this->resolveFullName($profile, $ownerUserId > 0 ? 'Пользователь ' . $ownerUserId : 'Автор идеи');
            $item['OWNER_ROLE'] = trim((string)($profile['WORK_POSITION'] ?? ''));
            $item['EXCERPT'] = trim((string)($item['DESCRIPTION'] ?? $item['PROBLEM'] ?? $item['PROPOSAL'] ?? ''));
            $item['DETAIL_URL'] = '/ideabank/ppu-detail.php?id=' . (int)($item['ID'] ?? 0);
            $item['EFFECT_VALUE'] = (float)($item['ECONOMIC_EFFECT'] ?? 0);
            $item['CONFIRMED_EFFECT_VALUE'] = (float)($item['CONFIRMED_EFFECT'] ?? 0);
            $item['IMPLEMENTATION_DAYS_VALUE'] = (int)($item['IMPLEMENTATION_DAYS'] ?? 0);
            $prepared[] = $item;
        }

        return $prepared;
    }

    private function buildShowcaseIdeas(array $ideas): array
    {
        $implemented = array_values(array_filter($ideas, function (array $item): bool {
            $status = is_array($item['STATUS'] ?? null) ? $item['STATUS'] : [];
            $code = (string)($status['CODE'] ?? '');
            $name = mb_strtolower((string)($status['NAME'] ?? ''));

            return in_array($code, ['implemented', 'accepted'], true)
                || str_contains($name, 'реализ')
                || str_contains($name, 'внедрен');
        }));

        return array_slice($implemented !== [] ? $implemented : $ideas, 0, 4);
    }

    private function buildIdeaTabs(array $topIdeas, array $allIdeas, array $myIdeas): array
    {
        $newIdeas = $allIdeas;
        usort($newIdeas, static function (array $left, array $right): int {
            return (int)($right['ID'] ?? 0) <=> (int)($left['ID'] ?? 0);
        });

        return [
            [
                'code' => 'top',
                'title' => 'Топ лучших',
                'url' => '/ideabank/management.php?mode=best',
                'items' => array_slice($topIdeas, 0, 5),
                'empty' => 'Лучших идей пока нет.',
            ],
            [
                'code' => 'new',
                'title' => 'Новые',
                'url' => '/ideabank/management.php',
                'items' => array_slice($newIdeas, 0, 5),
                'empty' => 'Новых идей пока нет.',
            ],
            [
                'code' => 'mine',
                'title' => 'Мои',
                'url' => '/ideabank/management.php?mode=mine',
                'items' => array_slice($myIdeas, 0, 5),
                'empty' => 'У вас пока нет идей.',
            ],
        ];
    }

    private function prepareLeaderboard(array $items, array $profiles): array
    {
        $prepared = [];
        $rank = 1;
        $ideaStats = $this->loadLeaderboardIdeaStats($this->collectUserIds($items));

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $userId = (int)($item['USER_ID'] ?? 0);
            $profile = $profiles[$userId] ?? [];
            $prepared[] = [
                'RANK' => (int)($item['RANK'] ?? $rank),
                'USER_ID' => $userId,
                'USER_LABEL' => $this->resolveFullName($profile, $userId > 0 ? 'Пользователь ' . $userId : 'Участник'),
                'ROLE_LABEL' => trim((string)($profile['WORK_POSITION'] ?? '')) ?: 'Участник банка идей',
                'PHOTO_SRC' => $this->resolveUserPhoto($profile),
                'COINS' => (int)($item['COINS'] ?? $item['TOTAL_COINS'] ?? 0),
                'IDEA_STATS' => $ideaStats[$userId] ?? [
                    'total' => 0,
                    'discussion' => 0,
                    'accepted' => 0,
                    'implemented' => 0,
                ],
            ];
            $rank++;
        }

        return $prepared;
    }

    private function loadLeaderboardIdeaStats(array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $userId): bool => $userId > 0)));
        if ($userIds === []) {
            return [];
        }

        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();
        $tableName = $sqlHelper->quote('b_uds_ideabank_idea');
        $ids = implode(',', $userIds);

        $rows = $connection->query("
            SELECT
                OWNER_USER_ID,
                COUNT(*) AS TOTAL,
                SUM(CASE WHEN STAGE IN ('moderation', 'published', 'initial_review', 'kpu', 'backlog') THEN 1 ELSE 0 END) AS DISCUSSION,
                SUM(CASE WHEN STAGE IN ('accepted', 'implementation', 'transferred') THEN 1 ELSE 0 END) AS ACCEPTED,
                SUM(CASE WHEN STAGE IN ('implemented', 'deployed') THEN 1 ELSE 0 END) AS IMPLEMENTED
            FROM {$tableName}
            WHERE OWNER_USER_ID IN ({$ids})
            GROUP BY OWNER_USER_ID
        ")->fetchAll();

        $stats = [];
        foreach ($rows as $row) {
            $stats[(int)$row['OWNER_USER_ID']] = [
                'total' => (int)($row['TOTAL'] ?? 0),
                'discussion' => (int)($row['DISCUSSION'] ?? 0),
                'accepted' => (int)($row['ACCEPTED'] ?? 0),
                'implemented' => (int)($row['IMPLEMENTED'] ?? 0),
            ];
        }

        return $stats;
    }

    private function resolveUserPhoto(array $profile): string
    {
        $photoId = (int)($profile['PERSONAL_PHOTO'] ?? 0);
        if ($photoId <= 0 || !class_exists(\CFile::class)) {
            return '';
        }

        return (string)\CFile::GetPath($photoId);
    }

    private function buildRoleSwitch(string $scenario, array $currentUser, array $permissions): array
    {
        $options = [
            [
                'code' => self::SCENARIO_EMPLOYEE,
                'badge' => 'Пользователь',
                'title' => 'Интерфейс пользователя',
                'summary' => 'Показывает личные и вовлекающие блоки: мои идеи, коины, лучшие практики, новости и следующий шаг автора.',
                'difference' => 'Подходит для подачи и развития идей. Рабочие очереди модерации и комитета здесь скрыты.',
                'visibility' => 'Свои идеи, история коинов, лучшие реализованные идеи, новости и материалы по подаче инициатив.',
                'impact' => 'Помогает сотруднику быстро включиться в систему и довести идею до решения.',
                'url' => '/ideabank/index.php?scenario=' . self::SCENARIO_EMPLOYEE,
            ],
            [
                'code' => self::SCENARIO_WORK,
                'badge' => 'Модератор',
                'title' => 'Интерфейс модератора',
                'summary' => 'Показывает только рабочие блоки: очереди модерации, экспертной оценки, комитета и идеи в реализации.',
                'difference' => 'Убирает новости и вовлекающий контент сотрудника, чтобы фокус был на проверке и решениях.',
                'visibility' => 'Рабочая сводка, очереди по этапам, backlog и быстрые переходы в операционные разделы.',
                'impact' => 'Помогает модератору и комитету быстрее принимать решения и двигать идеи по процессу.',
                'url' => '/ideabank/index.php?scenario=' . self::SCENARIO_WORK,
            ],
            [
                'code' => self::SCENARIO_ADMIN,
                'badge' => 'Администратор БИ',
                'title' => 'Интерфейс администратора банка идей',
                'summary' => 'Показывает административные действия: настройки модуля, справочники, права, конкурсы, челленджи и контроль установки.',
                'difference' => 'Подходит для настройки и сопровождения раздела. Пользовательские блоки скрыты, чтобы не мешать администрированию.',
                'visibility' => 'Настройки, справочники, правила наград, конкурсы, челленджи, модерация и аналитика.',
                'impact' => 'Помогает быстро проверить целостность модуля и перейти к нужной административной зоне.',
                'url' => '/ideabank/index.php?scenario=' . self::SCENARIO_ADMIN,
            ],
        ];

        if (empty($permissions['canViewModeration']) && empty($permissions['canReviewExpertise']) && empty($permissions['canMakeCommitteeDecision']) && empty($permissions['canAdminIdeabank'])) {
            $options = array_values(array_filter($options, static fn(array $option): bool => ($option['code'] ?? '') !== self::SCENARIO_WORK));
            $scenario = self::SCENARIO_EMPLOYEE;
        }
        if (empty($permissions['canAdminIdeabank'])) {
            $options = array_values(array_filter($options, static fn(array $option): bool => ($option['code'] ?? '') !== self::SCENARIO_ADMIN));
            if ($scenario === self::SCENARIO_ADMIN) {
                $scenario = self::SCENARIO_EMPLOYEE;
            }
        }

        $current = $options[0];
        foreach ($options as $option) {
            if ($option['code'] === $scenario) {
                $current = $option;
                break;
            }
        }

        return [
            'current' => $current,
            'currentUser' => $currentUser,
            'options' => $options,
        ];
    }

    private function buildRoleWidget(string $scenario, array $currentUser, array $myIdeas, array $roleQueues, array $permissions): array
    {
        if ($scenario === self::SCENARIO_ADMIN && !empty($permissions['canAdminIdeabank'])) {
            return $this->buildAdminRoleWidget();
        }

        if ($scenario === self::SCENARIO_WORK) {
            $queueConfig = [
                'moderation' => !empty($permissions['canViewModeration']),
                'expert' => !empty($permissions['canReviewExpertise']),
                'committee' => !empty($permissions['canMakeCommitteeDecision']),
                'implementation' => !empty($permissions['canViewModeration']) || !empty($permissions['canMakeCommitteeDecision']) || !empty($permissions['canAdminIdeabank']),
            ];
            $roleQueues = array_intersect_key($roleQueues, array_filter($queueConfig));
            $workMetrics = [
                [
                    'label' => 'На модерации',
                    'value' => count($roleQueues['moderation']['items'] ?? []),
                    'caption' => 'Идеи ожидают первичной проверки и решения о публикации.',
                ],
                [
                    'label' => 'Экспертная оценка',
                    'value' => count($roleQueues['expert']['items'] ?? []),
                    'caption' => 'Материалы требуют заключения перед комитетом.',
                ],
                [
                    'label' => 'Комитет',
                    'value' => count($roleQueues['committee']['items'] ?? []),
                    'caption' => 'Вопросы ждут управленческого решения.',
                ],
                [
                    'label' => 'В реализации',
                    'value' => count($roleQueues['implementation']['items'] ?? []),
                    'caption' => 'Идеи уже переданы в работу и требуют сопровождения.',
                ],
            ];

            return [
                'nextAction' => [
                    'eyebrow' => 'Рабочий фокус роли',
                    'badge' => 'Операционный режим',
                    'title' => 'Следующий шаг для модерации и комитета',
                    'text' => 'Проверьте очередь модерации, откройте аналитику и двигайте идеи по процессу без лишнего контента.',
                    'actions' => array_values(array_filter([
                        !empty($permissions['canViewModeration']) ? ['title' => 'Открыть модерацию', 'url' => '/ideabank/management.php?mode=moderation', 'primary' => true] : null,
                        ['title' => 'Открыть аналитику', 'url' => '/ideabank/stats.php?scope=general'],
                    ])),
                ],
                'workMetrics' => $workMetrics,
                'workLinks' => array_values(array_filter([
                    !empty($permissions['canViewModeration']) ? ['title' => 'Модерация', 'text' => 'Проверить новые идеи', 'url' => '/ideabank/management.php?mode=moderation'] : null,
                    !empty($permissions['canMakeCommitteeDecision']) ? ['title' => 'Комитет', 'text' => 'Открыть идеи на решение', 'url' => '/ideabank/management.php?status_id=0'] : null,
                    ['title' => 'Аналитика', 'text' => 'Смотреть воронку и сроки', 'url' => '/ideabank/stats.php?scope=general'],
                ])),
                'queues' => array_values($roleQueues),
            ];
        }

        $waiting = array_values(array_filter($myIdeas, fn(array $item): bool => $this->isIdeaWaiting($item)));
        $inWork = array_values(array_filter($myIdeas, fn(array $item): bool => $this->isIdeaInWork($item)));

        return [
            'nextAction' => [
                'eyebrow' => 'Рабочий фокус роли',
                'badge' => $currentUser['ROLE'] ?? 'Участник',
                'title' => 'Следующий шаг для автора идеи',
                'text' => 'Подайте новую идею или усилите черновик, чтобы быстрее попасть в общий контур обсуждения и получить поддержку.',
                'actions' => array_values(array_filter([
                    !empty($permissions['canSubmitIdea']) ? ['title' => 'Подать идею', 'url' => '/ideabank/ppu-form.php', 'primary' => true] : null,
                    ['title' => 'Моя статистика', 'url' => '/ideabank/stats.php?scope=mine'],
                ])),
            ],
            'queues' => [
                [
                    'title' => 'Мои идеи в ожидании решения',
                    'empty' => 'Нет идей, которые ждут решения.',
                    'items' => array_slice($waiting, 0, 5),
                ],
                [
                    'title' => 'Мои идеи в работе',
                    'empty' => 'Нет идей в активной реализации.',
                    'items' => array_slice($inWork, 0, 5),
                ],
            ],
        ];
    }

    private function buildAdminRoleWidget(): array
    {
        return [
            'nextAction' => [
                'eyebrow' => 'Администрирование',
                'badge' => 'Администратор БИ',
                'title' => 'Настройте и проверьте банк идей',
                'text' => 'Управляйте настройками, справочниками, конкурсами, правилами наград и рабочими очередями модуля.',
                'actions' => [
                    ['title' => 'Настройки модуля', 'url' => '/bitrix/admin/settings.php?mid=uds.ideabank2&lang=ru', 'primary' => true],
                    ['title' => 'Реестр идей', 'url' => '/bitrix/admin/uds_ideabank2.php'],
                    ['title' => 'Публичная аналитика', 'url' => '/ideabank/stats.php?scope=general'],
                ],
            ],
            'workMetrics' => [
                [
                    'label' => 'Структура',
                    'value' => 'OK',
                    'caption' => 'Публичные страницы, компоненты и assets устанавливаются вместе с модулем.',
                ],
                [
                    'label' => 'Права',
                    'value' => '5',
                    'caption' => 'Участники, модераторы, эксперты, комитет и администраторы банка идей.',
                ],
                [
                    'label' => 'Справочники',
                    'value' => '3',
                    'caption' => 'Статусы, категории и правила наград доступны в админке.',
                ],
                [
                    'label' => 'Данные',
                    'value' => 'Demo',
                    'caption' => 'Демо-набор можно запустить отдельно для полноценного обзора.',
                ],
            ],
            'workLinks' => [
                ['title' => 'Модерация', 'text' => 'Очередь проверки идей', 'url' => '/bitrix/admin/uds_ideabank2_moderation.php'],
                ['title' => 'Категории', 'text' => 'Направления и классификация', 'url' => '/bitrix/admin/uds_ideabank2_categories.php'],
                ['title' => 'Статусы', 'text' => 'Workflow и этапы идеи', 'url' => '/bitrix/admin/uds_ideabank2_statuses.php'],
                ['title' => 'Награды', 'text' => 'Правила начисления коинов', 'url' => '/bitrix/admin/uds_ideabank2_rewards.php'],
                ['title' => 'Конкурсы', 'text' => 'Программы и участие идей', 'url' => '/bitrix/admin/uds_ideabank2_contests.php'],
                ['title' => 'Челленджи', 'text' => 'Тематические сборы идей', 'url' => '/bitrix/admin/uds_ideabank2_challenges.php'],
            ],
            'queues' => [],
        ];
    }

    private function buildAdminSettingsWidget(array $message): array
    {
        $features = ModuleOptions::getFeatures();

        return [
            'message' => $message,
            'sections' => [
                [
                    'title' => 'Основной процесс',
                    'text' => 'Как идеи попадают в банк, проходят проверку и становятся публичными.',
                    'fields' => [
                        $this->buildToggleField('moderation_auto_approve', 'Автоодобрение модерации', 'Идеи публикуются без ручной проверки модератором.', ModuleOptions::getBool('moderation_auto_approve')),
                        $this->buildToggleField('enable_anonymous', 'Анонимные идеи', 'Разрешить сотрудникам скрывать автора идеи.', ModuleOptions::getBool('enable_anonymous')),
                        $this->buildToggleField('enable_expert_review', 'Экспертная оценка', 'Включить этап экспертного заключения.', ModuleOptions::getBool('enable_expert_review')),
                        $this->buildToggleField('enable_committee', 'Комитет', 'Включить управленческое решение комитетом.', ModuleOptions::getBool('enable_committee')),
                    ],
                ],
                [
                    'title' => 'Коины и признание',
                    'text' => 'Базовые суммы начислений, лидерборд и ограничения будущего магазина.',
                    'fields' => [
                        $this->buildNumberField('coin_submission', 'За подачу идеи', 'Стартовый бонус автору после отправки идеи.', ModuleOptions::getInt('coin_submission'), 0),
                        $this->buildNumberField('coin_accepted', 'За принятие в работу', 'Бонус за переход идеи на следующий уровень признания.', ModuleOptions::getInt('coin_accepted'), 0),
                        $this->buildNumberField('coin_implemented', 'За реализацию', 'Максимальная награда за внедренный эффект.', ModuleOptions::getInt('coin_implemented'), 0),
                        $this->buildNumberField('max_leaderboard', 'Размер рейтинга', 'Сколько участников показывать в лидерборде.', ModuleOptions::getInt('max_leaderboard'), 1, 200),
                        $this->buildNumberField('shop_monthly_limit', 'Лимит магазина в месяц', '0 — без отдельного лимита. Пользовательский магазин пока не завершен.', ModuleOptions::getInt('shop_monthly_limit'), 0),
                        $this->buildNumberField('shop_min_balance_after_purchase', 'Минимальный остаток', 'Баланс, ниже которого нельзя списывать коины.', ModuleOptions::getInt('shop_min_balance_after_purchase'), 0),
                    ],
                ],
                [
                    'title' => 'Публичные страницы',
                    'text' => 'Какие разделы банка идей доступны пользователям в публичном интерфейсе.',
                    'fields' => $this->buildFeatureFields($features, [
                        'feature_public_home' => ['Главная', 'Публичная витрина банка идей.'],
                        'feature_public_news' => ['Новости', 'Новости и кейсы улучшений.'],
                        'feature_public_contests' => ['Конкурсы и челленджи', 'Программы развития и тематические сборы идей.'],
                        'feature_public_docs' => ['Документы', 'Регламенты, шаблоны и материалы.'],
                        'feature_public_stats' => ['Статистика', 'Публичная аналитика по идеям и коинам.'],
                        'feature_public_hall' => ['Аллея славы', 'Рейтинг авторов и амбассадоров.'],
                        'feature_public_quotes' => ['Голоса авторов', 'Блок с короткими цитатами, собранными из опубликованных идей.'],
                        'feature_public_idea_detail' => ['Карточка идеи', 'Детальная страница идеи.'],
                        'feature_public_idea_form' => ['Форма подачи', 'Создание черновика и отправка идеи.'],
                    ]),
                ],
                [
                    'title' => 'Возможности идеи',
                    'text' => 'Функции, которые влияют на путь идеи, обсуждение и рабочие сценарии.',
                    'fields' => $this->buildFeatureFields($features, [
                        'feature_moderation' => ['Модерация', 'Очередь проверки и решения publish/revise/reject.'],
                        'feature_expertise' => ['Экспертиза', 'Дополнительная оценка профильными экспертами.'],
                        'feature_committee' => ['Комитет', 'Коллегиальное решение по идеям.'],
                        'feature_drafts' => ['Черновики', 'Сохранение идеи до отправки.'],
                        'feature_edit_after_submit' => ['Редактирование после подачи', 'Автор может менять идею после отправки.'],
                        'feature_comments' => ['Комментарии', 'Обсуждение идеи в карточке.'],
                        'feature_reactions' => ['Реакции', 'Поддержка идеи коллегами.'],
                        'feature_voting' => ['Голосование', 'Отдельный режим голосования, если он нужен процессу.'],
                    ]),
                ],
                [
                    'title' => 'Коины, магазин и dev',
                    'text' => 'Флаги начислений, лидерборда, магазина наград и безопасной разработки.',
                    'fields' => array_merge(
                        $this->buildFeatureFields($features, [
                            'feature_coins' => ['Коины', 'Включить баланс, историю и начисления.'],
                            'feature_auto_coin_accrual' => ['Автоначисление', 'Начислять коины по событиям идеи.'],
                            'feature_manual_coin_accrual' => ['Ручное начисление', 'Разрешить ручные операции с коинами.'],
                            'feature_rewards' => ['Правила наград', 'Использовать справочник правил начисления.'],
                            'feature_leaderboard' => ['Лидерборд', 'Показывать рейтинг авторов.'],
                            'feature_shop' => ['Магазин наград', 'Только флаг будущего магазина, полного контура пока нет.'],
                            'feature_shop_orders' => ['Заказы магазина', 'Контур заказов магазина наград.'],
                            'feature_shop_order_moderation' => ['Модерация заказов', 'Проверка заказов перед списанием.'],
                            'feature_shop_cancel_order' => ['Отмена заказов', 'Разрешить отмену заказов магазина.'],
                            'feature_demo_data' => ['Демо-данные', 'Отметка, что обзорный демо-набор был установлен.'],
                            'debug_auth_enabled' => ['Debug-auth', 'Dev-only вход для тестирования через whitelist.'],
                        ]),
                        [
                            $this->buildTextField('debug_auth_allowed_user_ids', 'Whitelist debug-auth', 'ID пользователей через запятую. Используется только в dev/debug режиме.', ModuleOptions::getString('debug_auth_allowed_user_ids', '')),
                        ]
                    ),
                ],
            ],
            'roleGroups' => $this->buildRoleGroupRows(),
            'selfCheck' => SelfCheck::run(),
        ];
    }

    private function buildToggleField(string $name, string $title, string $text, bool $enabled): array
    {
        return [
            'type' => 'toggle',
            'name' => $name,
            'title' => $title,
            'text' => $text,
            'enabled' => $enabled,
        ];
    }

    private function buildNumberField(string $name, string $title, string $text, int $value, int $min = 0, ?int $max = null): array
    {
        return [
            'type' => 'number',
            'name' => $name,
            'title' => $title,
            'text' => $text,
            'value' => $value,
            'min' => $min,
            'max' => $max,
        ];
    }

    private function buildTextField(string $name, string $title, string $text, string $value): array
    {
        return [
            'type' => 'text',
            'name' => $name,
            'title' => $title,
            'text' => $text,
            'value' => $value,
        ];
    }

    private function buildFeatureFields(array $features, array $labels): array
    {
        $fields = [];
        foreach ($labels as $name => $label) {
            $fields[] = $this->buildToggleField($name, (string)$label[0], (string)$label[1], !empty($features[$name]));
        }

        return $fields;
    }

    private function buildRoleGroupRows(): array
    {
        $labels = [
            'participants' => 'Участники',
            'moderators' => 'Модераторы',
            'experts' => 'Эксперты',
            'committee' => 'Комитет',
            'admins' => 'Администраторы БИ',
        ];
        $rows = [];
        foreach (ModuleOptions::getRoleGroupOptionNames() as $roleCode => $optionName) {
            $rows[] = [
                'title' => $labels[$roleCode] ?? $roleCode,
                'option' => $optionName,
                'value' => (int)ModuleOptions::getString($optionName, '0'),
            ];
        }

        return $rows;
    }

    private function buildCoinWidget(array $coinTotals, array $rewardRules, array $coinHistory): array
    {
        $balance = (int)($coinTotals['balance'] ?? 0);

        return [
            'totals' => $coinTotals,
            'level' => $this->buildCoinLevel($balance),
            'history' => $this->prepareCoinHistory($coinHistory),
            'tiles' => [
                $this->buildRewardTile($rewardRules, ['submitted'], 'Новая идея', 'Бонус сразу после подачи идеи.', 'idea', true),
                $this->buildRewardTile($rewardRules, ['implemented'], 'Реализация', 'Максимальная награда за внедренный эффект.', 'rocket'),
                $this->buildRewardTile($rewardRules, ['engagement'], 'Отклик коллег', 'За обсуждение, голоса и доработку вместе с командой.', 'team'),
                $this->buildRewardTile($rewardRules, ['accepted'], 'Принято в работу', 'Следующий уровень признания за сильное решение.', 'check'),
            ],
        ];
    }

    private function buildCoinLevel(int $balance): array
    {
        $levels = [
            ['min' => 0, 'target' => 300, 'title' => 'Новый автор идей'],
            ['min' => 300, 'target' => 1000, 'title' => 'Участник улучшений'],
            ['min' => 1000, 'target' => 2500, 'title' => 'Активный автор идей'],
            ['min' => 2500, 'target' => 5000, 'title' => 'Лидер изменений'],
            ['min' => 5000, 'target' => 5000, 'title' => 'Амбассадор улучшений'],
        ];

        $level = $levels[0];
        foreach ($levels as $item) {
            if ($balance >= $item['min']) {
                $level = $item;
            }
        }

        $target = max((int)$level['target'], 1);
        $left = max($target - $balance, 0);
        $progress = $target > 0 ? min(100, (int)round($balance / $target * 100)) : 100;

        return [
            'title' => $level['title'],
            'target' => $target,
            'left' => $left,
            'progress' => $progress,
        ];
    }

    private function prepareCoinHistory(array $coinHistory): array
    {
        $items = [];
        foreach (array_slice($coinHistory, 0, 4) as $coin) {
            if (!is_array($coin)) {
                continue;
            }

            $items[] = [
                'amount' => (int)($coin['COINS'] ?? 0),
                'badge' => $this->getCoinOperationBadge($coin),
                'title' => $this->getCoinOperationTitle($coin),
                'idea' => (string)($coin['IDEA_TITLE'] ?? $coin['TITLE'] ?? ''),
                'date' => $coin['CREATED_AT'] ?? null,
            ];
        }

        return $items;
    }

    private function getCoinOperationBadge(array $coin): string
    {
        $event = mb_strtolower((string)($coin['EVENT'] ?? ''));
        $description = mb_strtolower((string)($coin['DESCRIPTION'] ?? ''));

        if ($event === 'submitted' || str_contains($description, 'подач')) {
            return 'Подача';
        }

        if ($event === 'accepted' || str_contains($description, 'принят')) {
            return 'Принято';
        }

        if ($event === 'implemented' || str_contains($description, 'реализац')) {
            return 'Реализация';
        }

        if ($event === 'engagement' || str_contains($description, 'отклик')) {
            return 'Отклик';
        }

        return 'Баланс';
    }

    private function getCoinOperationTitle(array $coin): string
    {
        $description = trim((string)($coin['DESCRIPTION'] ?? ''));
        if ($description === '') {
            return 'Операция по коинам';
        }

        return preg_replace('/^Демо-начисление за\s+/u', '', $description) ?: $description;
    }

    private function buildRewardTile(array $rewardRules, array $events, string $title, string $text, string $icon, bool $accent = false): array
    {
        $rule = $this->findRewardRule($rewardRules, $events);

        return [
            'title' => $title,
            'value' => (int)($rule['COINS'] ?? 0),
            'text' => $text,
            'icon' => preg_replace('/[^a-z0-9_-]/i', '', $icon),
            'accent' => $accent,
        ];
    }

    private function findRewardRule(array $rewardRules, array $events): array
    {
        $events = array_map('mb_strtolower', $events);

        foreach ($rewardRules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $event = mb_strtolower((string)($rule['EVENT'] ?? ''));
            if (in_array($event, $events, true)) {
                return $rule;
            }
        }

        return [];
    }

    private function buildHero(string $scenario, array $permissions): array
    {
        if ($scenario === self::SCENARIO_ADMIN) {
            return [
                'title' => 'Администрируйте банк идей как цельный раздел.',
                'text' => 'Проверяйте настройки, права, справочники, конкурсы, челленджи и рабочие маршруты без потери обычного пользовательского режима.',
                'actions' => [
                    ['title' => 'Предложить идею', 'url' => '/ideabank/ppu-form.php', 'primary' => true],
                    ['title' => 'Настройки модуля', 'url' => '/bitrix/admin/settings.php?mid=uds.ideabank2&lang=ru'],
                    ['title' => 'Реестр идей', 'url' => '/bitrix/admin/uds_ideabank2.php'],
                ],
            ];
        }

        if ($scenario === self::SCENARIO_WORK) {
            return [
                'title' => 'Очереди и решения для модерации, экспертной оценки и комитета.',
                'text' => 'В этом сценарии скрыт вовлекающий контент сотрудника и оставлены только рабочие блоки: где идеи ждут проверки, какие вопросы вынесены на комитет и что уже запущено в работу.',
                'actions' => array_values(array_filter([
                    !empty($permissions['canSubmitIdea']) ? ['title' => 'Предложить идею', 'url' => '/ideabank/ppu-form.php', 'primary' => true] : null,
                    !empty($permissions['canViewModeration']) ? ['title' => 'Открыть модерацию', 'url' => '/ideabank/management.php?mode=moderation'] : null,
                    ['title' => 'Открыть аналитику', 'url' => '/ideabank/stats.php?scope=general'],
                ])),
            ];
        }

        return [
            'title' => 'Настраивай бизнес. Меняй бизнес.',
            'text' => 'Предлагай идеи, собирай поддержку и чувствуй силу своего вклада.',
            'actions' => array_values(array_filter([
                !empty($permissions['canSubmitIdea']) ? ['title' => 'Предложить идею', 'url' => '/ideabank/ppu-form.php', 'primary' => true] : null,
            ])),
        ];
    }

    private function buildProcessSteps(array $permissions, bool $formEnabled, array $shareIdeas): array
    {
        return [
            [
                'number' => 1,
                'icon' => '✍',
                'title' => 'Заметьте возможность',
                'text' => 'Видите, что мешает работе или где команда теряет время? Опишите идею — с этого начинаются изменения.',
                'action' => $formEnabled
                    ? [
                        'title' => 'Предложить свою идею',
                        'url' => '/ideabank/ppu-form.php',
                        'primary' => true,
                        'disabled' => empty($permissions['canSubmitIdea']),
                        'hint' => 'Войдите в портал, чтобы подать идею.',
                    ]
                    : null,
            ],
            [
                'number' => 2,
                'icon' => '🤝',
                'title' => 'Соберите поддержку коллег',
                'text' => $shareIdeas !== []
                    ? 'Коллеги ставят реакции и помогают идее набрать вес. Чем больше поддержки, тем выше шанс на реализацию.'
                    : 'Коллеги ставят реакции и помогают идее набрать вес. Автор получает признание и вознаграждение.',
                'shareIdeas' => $shareIdeas,
            ],
            [
                'number' => 3,
                'icon' => '🚀',
                'title' => 'Доведите до результата',
                'text' => 'Сильные идеи переходят в работу, меняют процессы и показывают реальный вклад автора и команды.',
                'action' => ['title' => 'Посмотреть реализованные идеи', 'url' => '#best-ideas', 'primary' => false],
            ],
        ];
    }

    private function buildShareIdeas(array $myIdeas): array
    {
        $items = [];

        foreach ($myIdeas as $idea) {
            if (!is_array($idea) || (string)($idea['IS_DRAFT'] ?? 'N') === 'Y') {
                continue;
            }

            $id = (int)($idea['ID'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $items[] = [
                'ID' => $id,
                'TITLE' => (string)($idea['TITLE'] ?? ('Идея #' . $id)),
                'URL' => (string)($idea['DETAIL_URL'] ?? ('/ideabank/ppu-detail.php?id=' . $id)),
                'STATUS_LABEL' => (string)($idea['STATUS']['NAME'] ?? $idea['STAGE_LABEL'] ?? ''),
            ];
        }

        return array_slice($items, 0, 20);
    }

    private function prepareChallengeCards(array $challenges): array
    {
        $items = [];
        $statsMap = ChallengeService::getStatsForChallenges(array_map(static function ($challenge): int {
            return is_array($challenge) ? (int)($challenge['ID'] ?? 0) : 0;
        }, $challenges));

        foreach ($challenges as $challenge) {
            if (!is_array($challenge)) {
                continue;
            }

            $direction = trim((string)($challenge['BUSINESS_DIRECTION'] ?? ''));
            $title = trim((string)($challenge['TITLE'] ?? ''));
            $challengeStats = $statsMap[(int)($challenge['ID'] ?? 0)] ?? ['total' => 0, 'items' => $this->buildEmptyChallengeStatusStats()];
            $challenge['ICON'] = $this->resolveChallengeIcon($direction, $title);
            $challenge['IDEA_STATS'] = is_array($challengeStats['items'] ?? null) ? $challengeStats['items'] : $this->buildEmptyChallengeStatusStats();
            $challenge['IDEA_TOTAL'] = (int)($challengeStats['total'] ?? 0);
            $items[] = $challenge;
        }

        return $items;
    }

    private function buildEmptyChallengeStatusStats(): array
    {
        return [
            ['label' => 'обсуждаются', 'value' => 0],
            ['label' => 'приняты', 'value' => 0],
            ['label' => 'реализованы', 'value' => 0],
        ];
    }

    private function resolveChallengeIcon(string $direction, string $title): string
    {
        $text = mb_strtolower($direction . ' ' . $title);

        if (str_contains($text, 'безопас')) {
            return 'shield';
        }
        if (str_contains($text, 'качеств')) {
            return 'quality';
        }
        if (str_contains($text, 'сервис') || str_contains($text, 'цифр') || str_contains($text, 'врем')) {
            return 'time';
        }

        return 'target';
    }

    private function buildTrustMetrics(array $stats): array
    {
        return [
            ['value' => (int)($stats['total'] ?? 0), 'label' => 'идей уже в системе', 'caption' => 'Команда уже делится тем, что можно улучшить.'],
            ['value' => (int)($stats['published'] ?? 0), 'label' => 'идей набирают поддержку', 'caption' => 'Ваш голос помогает лучшим инициативам перейти в работу.'],
            ['value' => (int)($stats['implemented'] ?? 0), 'label' => 'идей уже внедрено', 'caption' => 'Их предложили сотрудники, которые просто решили начать.'],
        ];
    }

    private function buildQuickLinks(array $features): array
    {
        return array_values(array_filter([
            !empty($features['feature_public_docs']) ? ['title' => 'Документация', 'text' => 'Регламенты и шаблоны', 'url' => '/ideabank/docs.php', 'class' => 'quick-link-card--docs'] : null,
            ['title' => 'Список идей', 'text' => 'Инициативы, черновики и лучшие практики', 'url' => '/ideabank/management.php', 'class' => 'quick-link-card--list'],
            !empty($features['feature_public_contests']) ? ['title' => 'Конкурсы', 'text' => 'Программы развития и челленджи', 'url' => '/ideabank/contests.php', 'class' => 'quick-link-card--contest'] : null,
        ]));
    }

    private function buildGuidanceCards(): array
    {
        $documents = PublicDataService::getDocumentItems();
        shuffle($documents);

        return array_map(static function (array $item): array {
            return [
                'title' => (string)($item['title'] ?? ''),
                'text' => (string)($item['description'] ?? ''),
                'type' => (string)($item['type'] ?? 'Документ'),
                'url' => (string)($item['url'] ?? '/ideabank/docs.php'),
            ];
        }, array_slice($documents, 0, 4));
    }

    private function buildQuotes(array $ideas): array
    {
        $quotes = [];

        foreach (array_slice($ideas, 0, 3) as $idea) {
            if (!is_array($idea)) {
                continue;
            }

            $text = trim((string)($idea['EXCERPT'] ?? $idea['TITLE'] ?? ''));
            if ($text === '') {
                continue;
            }

            $quotes[] = [
                'text' => $text,
                'author' => (string)($idea['OWNER_LABEL'] ?? 'Автор идеи'),
            ];
        }

        return $quotes;
    }

    private function prepareRoleQueues(array $roleQueues, array $profiles): array
    {
        $config = [
            'moderation' => ['title' => 'Очередь модерации', 'empty' => 'На модерации пока нет идей.'],
            'expert' => ['title' => 'Очередь эксперта', 'empty' => 'Нет идей, ожидающих экспертного заключения.'],
            'committee' => ['title' => 'Очередь комитета', 'empty' => 'Идей для заседания пока нет.'],
            'implementation' => ['title' => 'Идеи в работе', 'empty' => 'Сейчас нет идей в активной реализации.'],
        ];

        $result = [];
        foreach ($config as $code => $queueConfig) {
            $result[$code] = [
                'code' => $code,
                'title' => $queueConfig['title'],
                'empty' => $queueConfig['empty'],
                'items' => $this->prepareIdeaCards(is_array($roleQueues[$code] ?? null) ? $roleQueues[$code] : [], $profiles),
            ];
        }

        return $result;
    }

    private function isIdeaWaiting(array $item): bool
    {
        $status = mb_strtolower((string)((is_array($item['STATUS'] ?? null) ? ($item['STATUS']['NAME'] ?? '') : '')));
        $code = mb_strtolower((string)((is_array($item['STATUS'] ?? null) ? ($item['STATUS']['CODE'] ?? '') : '')));

        return in_array($code, ['moderation', 'revise', 'published'], true)
            || str_contains($status, 'модерац')
            || str_contains($status, 'доработ')
            || str_contains($status, 'проверк')
            || str_contains($status, 'кпу');
    }

    private function isIdeaInWork(array $item): bool
    {
        $status = mb_strtolower((string)((is_array($item['STATUS'] ?? null) ? ($item['STATUS']['NAME'] ?? '') : '')));
        $code = mb_strtolower((string)((is_array($item['STATUS'] ?? null) ? ($item['STATUS']['CODE'] ?? '') : '')));

        return in_array($code, ['accepted', 'implemented'], true)
            || str_contains($status, 'принят')
            || str_contains($status, 'реализ')
            || str_contains($status, 'внедрен')
            || str_contains($status, 'проект');
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
