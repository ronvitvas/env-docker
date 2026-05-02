<?php

declare(strict_types=1);

namespace Uds\Ideabank2\Domain;

use Bitrix\Main\Application;
use Uds\Ideabank2\Config\Feature;
use Uds\Ideabank2\Config\ModuleOptions;
use Uds\Ideabank2\Table\IdeaCategoryTable;
use Uds\Ideabank2\Table\IdeaChallengeTable;
use Uds\Ideabank2\Table\IdeaCommentTable;
use Uds\Ideabank2\Table\IdeaCommitteeTable;
use Uds\Ideabank2\Table\IdeaContestTable;
use Uds\Ideabank2\Table\IdeaExpertReviewTable;
use Uds\Ideabank2\Table\IdeaFeedbackTable;
use Uds\Ideabank2\Table\IdeaNewsTable;
use Uds\Ideabank2\Table\IdeaReactionTable;
use Uds\Ideabank2\Table\IdeaRewardRuleTable;
use Uds\Ideabank2\Table\IdeaStatusTable;
use Uds\Ideabank2\Table\IdeaTable;
use Uds\Ideabank2\Table\IdeaWorkflowTable;

final class PublicDataService
{
    public static function getCurrentUserId(): int
    {
        return IdeaService::currentUser();
    }

    public static function getShellData(string $active): array
    {
        $features = Feature::all();
        $permissions = self::getCurrentUserPermissions();
        $menu = [
            ['code' => 'home', 'title' => 'Банк идей', 'url' => '/ideabank/', 'feature' => 'feature_public_home'],
            ['code' => 'management', 'title' => 'Список идей', 'url' => '/ideabank/management.php'],
            ['code' => 'form', 'title' => 'Подать идею', 'url' => '/ideabank/ppu-form.php', 'feature' => 'feature_public_idea_form', 'permission' => 'canSubmitIdea'],
            ['code' => 'contests', 'title' => 'Конкурсы', 'url' => '/ideabank/contests.php', 'feature' => 'feature_public_contests'],
            ['code' => 'news', 'title' => 'Новости', 'url' => '/ideabank/news.php', 'feature' => 'feature_public_news'],
            ['code' => 'hall', 'title' => 'Аллея славы', 'url' => '/ideabank/hall-of-fame.php', 'feature' => 'feature_public_hall'],
            ['code' => 'stats', 'title' => 'Статистика', 'url' => '/ideabank/stats.php', 'feature' => 'feature_public_stats'],
            ['code' => 'docs', 'title' => 'Документы', 'url' => '/ideabank/docs.php', 'feature' => 'feature_public_docs'],
        ];

        return [
            'active' => $active,
            'menu' => array_values(array_filter($menu, static function (array $item) use ($features, $permissions): bool {
                $feature = (string)($item['feature'] ?? '');
                $permission = (string)($item['permission'] ?? '');

                if ($feature !== '' && empty($features[$feature])) {
                    return false;
                }

                return $permission === '' || !empty($permissions[$permission]);
            })),
            'features' => $features,
            'permissions' => $permissions,
        ];
    }

    public static function getRuntimeMeta(): array
    {
        return [
            'features' => Feature::all(),
            'permissions' => self::getCurrentUserPermissions(),
        ];
    }

    public static function getHomeData(): array
    {
        $userId = self::getCurrentUserId();
        $features = Feature::all();
        $ideas = self::getIdeas(['=IS_HIDDEN' => 'N'], 6);
        $stats = IdeaService::getStats();

        return [
            'shell' => self::getShellData('home'),
            'stats' => $stats,
            'coinBalance' => !empty($features['feature_coins']) ? CoinService::getBalance($userId) : 0,
            'coinTotals' => !empty($features['feature_coins']) ? CoinService::getTotals($userId) : [],
            'coinHistory' => !empty($features['feature_coins']) ? CoinService::getHistory($userId, 5) : [],
            'rewardRules' => !empty($features['feature_rewards']) ? self::getRewardRules() : [],
            'leaderboard' => !empty($features['feature_leaderboard']) ? CoinService::getLeaderboard(10) : [],
            'news' => !empty($features['feature_public_news']) ? self::getNews(3) : [],
            'challenges' => !empty($features['feature_public_contests']) ? self::getChallenges(4) : [],
            'ideas' => $ideas,
            'roleQueues' => self::getRoleQueues(),
            'meta' => self::getRuntimeMeta(),
        ];
    }

    public static function getCurrentUserPermissions(): array
    {
        $userId = self::getCurrentUserId();
        $features = Feature::all();
        $isAdmin = self::isCurrentUserSystemAdmin() || self::isCurrentUserInConfiguredGroup('admins');
        $isModerator = self::isCurrentUserInConfiguredGroup('moderators');
        $canManageIdeas = $isModerator || $isAdmin;

        return [
            'userId' => $userId,
            'canSubmitIdea' => $userId > 0 && !empty($features['feature_public_idea_form']),
            'canUseDrafts' => !empty($features['feature_drafts']),
            'canEditAfterSubmit' => !empty($features['feature_edit_after_submit']),
            'canViewModeration' => !empty($features['feature_moderation']) && $canManageIdeas,
            'canReviewExpertise' => !empty($features['feature_expertise']) && (self::isCurrentUserInConfiguredGroup('experts') || $isAdmin),
            'canMakeCommitteeDecision' => !empty($features['feature_committee']) && (self::isCurrentUserInConfiguredGroup('committee') || $isAdmin),
            'canAdminIdeabank' => $isAdmin,
            'canViewCoins' => !empty($features['feature_coins']),
            'canViewRewards' => !empty($features['feature_rewards']),
            'canViewLeaderboard' => !empty($features['feature_leaderboard']),
            'canViewShop' => !empty($features['feature_shop']),
        ];
    }

    private static function isCurrentUserInConfiguredGroup(string $roleCode): bool
    {
        global $USER;

        if (!is_object($USER) || !method_exists($USER, 'GetUserGroupArray')) {
            return false;
        }

        $optionName = ModuleOptions::getRoleGroupOptionNames()[$roleCode] ?? '';
        $groupId = $optionName !== '' ? (int)ModuleOptions::getString($optionName, '0') : 0;
        if ($groupId <= 0) {
            return false;
        }

        return in_array($groupId, array_map('intval', (array)$USER->GetUserGroupArray()), true);
    }

    private static function isCurrentUserSystemAdmin(): bool
    {
        global $USER;

        return is_object($USER) && method_exists($USER, 'IsAdmin') && (bool)$USER->IsAdmin();
    }

    public static function getIdeaListData(array $query): array
    {
        $filter = [];
        $permissions = self::getCurrentUserPermissions();
        $mode = self::normalizeIdeaListMode((string)($query['mode'] ?? 'all'), $permissions);
        $search = trim((string)($query['q'] ?? ''));
        $page = max(1, (int)($query['page'] ?? 1));
        $pageSize = 20;

        if ($search !== '') {
            $filter[] = [
                'LOGIC' => 'OR',
                '%TITLE' => $search,
                '%DESCRIPTION' => $search,
                '%PROBLEM' => $search,
                '%PROPOSAL' => $search,
                '%KEYWORDS' => $search,
                '=CODE' => $search,
            ];
        }

        $userId = self::getCurrentUserId();
        if ($mode === 'mine') {
            $filter['=OWNER_USER_ID'] = $userId;
        } elseif ($mode === 'drafts') {
            $filter['=OWNER_USER_ID'] = $userId;
            $filter['=IS_DRAFT'] = 'Y';
        } elseif ($mode === 'moderation') {
            $filter['=STAGE'] = 'moderation';
        } elseif ($mode === 'best') {
            $filter['=STAGE'] = 'implemented';
        }

        if (in_array($mode, ['all', 'best'], true)) {
            $filter['=IS_HIDDEN'] = 'N';
        }

        $statusId = (int)($query['status_id'] ?? 0);
        $categoryId = (int)($query['category_id'] ?? 0);
        if ($statusId > 0) {
            $filter['=STATUS_ID'] = $statusId;
        }
        if ($categoryId > 0) {
            $filter['=CATEGORY_ID'] = $categoryId;
        }

        $items = self::fetchAll(
            IdeaTable::class,
            ['ID' => 'DESC'],
            $filter,
            $pageSize,
            ($page - 1) * $pageSize
        );

        $statuses = self::indexById(self::getStatuses());
        $categories = self::indexById(self::getCategories());
        foreach ($items as &$item) {
            $item['STATUS'] = $statuses[(int)($item['STATUS_ID'] ?? 0)] ?? null;
            $item['CATEGORY'] = $categories[(int)($item['CATEGORY_ID'] ?? 0)] ?? null;
        }
        unset($item);

        $total = IdeaTable::getCount($filter);

        return [
            'shell' => self::getShellData('management'),
            'items' => $items,
            'statuses' => self::getStatuses(),
            'categories' => self::getCategories(),
            'query' => $query,
            'mode' => $mode,
            'search' => $search,
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => (int)$total,
                'pages' => (int)max(1, (int)ceil(((int)$total) / $pageSize)),
            ],
            'meta' => self::getRuntimeMeta(),
        ];
    }

    private static function normalizeIdeaListMode(string $mode, array $permissions): string
    {
        if ($mode === 'drafts' && empty($permissions['canUseDrafts'])) {
            return 'mine';
        }

        if ($mode === 'moderation' && empty($permissions['canViewModeration'])) {
            return 'all';
        }

        return in_array($mode, ['all', 'mine', 'drafts', 'best', 'moderation'], true) ? $mode : 'all';
    }

    public static function getIdeaFormData(array $query): array
    {
        $id = (int)($query['id'] ?? 0);
        return [
            'shell' => self::getShellData('form'),
            'idea' => $id > 0 ? self::getIdeaDetail($id) : null,
            'statuses' => self::getStatuses(),
            'categories' => self::getCategories(),
            'businessDirections' => self::getBusinessDirections(),
            'challenges' => self::getChallenges(100),
            'actionUrl' => '/local/modules/uds.ideabank2/.ajax.php',
            'meta' => self::getRuntimeMeta(),
        ];
    }

    public static function getIdeaDetailData(array $query): array
    {
        $id = (int)($query['id'] ?? 0);
        $features = Feature::all();
        $permissions = self::getCurrentUserPermissions();
        $idea = $id > 0 ? self::getIdeaDetail($id) : null;
        $canLoadRelatedData = $idea !== null;

        return [
            'shell' => self::getShellData('management'),
            'idea' => $idea,
            'comments' => $canLoadRelatedData && !empty($features['feature_comments']) ? self::getComments($id) : [],
            'reactions' => $canLoadRelatedData && !empty($features['feature_reactions']) ? IdeaService::getReactionCount($id) : ['like' => 0, 'dislike' => 0],
            'workflow' => $canLoadRelatedData ? self::getWorkflow($id) : [],
            'feedback' => $canLoadRelatedData && (!empty($permissions['canViewModeration']) || !empty($permissions['canAdminIdeabank'])) ? self::getFeedback($id) : [],
            'expertReviews' => $canLoadRelatedData && !empty($features['feature_expertise']) && (!empty($permissions['canReviewExpertise']) || !empty($permissions['canAdminIdeabank'])) ? self::getExpertReviews($id) : [],
            'committeeDecision' => $canLoadRelatedData && !empty($features['feature_committee']) && (!empty($permissions['canMakeCommitteeDecision']) || !empty($permissions['canAdminIdeabank'])) ? self::getCommitteeDecision($id) : null,
            'meta' => self::getRuntimeMeta(),
        ];
    }

    public static function getContestListData(): array
    {
        return ['shell' => self::getShellData('contests'), 'items' => self::fetchAll(IdeaContestTable::class, ['ID' => 'DESC'])];
    }

    public static function getContestDetailData(array $query): array
    {
        $id = (int)($query['id'] ?? 0);
        return ['shell' => self::getShellData('contests'), 'item' => $id > 0 ? IdeaContestTable::getByPrimary($id)->fetch() : null];
    }

    public static function getNewsListData(): array
    {
        return ['shell' => self::getShellData('news'), 'items' => self::getNews(100)];
    }

    public static function getNewsDetailData(array $query): array
    {
        $id = (int)($query['id'] ?? 0);
        return ['shell' => self::getShellData('news'), 'item' => $id > 0 ? IdeaNewsTable::getByPrimary($id)->fetch() : null];
    }

    public static function getHallData(): array
    {
        return ['shell' => self::getShellData('hall'), 'items' => CoinService::getLeaderboard(50)];
    }

    public static function getStatsData(): array
    {
        return [
            'shell' => self::getShellData('stats'),
            'stats' => IdeaService::getStats(),
            'statuses' => self::getStatusCounters(),
            'categories' => self::getCategoryCounters(),
        ];
    }

    public static function getDocsData(): array
    {
        return [
            'shell' => self::getShellData('docs'),
            'items' => self::getDocumentItems(),
            'supportCategories' => [
                'Не удается приложить файл к инициативе',
                'Не открывается карточка идеи',
                'Не получается отправить инициативу на рассмотрение',
                'Не отображается корректный статус идеи',
            ],
        ];
    }

    public static function getDocumentItems(): array
    {
        $items = [
            ['code' => 'strong-idea', 'title' => 'Как подать сильную идею', 'description' => 'Опишите проблему, предложенное решение и ожидаемый результат для команды или бизнеса. Чем конкретнее эффект, тем проще пройти оценку.', 'type' => 'Памятка автора'],
            ['code' => 'quick-wins', 'title' => 'Что важно для быстрых побед', 'description' => 'Быстрее проходят идеи с понятным пилотом, владельцем процесса и измеримым эффектом уже в первые недели.', 'type' => 'Практика внедрения'],
            ['code' => 'idea-submit-rules', 'title' => 'Регламент подачи идеи', 'description' => 'Порядок регистрации инициативы, критерии полноты заявки и маршрут рассмотрения.', 'type' => 'Регламент'],
            ['code' => 'committee-protocol', 'title' => 'Шаблон протокола комиссии по идеям', 'description' => 'Структура решения комиссии: эффект, реализуемость, замечания и итоговый статус.', 'type' => 'Шаблон'],
            ['code' => 'contest-rules', 'title' => 'Положение о конкурсах банка идей', 'description' => 'Правила участия, критерии оценки и логика определения победителей.', 'type' => 'Положение'],
            ['code' => 'effect-calculation', 'title' => 'Шаблон расчета экономического эффекта', 'description' => 'Подсказка по оценке времени, материалов, потерь качества и подтвержденного эффекта.', 'type' => 'Шаблон'],
            ['code' => 'implementation-roadmap', 'title' => 'Дорожная карта внедрения идеи', 'description' => 'Этапы запуска пилота, сопровождение, контроль результата и переход к тиражированию.', 'type' => 'Маршрут'],
        ];

        foreach ($items as &$item) {
            $item['url'] = '/ideabank/docs.php#doc-' . $item['code'];
        }
        unset($item);

        return $items;
    }

    public static function getStatuses(): array
    {
        return self::fetchAll(IdeaStatusTable::class, ['SORT' => 'ASC', 'ID' => 'ASC']);
    }

    public static function getCategories(): array
    {
        return self::fetchAll(IdeaCategoryTable::class, ['SORT' => 'ASC', 'ID' => 'ASC']);
    }

    public static function getRewardRules(): array
    {
        return self::fetchAll(IdeaRewardRuleTable::class, ['ID' => 'ASC']);
    }

    public static function getChallenges(int $limit = 100): array
    {
        return self::fetchAll(IdeaChallengeTable::class, ['ID' => 'DESC'], [], $limit);
    }

    public static function getNews(int $limit = 100): array
    {
        return self::fetchAll(IdeaNewsTable::class, ['DATE' => 'DESC', 'ID' => 'DESC'], [], $limit);
    }

    public static function getIdeas(array $filter = [], int $limit = 100): array
    {
        $items = self::fetchAll(IdeaTable::class, ['ID' => 'DESC'], $filter, $limit);
        $statuses = self::indexById(self::getStatuses());
        $categories = self::indexById(self::getCategories());
        foreach ($items as &$item) {
            $item['STATUS'] = $statuses[(int)($item['STATUS_ID'] ?? 0)] ?? null;
            $item['CATEGORY'] = $categories[(int)($item['CATEGORY_ID'] ?? 0)] ?? null;
        }
        unset($item);
        return $items;
    }

    public static function getIdeaDetail(int $id): ?array
    {
        $idea = IdeaTable::getByPrimary($id)->fetch();
        if (!$idea) {
            return null;
        }
        $idea['AUTHORS'] = self::getAuthors($id);
        $idea['STATUS'] = self::indexById(self::getStatuses())[(int)$idea['STATUS_ID']] ?? null;
        $idea['CATEGORY'] = self::indexById(self::getCategories())[(int)$idea['CATEGORY_ID']] ?? null;
        $idea['CHALLENGE_ID'] = ChallengeService::getChallengeIdForIdea($id);
        $idea['FILES'] = IdeaService::getFiles($id);
        if ((string)($idea['IS_HIDDEN'] ?? 'N') === 'Y' && !self::canViewHiddenIdea($idea)) {
            return null;
        }
        return $idea;
    }

    private static function canViewHiddenIdea(array $idea): bool
    {
        $permissions = self::getCurrentUserPermissions();
        $userId = self::getCurrentUserId();

        return (int)($idea['OWNER_USER_ID'] ?? 0) === $userId
            || !empty($permissions['canViewModeration'])
            || !empty($permissions['canAdminIdeabank']);
    }

    public static function getBusinessDirections(): array
    {
        return ['Центр развития', 'Производство', 'Сервис', 'ОТК', 'Развитие', 'Управление', 'Качество', 'Ремонт'];
    }

    private static function getAuthors(int $ideaId): array
    {
        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();
        $table = $sqlHelper->quote('b_uds_ideabank_idea_author');

        return $connection
            ->query('SELECT * FROM ' . $table . ' WHERE IDEA_ID = ' . (int)$ideaId)
            ->fetchAll();
    }

    private static function getComments(int $ideaId): array
    {
        return self::fetchAll(IdeaCommentTable::class, ['CREATED_AT' => 'ASC'], ['=IDEA_ID' => $ideaId], 100);
    }

    private static function getWorkflow(int $ideaId): array
    {
        return self::fetchAll(IdeaWorkflowTable::class, ['CREATED_AT' => 'DESC'], ['=IDEA_ID' => $ideaId], 100);
    }

    private static function getFeedback(int $ideaId): array
    {
        return self::fetchAll(IdeaFeedbackTable::class, ['CREATED_AT' => 'DESC'], ['=IDEA_ID' => $ideaId], 100);
    }

    private static function getExpertReviews(int $ideaId): array
    {
        return self::fetchAll(IdeaExpertReviewTable::class, ['CREATED_AT' => 'DESC'], ['=IDEA_ID' => $ideaId], 100);
    }

    private static function getCommitteeDecision(int $ideaId): ?array
    {
        return IdeaCommitteeTable::getList(['filter' => ['=IDEA_ID' => $ideaId], 'order' => ['DECIDED_AT' => 'DESC'], 'limit' => 1])->fetch() ?: null;
    }

    private static function getRoleQueues(): array
    {
        return [
            'moderation' => self::getIdeas(['=STAGE' => 'moderation'], 5),
            'expert' => self::getIdeas(['=STAGE' => 'initial_review'], 5),
            'committee' => self::getIdeas(['=STAGE' => 'kpu'], 5),
            'implementation' => self::getIdeas(['=STAGE' => 'implementation'], 5),
        ];
    }

    private static function getStatusCounters(): array
    {
        return self::counter('STATUS_ID', self::indexById(self::getStatuses()));
    }

    private static function getCategoryCounters(): array
    {
        return self::counter('CATEGORY_ID', self::indexById(self::getCategories()));
    }

    private static function counter(string $field, array $dictionary): array
    {
        $connection = Application::getConnection();
        $allowedFields = ['STATUS_ID', 'CATEGORY_ID'];
        if (!in_array($field, $allowedFields, true)) {
            return [];
        }

        $sqlHelper = $connection->getSqlHelper();
        $table = $sqlHelper->quote('b_uds_ideabank_idea');
        $rows = $connection
            ->query('SELECT ' . $field . ' AS ID, COUNT(*) AS CNT FROM ' . $table . ' GROUP BY ' . $field)
            ->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $id = (int)$row['ID'];
            $result[] = ['label' => $dictionary[$id]['NAME'] ?? ('#' . $id), 'value' => (int)$row['CNT']];
        }
        return $result;
    }

    private static function fetchAll(
        string $tableClass,
        array $order = ['ID' => 'DESC'],
        array $filter = [],
        int $limit = 0,
        int $offset = 0
    ): array
    {
        $params = ['select' => ['*'], 'filter' => $filter, 'order' => $order];
        if ($limit > 0) {
            $params['limit'] = $limit;
        }
        if ($offset > 0) {
            $params['offset'] = $offset;
        }

        return $tableClass::getList($params)->fetchAll();
    }

    private static function indexById(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row['ID']] = $row;
        }
        return $result;
    }
}
