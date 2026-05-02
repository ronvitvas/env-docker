<?php

declare(strict_types=1);

namespace Uds\Ideabank2\Seed;

use Bitrix\Main\Application;
use Bitrix\Main\DB\SqlHelper;

final class DemoDataSeeder
{
    public static function run(): array
    {
        $connection = Application::getConnection();
        $sql = $connection->getSqlHelper();

        $statusIds = self::getDictionaryIds('b_uds_ideabank_idea_status', 'CODE');
        $categoryIds = self::getDictionaryIds('b_uds_ideabank_idea_category', 'CODE');

        $created = [
            'users' => 0,
            'ideas' => 0,
            'updated_idea_owners' => 0,
            'removed_duplicate_ideas' => 0,
            'authors' => 0,
            'news' => 0,
            'contests' => 0,
            'contest_parts' => 0,
            'challenges' => 0,
            'challenge_parts' => 0,
            'comments' => 0,
            'reactions' => 0,
            'coins' => 0,
            'expert_reviews' => 0,
            'committee_decisions' => 0,
            'workflow_entries' => 0,
            'feedback_entries' => 0,
        ];

        $created['users'] = self::ensureDemoUsers();
        $userIds = self::getUserIds();

        $ideas = self::ideaRows($userIds, $statusIds, $categoryIds);
        $created['removed_duplicate_ideas'] = self::cleanupDuplicateDemoIdeas($ideas);
        foreach ($ideas as $idea) {
            $ideaId = self::findId('b_uds_ideabank_idea', 'CODE', $idea['CODE']);
            if ($ideaId <= 0) {
                $ideaId = self::findId('b_uds_ideabank_idea', 'TITLE', $idea['TITLE']);
            }
            if ($ideaId > 0) {
                self::syncIdeaOwner((int)$ideaId, (int)$idea['OWNER_USER_ID'], $sql);
                $created['updated_idea_owners']++;
                continue;
            }

            $ideaId = self::insert('b_uds_ideabank_idea', $idea, $sql);
            $created['ideas']++;
            $hasCoAuthor = (int)$ideaId % 3 === 0;
            self::insert('b_uds_ideabank_idea_author', ['IDEA_ID' => $ideaId, 'USER_ID' => $idea['OWNER_USER_ID'], 'SHARE_PERCENT' => $hasCoAuthor ? 75 : 100], $sql);
            $created['authors']++;

            if ($hasCoAuthor) {
                self::insert('b_uds_ideabank_idea_author', [
                    'IDEA_ID' => $ideaId,
                    'USER_ID' => $userIds[((int)$ideaId + 2) % count($userIds)],
                    'SHARE_PERCENT' => 25,
                ], $sql);
                $created['authors']++;
            }

            self::seedIdeaActivity($ideaId, $idea, $userIds, $sql, $created);
        }

        foreach (self::newsRows($userIds) as $row) {
            if (!self::exists('b_uds_ideabank_idea_news', 'TITLE', $row['TITLE'])) {
                self::insert('b_uds_ideabank_idea_news', $row, $sql);
                $created['news']++;
            }
        }

        foreach (self::contestRows($userIds) as $row) {
            $contestId = self::findId('b_uds_ideabank_idea_contest', 'TITLE', $row['TITLE']);
            if ($contestId <= 0) {
                $contestId = self::insert('b_uds_ideabank_idea_contest', $row, $sql);
                $created['contests']++;
            }

            self::clearContestParts($contestId);
            $created['contest_parts'] += self::seedContestParts($contestId, $userIds, $sql);
        }

        foreach (self::challengeRows() as $row) {
            $challengeId = self::findId('b_uds_ideabank_idea_challenge', 'TITLE', $row['TITLE']);
            if ($challengeId <= 0) {
                $challengeId = self::insert('b_uds_ideabank_idea_challenge', $row, $sql);
                $created['challenges']++;
            }

            self::clearChallengeParts($challengeId);
            $created['challenge_parts'] += self::seedChallengeParts($challengeId, $row, $userIds, $sql);
        }

        $created['coins'] += self::seedDemoLeaderboardCoins($userIds, $sql);

        return $created;
    }

    private static function getUserIds(): array
    {
        $connection = Application::getConnection();
        $sql = $connection->getSqlHelper();
        $logins = array_map(static fn(array $user): string => $user['login'], self::demoUserRows());
        $loginList = "'" . implode("','", array_map(static fn(string $login): string => $sql->forSql($login), $logins)) . "'";
        $rows = $connection->query('SELECT ID FROM b_user WHERE LOGIN IN (' . $loginList . ') ORDER BY LOGIN ASC')->fetchAll();
        $ids = array_map(static fn(array $row): int => (int)$row['ID'], $rows);

        if (count($ids) < 10) {
            $rows = $connection->query('SELECT ID FROM b_user WHERE ACTIVE = \'Y\' ORDER BY ID ASC LIMIT 10')->fetchAll();
            $ids = array_map(static fn(array $row): int => (int)$row['ID'], $rows);
            if ($ids === []) {
                $ids = [1];
            }
        }

        while (count($ids) < 10) {
            $ids[] = $ids[0];
        }
        return $ids;
    }

    private static function ensureDemoUsers(): int
    {
        $created = 0;
        $participantGroupId = (int)\COption::GetOptionString('uds.ideabank2', 'group_participants_id', '0');
        $groupIds = $participantGroupId > 0 ? [$participantGroupId] : [];

        foreach (self::demoUserRows() as $index => $row) {
            $userId = self::findUserIdByLogin($row['login']);
            $fields = [
                'LOGIN' => $row['login'],
                'EMAIL' => $row['email'],
                'NAME' => $row['name'],
                'LAST_NAME' => $row['last_name'],
                'SECOND_NAME' => $row['second_name'],
                'WORK_POSITION' => $row['position'],
                'PERSONAL_GENDER' => $row['gender'],
                'ACTIVE' => 'Y',
            ];

            if ($groupIds !== []) {
                $fields['GROUP_ID'] = $groupIds;
            }

            $user = new \CUser();
            if ($userId > 0) {
                if (self::userNeedsDemoUpdate($userId)) {
                    $user->Update($userId, $fields);
                }
                self::ensureDemoUserPhoto($userId, $index + 1);
                continue;
            }

            $password = 'IdeaBankDemo' . str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT) . '!';
            $fields['PASSWORD'] = $password;
            $fields['CONFIRM_PASSWORD'] = $password;
            $fields['LID'] = defined('SITE_ID') ? SITE_ID : 's1';
            $fields['XML_ID'] = 'UDS_IDEABANK2_DEMO_USER_' . str_pad((string)($index + 1), 2, '0', STR_PAD_LEFT);

            $newUserId = (int)$user->Add($fields);
            if ($newUserId > 0) {
                self::ensureDemoUserPhoto($newUserId, $index + 1);
                $created++;
            }
        }

        return $created;
    }

    private static function demoUserRows(): array
    {
        return [
            ['login' => 'ideabank.demo01', 'email' => 'ideabank.demo01@example.local', 'name' => 'Анна', 'last_name' => 'Морозова', 'second_name' => 'Игоревна', 'position' => 'Мастер участка упаковки', 'gender' => 'F'],
            ['login' => 'ideabank.demo02', 'email' => 'ideabank.demo02@example.local', 'name' => 'Дмитрий', 'last_name' => 'Соколов', 'second_name' => 'Павлович', 'position' => 'Инженер по эффективности', 'gender' => 'M'],
            ['login' => 'ideabank.demo03', 'email' => 'ideabank.demo03@example.local', 'name' => 'Елена', 'last_name' => 'Кузнецова', 'second_name' => 'Андреевна', 'position' => 'Специалист по качеству', 'gender' => 'F'],
            ['login' => 'ideabank.demo04', 'email' => 'ideabank.demo04@example.local', 'name' => 'Павел', 'last_name' => 'Никитин', 'second_name' => 'Олегович', 'position' => 'Руководитель ремонтной смены', 'gender' => 'M'],
            ['login' => 'ideabank.demo05', 'email' => 'ideabank.demo05@example.local', 'name' => 'Мария', 'last_name' => 'Волкова', 'second_name' => 'Сергеевна', 'position' => 'Координатор улучшений', 'gender' => 'F'],
            ['login' => 'ideabank.demo06', 'email' => 'ideabank.demo06@example.local', 'name' => 'Сергей', 'last_name' => 'Орлов', 'second_name' => 'Викторович', 'position' => 'Мастер производственной линии', 'gender' => 'M'],
            ['login' => 'ideabank.demo07', 'email' => 'ideabank.demo07@example.local', 'name' => 'Ольга', 'last_name' => 'Петрова', 'second_name' => 'Денисовна', 'position' => 'Бизнес-аналитик процессов', 'gender' => 'F'],
            ['login' => 'ideabank.demo08', 'email' => 'ideabank.demo08@example.local', 'name' => 'Илья', 'last_name' => 'Федоров', 'second_name' => 'Максимович', 'position' => 'Руководитель сервиса', 'gender' => 'M'],
            ['login' => 'ideabank.demo09', 'email' => 'ideabank.demo09@example.local', 'name' => 'Наталья', 'last_name' => 'Смирнова', 'second_name' => 'Владимировна', 'position' => 'Методолог банка идей', 'gender' => 'F'],
            ['login' => 'ideabank.demo10', 'email' => 'ideabank.demo10@example.local', 'name' => 'Алексей', 'last_name' => 'Громов', 'second_name' => 'Романович', 'position' => 'Руководитель направления БН', 'gender' => 'M'],
        ];
    }

    private static function findUserIdByLogin(string $login): int
    {
        $connection = Application::getConnection();
        $sql = $connection->getSqlHelper();
        $row = $connection->query("SELECT ID FROM b_user WHERE LOGIN='" . $sql->forSql($login) . "' LIMIT 1")->fetch();

        return is_array($row) ? (int)$row['ID'] : 0;
    }

    private static function userNeedsDemoUpdate(int $userId): bool
    {
        $row = Application::getConnection()->query('SELECT PERSONAL_PHOTO FROM b_user WHERE ID=' . $userId . ' LIMIT 1')->fetch();

        return !is_array($row) || (int)($row['PERSONAL_PHOTO'] ?? 0) <= 0;
    }

    private static function ensureDemoUserPhoto(int $userId, int $number): void
    {
        if ($userId <= 0 || !class_exists(\CFile::class)) {
            return;
        }

        $row = Application::getConnection()->query('SELECT PERSONAL_PHOTO FROM b_user WHERE ID=' . $userId . ' LIMIT 1')->fetch();
        if (is_array($row) && (int)($row['PERSONAL_PHOTO'] ?? 0) > 0) {
            return;
        }

        $file = self::makeDemoUserPhoto($number);
        if ($file === []) {
            return;
        }

        $file['MODULE_ID'] = 'main';
        $fileId = (int)\CFile::SaveFile($file, 'main');
        if ($fileId > 0) {
            Application::getConnection()->queryExecute(
                'UPDATE b_user SET PERSONAL_PHOTO=' . $fileId . ' WHERE ID=' . $userId
            );
        }
    }

    private static function makeDemoUserPhoto(int $number): array
    {
        if (!class_exists(\CFile::class)) {
            return [];
        }

        $candidates = [
            $_SERVER['DOCUMENT_ROOT'] . '/local/_/tmp/ideas/assets/images/avatars/avatar-' . $number . '.png',
            $_SERVER['DOCUMENT_ROOT'] . '/local/_/tmp/ideas/assets/images/avatars/rating-' . $number . '.png',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                $file = \CFile::MakeFileArray($path);
                return is_array($file) ? $file : [];
            }
        }

        return [];
    }

    private static function getDictionaryIds(string $table, string $codeField): array
    {
        $result = [];
        foreach (Application::getConnection()->query('SELECT ID, ' . $codeField . ' AS CODE FROM ' . $table)->fetchAll() as $row) {
            $result[(string)$row['CODE']] = (int)$row['ID'];
        }
        return $result;
    }

    private static function ideaRows(array $users, array $statuses, array $categories): array
    {
        $now = date('Y-m-d H:i:s');
        $rows = [
            ['CODE' => 'DEMO_IDEA_SAFETY_ROUTE', 'TITLE' => 'Единый маршрут обхода по безопасности', 'TYPE' => 'Идея улучшения', 'CATEGORY_ID' => $categories['safety'] ?? 1, 'STATUS_ID' => $statuses['implemented'] ?? 1, 'STAGE' => 'implemented', 'DESCRIPTION' => 'Единый маршрут обхода и фиксации замечаний для упаковочного участка.', 'PROBLEM' => 'Обходы выполнялись по разным маршрутам.', 'LOSSES' => 'Лишнее время и задержки закрытия замечаний.', 'PROPOSAL' => 'Утвердить единый маршрут и цифровой шаблон фиксации.', 'ECONOMIC_EFFECT' => 180000, 'CONFIRMED_EFFECT' => 165000, 'IMPLEMENTATION_DAYS' => 5, 'OWNER_USER_ID' => $users[0], 'BUSINESS_DIRECTION' => 'Производство', 'KEYWORDS' => 'безопасность, обход, визуальный контроль', 'ECONOMIC_EFFECT_TEXT' => 'Снижение потерь времени.', 'IMPLEMENTATION_PLAN' => 'Пилот, корректировка и тиражирование.', 'RESOURCES_NEEDED' => 'Поддержка мастеров смен.', 'RISKS' => 'Поддерживать актуальность маршрута.', 'IS_DRAFT' => 'N', 'IS_ANONYMOUS' => 'N', 'CREATED_AT' => '2024-08-01 08:00:00', 'SUBMITTED_AT' => '2024-08-03 08:30:00', 'UPDATED_AT' => $now],
            ['CODE' => 'DEMO_IDEA_DIGITAL_CHANGEOVER', 'TITLE' => 'Цифровой чек-лист переналадки линии', 'TYPE' => 'Рационализаторская идея', 'CATEGORY_ID' => $categories['efficiency'] ?? 1, 'STATUS_ID' => $statuses['initial_review'] ?? 1, 'STAGE' => 'initial_review', 'DESCRIPTION' => 'Единый цифровой маршрут переналадки.', 'PROBLEM' => 'Действия подтверждаются устно или по бумаге.', 'LOSSES' => 'Потери времени на сверку параметров.', 'PROPOSAL' => 'Собрать шаги в цифровой чек-лист.', 'ECONOMIC_EFFECT' => 240000, 'CONFIRMED_EFFECT' => 0, 'IMPLEMENTATION_DAYS' => 8, 'OWNER_USER_ID' => $users[1], 'BUSINESS_DIRECTION' => 'Производство', 'KEYWORDS' => 'переналадка, цифровизация, мастер', 'ECONOMIC_EFFECT_TEXT' => 'Сокращение простоев.', 'IMPLEMENTATION_PLAN' => 'Пилот на одной линии.', 'RESOURCES_NEEDED' => '4 часа аналитика.', 'RISKS' => 'Дисциплина заполнения.', 'IS_DRAFT' => 'N', 'IS_ANONYMOUS' => 'N', 'CREATED_AT' => '2024-08-02 09:00:00', 'SUBMITTED_AT' => '2024-08-04 09:00:00', 'UPDATED_AT' => $now],
            ['CODE' => 'DEMO_IDEA_PACKAGING_STANDARD', 'TITLE' => 'Визуальный стандарт приемки упаковки', 'TYPE' => 'Идея улучшения', 'CATEGORY_ID' => $categories['quality'] ?? 1, 'STATUS_ID' => $statuses['accepted'] ?? 1, 'STAGE' => 'accepted', 'DESCRIPTION' => 'Фотоэталоны и контрольные метки для приемки.', 'PROBLEM' => 'Ошибки выявлялись после выхода партии.', 'LOSSES' => 'Возвраты и повторная упаковка.', 'PROPOSAL' => 'Ввести фотоэталоны до запуска серии.', 'ECONOMIC_EFFECT' => 210000, 'CONFIRMED_EFFECT' => 194000, 'IMPLEMENTATION_DAYS' => 4, 'OWNER_USER_ID' => $users[2], 'BUSINESS_DIRECTION' => 'Качество', 'KEYWORDS' => 'качество, упаковка, визуальный стандарт', 'ECONOMIC_EFFECT_TEXT' => 'Снижение возвратов.', 'IMPLEMENTATION_PLAN' => 'Согласовать эталоны и закрепить стандарт.', 'RESOURCES_NEEDED' => 'Печать материалов.', 'RISKS' => 'Обновлять эталоны.', 'IS_DRAFT' => 'N', 'IS_ANONYMOUS' => 'N', 'CREATED_AT' => '2024-07-25 10:00:00', 'SUBMITTED_AT' => '2024-07-27 10:00:00', 'UPDATED_AT' => $now],
            ['CODE' => 'DEMO_IDEA_TOOL_STORAGE', 'TITLE' => 'Маркировка зон хранения инструмента', 'TYPE' => 'Идея улучшения', 'CATEGORY_ID' => $categories['culture'] ?? 1, 'STATUS_ID' => $statuses['moderation'] ?? 1, 'STAGE' => 'moderation', 'DESCRIPTION' => 'Цветовая маркировка зон хранения.', 'PROBLEM' => 'Инструмент хранится без понятной логики.', 'LOSSES' => 'Потери времени на поиск.', 'PROPOSAL' => 'Ввести цветовые зоны и карточки возврата.', 'ECONOMIC_EFFECT' => 98000, 'CONFIRMED_EFFECT' => 0, 'IMPLEMENTATION_DAYS' => 4, 'OWNER_USER_ID' => $users[3], 'BUSINESS_DIRECTION' => 'Ремонт', 'KEYWORDS' => '5С, инструмент, визуальный менеджмент', 'ECONOMIC_EFFECT_TEXT' => 'Экономия времени смены.', 'IMPLEMENTATION_PLAN' => 'Подготовить схему зон.', 'RESOURCES_NEEDED' => 'Печать маркировки.', 'RISKS' => 'Контроль поддержания порядка.', 'IS_DRAFT' => 'N', 'IS_ANONYMOUS' => 'N', 'CREATED_AT' => '2024-07-15 10:00:00', 'SUBMITTED_AT' => '2024-07-16 10:00:00', 'UPDATED_AT' => $now],
            ['CODE' => 'DEMO_IDEA_DEVIATION_ANALYSIS', 'TITLE' => 'Шаблон анализа причин отклонений', 'TYPE' => 'Идея улучшения', 'CATEGORY_ID' => $categories['quality'] ?? 1, 'STATUS_ID' => $statuses['published'] ?? 1, 'STAGE' => 'published', 'DESCRIPTION' => 'Единый шаблон анализа причин повторяющихся отклонений.', 'PROBLEM' => 'Разные форматы анализа причин в цехах.', 'LOSSES' => 'Потери времени и неполные корректирующие действия.', 'PROPOSAL' => 'Применять единый цифровой шаблон 5 Why.', 'ECONOMIC_EFFECT' => 120000, 'CONFIRMED_EFFECT' => 0, 'IMPLEMENTATION_DAYS' => 7, 'OWNER_USER_ID' => $users[4], 'BUSINESS_DIRECTION' => 'Качество', 'KEYWORDS' => '5 why, отклонения, анализ причин', 'ECONOMIC_EFFECT_TEXT' => 'Снижение повторных дефектов.', 'IMPLEMENTATION_PLAN' => 'Пилот в двух сменах.', 'RESOURCES_NEEDED' => 'Методолог и мастер качества.', 'RISKS' => 'Нужен контроль полноты заполнения.', 'IS_DRAFT' => 'N', 'IS_ANONYMOUS' => 'N', 'CREATED_AT' => '2024-07-12 11:00:00', 'SUBMITTED_AT' => '2024-07-13 11:00:00', 'UPDATED_AT' => $now],
            ['CODE' => 'DEMO_IDEA_SHIFT_BRIEFING', 'TITLE' => 'Стандартизация стартового брифинга смены', 'TYPE' => 'Идея улучшения', 'CATEGORY_ID' => $categories['safety'] ?? 1, 'STATUS_ID' => $statuses['initial_review'] ?? 1, 'STAGE' => 'initial_review', 'DESCRIPTION' => 'Единый 10-минутный брифинг перед стартом.', 'PROBLEM' => 'Разный объем вводной информации перед сменой.', 'LOSSES' => 'Ошибки в первые часы смены.', 'PROPOSAL' => 'Согласовать обязательный чек-лист брифинга.', 'ECONOMIC_EFFECT' => 76000, 'CONFIRMED_EFFECT' => 0, 'IMPLEMENTATION_DAYS' => 3, 'OWNER_USER_ID' => $users[5], 'BUSINESS_DIRECTION' => 'Производство', 'KEYWORDS' => 'брифинг, безопасность, смена', 'ECONOMIC_EFFECT_TEXT' => 'Снижение первичных ошибок.', 'IMPLEMENTATION_PLAN' => 'Согласовать шаблон и обучить мастеров.', 'RESOURCES_NEEDED' => 'Время мастера смены.', 'RISKS' => 'Риск формального проведения.', 'IS_DRAFT' => 'N', 'IS_ANONYMOUS' => 'N', 'CREATED_AT' => '2024-07-09 07:30:00', 'SUBMITTED_AT' => '2024-07-09 08:00:00', 'UPDATED_AT' => $now],
            ['CODE' => 'DEMO_IDEA_BEST_PRACTICE_CARD', 'TITLE' => 'Сквозная карточка переноса лучших практик', 'TYPE' => 'Рационализаторская идея', 'CATEGORY_ID' => $categories['culture'] ?? 1, 'STATUS_ID' => $statuses['accepted'] ?? 1, 'STAGE' => 'accepted', 'DESCRIPTION' => 'Карточка передачи лучших практик между сменами.', 'PROBLEM' => 'Хорошие решения не всегда тиражируются.', 'LOSSES' => 'Повторное решение одних и тех же проблем.', 'PROPOSAL' => 'Ввести карточку с владельцем и сроком внедрения.', 'ECONOMIC_EFFECT' => 132000, 'CONFIRMED_EFFECT' => 64000, 'IMPLEMENTATION_DAYS' => 6, 'OWNER_USER_ID' => $users[6], 'BUSINESS_DIRECTION' => 'Управление', 'KEYWORDS' => 'тиражирование, лучшие практики, передача опыта', 'ECONOMIC_EFFECT_TEXT' => 'Сокращение времени на запуск практик.', 'IMPLEMENTATION_PLAN' => 'Запуск на 3 участках.', 'RESOURCES_NEEDED' => 'Координатор улучшений.', 'RISKS' => 'Слабый контроль сроков.', 'IS_DRAFT' => 'N', 'IS_ANONYMOUS' => 'N', 'CREATED_AT' => '2024-07-07 12:00:00', 'SUBMITTED_AT' => '2024-07-07 13:00:00', 'UPDATED_AT' => $now],
            ['CODE' => 'DEMO_IDEA_MATERIAL_SHORTAGE_ALERT', 'TITLE' => 'Автосигнал о риске дефицита материалов', 'TYPE' => 'Идея улучшения', 'CATEGORY_ID' => $categories['efficiency'] ?? 1, 'STATUS_ID' => $statuses['moderation'] ?? 1, 'STAGE' => 'moderation', 'DESCRIPTION' => 'Сигнал при приближении к критическому остатку.', 'PROBLEM' => 'Дефицит материалов выявляется с опозданием.', 'LOSSES' => 'Простои и срочные закупки.', 'PROPOSAL' => 'Ввести пороговые уведомления по складу.', 'ECONOMIC_EFFECT' => 205000, 'CONFIRMED_EFFECT' => 0, 'IMPLEMENTATION_DAYS' => 9, 'OWNER_USER_ID' => $users[7], 'BUSINESS_DIRECTION' => 'Сервис', 'KEYWORDS' => 'материалы, остатки, уведомления', 'ECONOMIC_EFFECT_TEXT' => 'Снижение простоев.', 'IMPLEMENTATION_PLAN' => 'Пилот для 5 критичных позиций.', 'RESOURCES_NEEDED' => 'Настройка правил в системе.', 'RISKS' => 'Ложные срабатывания.', 'IS_DRAFT' => 'N', 'IS_ANONYMOUS' => 'N', 'CREATED_AT' => '2024-07-04 09:20:00', 'SUBMITTED_AT' => '2024-07-04 10:00:00', 'UPDATED_AT' => $now],
            ['CODE' => 'DEMO_IDEA_SIMILAR_CASES', 'TITLE' => 'Быстрая верификация идеи на похожие кейсы', 'TYPE' => 'Идея улучшения', 'CATEGORY_ID' => $categories['efficiency'] ?? 1, 'STATUS_ID' => $statuses['published'] ?? 1, 'STAGE' => 'published', 'DESCRIPTION' => 'Подбор похожих кейсов перед запуском идеи.', 'PROBLEM' => 'Часто дублируются одинаковые инициативы.', 'LOSSES' => 'Потери времени на повторный анализ.', 'PROPOSAL' => 'Показывать похожие кейсы при подаче идеи.', 'ECONOMIC_EFFECT' => 87000, 'CONFIRMED_EFFECT' => 28000, 'IMPLEMENTATION_DAYS' => 4, 'OWNER_USER_ID' => $users[8], 'BUSINESS_DIRECTION' => 'Развитие', 'KEYWORDS' => 'похожие идеи, база знаний, экономия времени', 'ECONOMIC_EFFECT_TEXT' => 'Экономия времени экспертов.', 'IMPLEMENTATION_PLAN' => 'Реализовать подсказки в форме.', 'RESOURCES_NEEDED' => 'Поддержка аналитика.', 'RISKS' => 'Необходима актуальная база кейсов.', 'IS_DRAFT' => 'N', 'IS_ANONYMOUS' => 'N', 'CREATED_AT' => '2024-07-02 15:10:00', 'SUBMITTED_AT' => '2024-07-02 15:40:00', 'UPDATED_AT' => $now],
            ['CODE' => 'DEMO_IDEA_DRAFT_APPROVAL_ROUTE', 'TITLE' => 'Черновик: оптимизация маршрута согласования', 'TYPE' => 'Идея улучшения', 'CATEGORY_ID' => $categories['culture'] ?? 1, 'STATUS_ID' => $statuses['draft'] ?? ($statuses['moderation'] ?? 1), 'STAGE' => 'draft', 'DESCRIPTION' => 'Черновая идея оптимизации маршрута согласования.', 'PROBLEM' => 'Длинный путь согласования для небольших улучшений.', 'LOSSES' => 'Задержки внедрения.', 'PROPOSAL' => 'Сократить маршрут до двух обязательных ролей.', 'ECONOMIC_EFFECT' => 54000, 'CONFIRMED_EFFECT' => 0, 'IMPLEMENTATION_DAYS' => 3, 'OWNER_USER_ID' => $users[9], 'BUSINESS_DIRECTION' => 'Управление', 'KEYWORDS' => 'согласование, скорость, черновик', 'ECONOMIC_EFFECT_TEXT' => 'Ускорение принятия решений.', 'IMPLEMENTATION_PLAN' => 'Подготовить схему и роли.', 'RESOURCES_NEEDED' => 'Согласование с администраторами.', 'RISKS' => 'Потеря качества проверки.', 'IS_DRAFT' => 'Y', 'IS_ANONYMOUS' => 'N', 'CREATED_AT' => '2024-07-01 09:00:00', 'SUBMITTED_AT' => null, 'UPDATED_AT' => $now],
            ['CODE' => 'DEMO_IDEA_ANONYMOUS_CHANNEL', 'TITLE' => 'Анонимный канал предложений по улучшениям', 'TYPE' => 'Идея улучшения', 'CATEGORY_ID' => $categories['culture'] ?? 1, 'STATUS_ID' => $statuses['moderation'] ?? 1, 'STAGE' => 'moderation', 'DESCRIPTION' => 'Канал для анонимной подачи идей с последующей идентификацией куратора.', 'PROBLEM' => 'Часть инициатив не подаётся из-за опасения критики.', 'LOSSES' => 'Потеря потенциальных улучшений.', 'PROPOSAL' => 'Пилот анонимного канала для 1 подразделения.', 'ECONOMIC_EFFECT' => 65000, 'CONFIRMED_EFFECT' => 0, 'IMPLEMENTATION_DAYS' => 5, 'OWNER_USER_ID' => $users[2], 'BUSINESS_DIRECTION' => 'Развитие', 'KEYWORDS' => 'анонимность, вовлеченность, идеи', 'ECONOMIC_EFFECT_TEXT' => 'Рост вовлеченности сотрудников.', 'IMPLEMENTATION_PLAN' => 'Пилот и оценка качества потока идей.', 'RESOURCES_NEEDED' => 'Куратор и модератор канала.', 'RISKS' => 'Риск нерелевантных предложений.', 'IS_DRAFT' => 'N', 'IS_ANONYMOUS' => 'Y', 'CREATED_AT' => '2024-06-28 11:40:00', 'SUBMITTED_AT' => '2024-06-29 08:00:00', 'UPDATED_AT' => $now],
            ['CODE' => 'DEMO_IDEA_SLA_INDICATOR', 'TITLE' => 'Визуальный индикатор SLA по маршруту идеи', 'TYPE' => 'Рационализаторская идея', 'CATEGORY_ID' => $categories['efficiency'] ?? 1, 'STATUS_ID' => $statuses['implemented'] ?? 1, 'STAGE' => 'implemented', 'DESCRIPTION' => 'Индикатор SLA на каждом этапе маршрута.', 'PROBLEM' => 'Непрозрачные сроки рассмотрения идей.', 'LOSSES' => 'Снижение доверия к процессу.', 'PROPOSAL' => 'Показывать SLA и просрочку на карточке.', 'ECONOMIC_EFFECT' => 99000, 'CONFIRMED_EFFECT' => 83000, 'IMPLEMENTATION_DAYS' => 4, 'OWNER_USER_ID' => $users[1], 'BUSINESS_DIRECTION' => 'Управление', 'KEYWORDS' => 'sla, маршрут, прозрачность', 'ECONOMIC_EFFECT_TEXT' => 'Сокращение цикла рассмотрения.', 'IMPLEMENTATION_PLAN' => 'Настройка статусов и индикаторов.', 'RESOURCES_NEEDED' => 'Время аналитика.', 'RISKS' => 'Необходимость дисциплины обновления статусов.', 'IS_DRAFT' => 'N', 'IS_ANONYMOUS' => 'N', 'CREATED_AT' => '2024-06-25 08:20:00', 'SUBMITTED_AT' => '2024-06-25 09:00:00', 'UPDATED_AT' => $now],
        ];

        return array_merge($rows, self::historyIdeaRows($users, $statuses, $categories));
    }

    private static function historyIdeaRows(array $users, array $statuses, array $categories): array
    {
        $templates = [
            ['code' => 'ERGONOMIC_WORKPLACE', 'title' => 'Эргономичная настройка рабочего места', 'category' => 'safety', 'direction' => 'Производство', 'keywords' => 'эргономика, безопасность, рабочее место'],
            ['code' => 'AUTO_REPORT_SHIFT', 'title' => 'Автоотчет по итогам смены', 'category' => 'efficiency', 'direction' => 'Производство', 'keywords' => 'отчет, смена, автоматизация'],
            ['code' => 'SUPPLIER_DEFECT_SIGNAL', 'title' => 'Сигнал по повторным дефектам поставщика', 'category' => 'quality', 'direction' => 'Качество', 'keywords' => 'поставщик, дефекты, качество'],
            ['code' => 'SPARE_PART_KANBAN', 'title' => 'Канбан для критичных запасных частей', 'category' => 'cost', 'direction' => 'Склад', 'keywords' => 'канбан, запчасти, остатки'],
            ['code' => 'TRAINING_MICROLESSONS', 'title' => 'Микроуроки для новых сотрудников', 'category' => 'culture', 'direction' => 'HR', 'keywords' => 'обучение, адаптация, культура'],
            ['code' => 'CUSTOMER_CLAIM_ROUTING', 'title' => 'Единый маршрут разбора рекламаций', 'category' => 'quality', 'direction' => 'Сервис', 'keywords' => 'рекламации, маршрут, ответственность'],
            ['code' => 'ENERGY_IDLE_MODE', 'title' => 'Режим ожидания для энергоемкого оборудования', 'category' => 'cost', 'direction' => 'Энергетика', 'keywords' => 'энергия, простой, экономия'],
            ['code' => 'VISUAL_PLAN_FACT', 'title' => 'План-факт на доске участка', 'category' => 'organization', 'direction' => 'Производство', 'keywords' => 'план-факт, участок, визуализация'],
        ];

        $stages = [
            'moderation',
            'published',
            'initial_review',
            'kpu',
            'transferred',
            'accepted',
            'implementation',
            'implemented',
            'deployed',
            'revise',
            'backlog',
            'rejected',
            'duplicate',
        ];

        $rows = [];
        $index = 0;
        for ($year = 2022; $year <= 2026; $year++) {
            for ($quarter = 1; $quarter <= 4; $quarter++) {
                $template = $templates[$index % count($templates)];
                $stage = $stages[$index % count($stages)];
                $month = (($quarter - 1) * 3) + 1;
                $day = 5 + ($index % 18);
                $submittedAt = sprintf('%04d-%02d-%02d 09:%02d:00', $year, $month, $day, ($index * 7) % 60);
                $isDraft = $index % 17 === 0;
                $actualStage = $isDraft ? 'draft' : $stage;
                $statusCode = $isDraft ? 'moderation' : $stage;
                $effect = 45000 + ($index * 17500);
                $confirmed = in_array($stage, ['accepted', 'implementation', 'implemented', 'deployed'], true) ? (int)round($effect * 0.72) : 0;

                $rows[] = [
                    'CODE' => 'DEMO_' . $year . '_Q' . $quarter . '_' . $template['code'] . '_' . ($index + 1),
                    'TITLE' => $template['title'] . ' ' . $year . ' Q' . $quarter,
                    'TYPE' => $index % 4 === 0 ? 'Рационализаторская идея' : 'Идея улучшения',
                    'CATEGORY_ID' => $categories[$template['category']] ?? 1,
                    'STATUS_ID' => $statuses[$statusCode] ?? 1,
                    'STAGE' => $actualStage,
                    'DESCRIPTION' => 'Многолетний демо-кейс для проверки витрин, фильтров, статистики и маршрутов банка идей.',
                    'PROBLEM' => 'Процесс выполнялся нестабильно и зависел от ручного контроля.',
                    'LOSSES' => 'Потери времени, повторные проверки и непрозрачная ответственность.',
                    'PROPOSAL' => 'Закрепить стандарт, владельца процесса и измеримый показатель результата.',
                    'ECONOMIC_EFFECT' => $effect,
                    'CONFIRMED_EFFECT' => $confirmed,
                    'IMPLEMENTATION_DAYS' => 3 + ($index % 12),
                    'OWNER_USER_ID' => $users[$index % count($users)],
                    'BUSINESS_DIRECTION' => $template['direction'],
                    'KEYWORDS' => $template['keywords'],
                    'ECONOMIC_EFFECT_TEXT' => 'Расчет включает сокращение времени операций и снижение повторных работ.',
                    'IMPLEMENTATION_PLAN' => 'Диагностика, пилот, замер результата, решение о тиражировании.',
                    'RESOURCES_NEEDED' => 'Куратор процесса, мастер участка и короткая настройка регламента.',
                    'RISKS' => 'Нужен контроль регулярности применения стандарта.',
                    'IS_DRAFT' => $isDraft ? 'Y' : 'N',
                    'IS_ANONYMOUS' => $index % 11 === 0 ? 'Y' : 'N',
                    'CREATED_AT' => sprintf('%04d-%02d-%02d 08:20:00', $year, $month, max(1, $day - 1)),
                    'SUBMITTED_AT' => $isDraft ? null : $submittedAt,
                    'UPDATED_AT' => sprintf('%04d-%02d-%02d 17:30:00', min(2026, $year), min(12, $month + 1), min(28, $day + 2)),
                ];
                $index++;
            }
        }

        return $rows;
    }

    private static function newsRows(array $users): array
    {
        return [
            ['CATEGORY' => 'Безопасность', 'DATE' => '2024-08-12', 'TITLE' => 'Единый маршрут обхода снизил число замечаний по безопасности', 'EXCERPT' => 'Команда участка упаковки перестроила ежедневный обход и усилила визуальные сигналы.', 'BODY' => 'Инициатива начиналась как простая идея мастеров смены. После пилота команда зафиксировала снижение повторяющихся замечаний.', 'IMAGE' => '/local/_/tmp/ideas/assets/images/news/helmet-main.png', 'HERO_IMAGE' => '/local/_/tmp/ideas/assets/images/news/helmet-main.png', 'AUTHOR_USER_ID' => $users[0], 'QUOTE' => 'Когда идея быстро доходит до пилота, сотрудники начинают верить в изменения.'],
            ['CATEGORY' => 'Качество', 'DATE' => '2024-08-05', 'TITLE' => 'Визуальный стандарт приемки упаковки сократил возвраты', 'EXCERPT' => 'На линии упаковки запустили эталоны визуальной приемки.', 'BODY' => 'Команда качества оформила лучший опыт сотрудников в понятный визуальный стандарт.', 'IMAGE' => '/local/_/tmp/ideas/assets/images/news/metal-thumb.png', 'HERO_IMAGE' => '/local/_/tmp/ideas/assets/images/news/metal-thumb.png', 'AUTHOR_USER_ID' => $users[2], 'QUOTE' => 'Лучшие идеи появляются там, где есть быстрый способ обсудить предложение.'],
            ['CATEGORY' => 'Цифровизация', 'DATE' => '2024-07-29', 'TITLE' => 'Цифровой чек-лист переналадки ускорил подготовку линии', 'EXCERPT' => 'Мастера перевели бумажные действия в один цифровой маршрут.', 'BODY' => 'Решение сократило потери на повторных проверках и дало прозрачную историю выполнения шагов.', 'IMAGE' => '/local/_/tmp/ideas/assets/images/news/notebook-thumb.png', 'HERO_IMAGE' => '/local/_/tmp/ideas/assets/images/news/notebook-thumb.png', 'AUTHOR_USER_ID' => $users[1], 'QUOTE' => 'Цифровой маршрут помогает мастеру быстрее запускать линию.'],
            ['CATEGORY' => 'Управление', 'DATE' => '2024-07-20', 'TITLE' => 'На карточках идей появилась визуализация SLA этапов', 'EXCERPT' => 'Команды быстрее замечают просрочки и управляют сроками.', 'BODY' => 'После внедрения индикатора SLA средняя длительность этапов сократилась.', 'IMAGE' => '/local/_/tmp/ideas/assets/images/news/contest-main.png', 'HERO_IMAGE' => '/local/_/tmp/ideas/assets/images/news/contest-main.png', 'AUTHOR_USER_ID' => $users[3], 'QUOTE' => 'Прозрачные сроки повышают доверие к процессу.'],
            ['CATEGORY' => 'Развитие', 'DATE' => '2024-07-14', 'TITLE' => 'Стартовал пилот анонимного канала предложений', 'EXCERPT' => 'Сотрудники получили новый формат безопасной подачи инициатив.', 'BODY' => 'Пилот запущен на одном производственном контуре с кураторской модерацией.', 'IMAGE' => '/local/_/tmp/ideas/assets/images/news/notebook-thumb.png', 'HERO_IMAGE' => '/local/_/tmp/ideas/assets/images/news/notebook-thumb.png', 'AUTHOR_USER_ID' => $users[4], 'QUOTE' => 'Важно, чтобы идею можно было подать в комфортном формате.'],
            ['CATEGORY' => 'Эффективность', 'DATE' => '2024-07-08', 'TITLE' => 'Челлендж по экономии материалов собрал 40+ инициатив', 'EXCERPT' => 'Идеи сотрудников легли в основу новых стандартов работы.', 'BODY' => 'Эксперты отмечают высокий уровень проработки предложений и эффектов.', 'IMAGE' => '/local/_/tmp/ideas/assets/images/news/metal-thumb.png', 'HERO_IMAGE' => '/local/_/tmp/ideas/assets/images/news/metal-thumb.png', 'AUTHOR_USER_ID' => $users[5], 'QUOTE' => 'Сильные идеи рождаются в совместной работе и обратной связи.'],
        ];
    }

    private static function contestRows(array $users): array
    {
        return [
            ['TITLE' => 'Лидер безопасности 2024', 'DESCRIPTION' => 'Конкурс для идей, которые снижают риски и упрощают безопасное поведение.', 'DATE_LABEL' => 'Заявки до: 21.09', 'DEADLINE' => '2024-09-21', 'ORGANIZER_USER_ID' => $users[0], 'IMAGE' => '/local/_/tmp/ideas/assets/images/news/helmet-main.png', 'REQUIREMENTS' => "Решить конкретный риск.\nПоказать текущее и целевое состояние.\nОписать быстрый пилот."],
            ['TITLE' => 'Экономия без потери качества', 'DESCRIPTION' => 'Инициативы, которые сокращают потери материалов, времени и ручных операций.', 'DATE_LABEL' => 'Заявки до: 05.10', 'DEADLINE' => '2024-10-05', 'ORGANIZER_USER_ID' => $users[1], 'IMAGE' => '/local/_/tmp/ideas/assets/images/news/contest-main.png', 'REQUIREMENTS' => "Понятный расчет эффекта.\nСохранение качества и безопасности.\nПлан тиражирования."],
            ['TITLE' => 'Цифровой контур улучшений', 'DESCRIPTION' => 'Конкурс идей по цифровизации производственных рутин и управлению данными.', 'DATE_LABEL' => 'Заявки до: 18.10', 'DEADLINE' => '2024-10-18', 'ORGANIZER_USER_ID' => $users[2], 'IMAGE' => '/local/_/tmp/ideas/assets/images/news/notebook-thumb.png', 'REQUIREMENTS' => "Измеримый показатель до/после.\nМинимальный пилот до 2 недель.\nОписание рисков внедрения."],
            ['TITLE' => 'Лучшие практики тиражирования', 'DESCRIPTION' => 'Инициативы, которые помогают быстрее переносить улучшения между подразделениями.', 'DATE_LABEL' => 'Заявки до: 02.11', 'DEADLINE' => '2024-11-02', 'ORGANIZER_USER_ID' => $users[3], 'IMAGE' => '/local/_/tmp/ideas/assets/images/news/metal-thumb.png', 'REQUIREMENTS' => "Показать шаги тиражирования.\nНазначить владельца практики.\nОписать контроль результата."],
        ];
    }

    private static function challengeRows(): array
    {
        return [
            ['TITLE' => 'Безопасный старт смены', 'PERIOD' => 'Апрель — май', 'TARGET' => 'Идеи, которые снижают риск инцидентов и делают обходы понятнее.', 'REWARD_BONUS' => 20, 'BUSINESS_DIRECTION' => 'Производство', 'TIPS' => 'безопасность, визуальный контроль, быстрый пилот'],
            ['TITLE' => 'Сервис без потерь времени', 'PERIOD' => 'Май', 'TARGET' => 'Идеи по сокращению лишних перемещений и ожиданий.', 'REWARD_BONUS' => 15, 'BUSINESS_DIRECTION' => 'Сервис', 'TIPS' => 'скорость, цифровизация, удобство'],
            ['TITLE' => 'Качество с первого раза', 'PERIOD' => 'Май — июнь', 'TARGET' => 'Инициативы, которые помогают предупреждать дефекты раньше.', 'REWARD_BONUS' => 25, 'BUSINESS_DIRECTION' => 'Качество', 'TIPS' => 'предупреждение дефектов, стандарты'],
        ];
    }

    private static function seedIdeaActivity(int $ideaId, array $idea, array $userIds, SqlHelper $sql, array &$created): void
    {
        $stage = (string)($idea['STAGE'] ?? 'moderation');
        $ownerId = (int)$idea['OWNER_USER_ID'];
        $baseTime = strtotime((string)($idea['SUBMITTED_AT'] ?: $idea['CREATED_AT'] ?: '2024-01-01 09:00:00')) ?: time();

        foreach (self::workflowForStage($stage) as $offset => $workflowStage) {
            self::insert('b_uds_ideabank_idea_workflow', [
                'IDEA_ID' => $ideaId,
                'USER_ID' => $userIds[($ideaId + $offset) % count($userIds)],
                'STATUS' => $workflowStage,
                'STAGE' => $workflowStage,
                'COMMENT' => self::workflowComment($workflowStage),
                'CREATED_AT' => date('Y-m-d H:i:s', $baseTime + ($offset * 86400)),
            ], $sql);
            $created['workflow_entries']++;
        }

        self::insert('b_uds_ideabank_idea_feedback', [
            'IDEA_ID' => $ideaId,
            'USER_ID' => $userIds[($ideaId + 1) % count($userIds)],
            'STAGE' => $stage,
            'TONE' => in_array($stage, ['accepted', 'implementation', 'implemented', 'deployed'], true) ? 'success' : ($stage === 'rejected' ? 'warning' : 'info'),
            'MESSAGE' => self::feedbackMessage($stage),
            'CREATED_AT' => date('Y-m-d H:i:s', $baseTime + 43200),
        ], $sql);
        $created['feedback_entries']++;

        $commentCount = 1 + ($ideaId % 3);
        for ($i = 0; $i < $commentCount; $i++) {
            self::insert('b_uds_ideabank_idea_comment', [
                'IDEA_ID' => $ideaId,
                'USER_ID' => $userIds[($ideaId + $i + 2) % count($userIds)],
                'TEXT' => self::commentText($i, $stage),
                'CREATED_AT' => date('Y-m-d H:i:s', $baseTime + (($i + 1) * 7200)),
            ], $sql);
            $created['comments']++;
        }

        $reactionTypes = ['like', 'support', 'useful'];
        $reactionCount = 1 + ($ideaId % 4);
        for ($i = 0; $i < $reactionCount; $i++) {
            self::insert('b_uds_ideabank_idea_reaction', [
                'IDEA_ID' => $ideaId,
                'USER_ID' => $userIds[($ideaId + $i + 4) % count($userIds)],
                'TYPE' => $reactionTypes[$i % count($reactionTypes)],
                'CREATED_AT' => date('Y-m-d H:i:s', $baseTime + (($i + 1) * 5400)),
            ], $sql);
            $created['reactions']++;
        }

        self::insert('b_uds_ideabank_idea_coin', [
            'USER_ID' => $ownerId,
            'IDEA_ID' => $ideaId,
            'EVENT' => 'submitted',
            'COINS' => 30,
            'DESCRIPTION' => 'Демо-начисление за подачу идеи',
            'CREATED_AT' => date('Y-m-d H:i:s', $baseTime + 1800),
        ], $sql);
        $created['coins']++;

        if (in_array($stage, ['accepted', 'implementation', 'implemented', 'deployed'], true)) {
            self::insert('b_uds_ideabank_idea_coin', [
                'USER_ID' => $ownerId,
                'IDEA_ID' => $ideaId,
                'EVENT' => 'accepted',
                'COINS' => 45,
                'DESCRIPTION' => 'Демо-начисление за принятие идеи в работу',
                'CREATED_AT' => date('Y-m-d H:i:s', $baseTime + 172800),
            ], $sql);
            $created['coins']++;

            self::insert('b_uds_ideabank_idea_expert_review', [
                'IDEA_ID' => $ideaId,
                'EXPERT_USER_ID' => $userIds[($ideaId + 5) % count($userIds)],
                'SCORE' => $stage === 'implemented' || $stage === 'deployed' ? 5 : 4,
                'COMMENT' => 'Демо-экспертиза: эффект понятен, риски описаны, пилот возможен.',
                'CREATED_AT' => date('Y-m-d H:i:s', $baseTime + 129600),
            ], $sql);
            $created['expert_reviews']++;

            self::insert('b_uds_ideabank_idea_committee', [
                'IDEA_ID' => $ideaId,
                'USER_ID' => $userIds[($ideaId + 6) % count($userIds)],
                'DECISION' => in_array($stage, ['implemented', 'deployed'], true) ? 'implement' : 'pilot',
                'SUMMARY' => 'Демо-решение комитета: согласовать следующий шаг и владельца внедрения.',
                'DECIDED_AT' => date('Y-m-d H:i:s', $baseTime + 216000),
            ], $sql);
            $created['committee_decisions']++;
        }

        if (in_array($stage, ['implemented', 'deployed'], true)) {
            self::insert('b_uds_ideabank_idea_coin', [
                'USER_ID' => $ownerId,
                'IDEA_ID' => $ideaId,
                'EVENT' => 'implemented',
                'COINS' => 120,
                'DESCRIPTION' => 'Демо-начисление за реализованную идею',
                'CREATED_AT' => date('Y-m-d H:i:s', $baseTime + 604800),
            ], $sql);
            $created['coins']++;
        }
    }

    private static function workflowForStage(string $stage): array
    {
        $route = ['submitted', 'moderation', 'published', 'initial_review', 'kpu', 'accepted', 'implementation', 'implemented', 'deployed'];

        if ($stage === 'draft') {
            return ['draft'];
        }

        if (in_array($stage, ['revise', 'backlog', 'rejected', 'duplicate', 'transferred'], true)) {
            return ['submitted', 'moderation', $stage];
        }

        $position = array_search($stage, $route, true);
        if ($position === false) {
            return ['submitted', $stage];
        }

        return array_slice($route, 0, $position + 1);
    }

    private static function workflowComment(string $stage): string
    {
        $comments = [
            'draft' => 'Черновик сохранен автором для доработки.',
            'submitted' => 'Идея отправлена в банк идей.',
            'moderation' => 'Модератор проверяет полноту и корректность описания.',
            'published' => 'Идея опубликована для обсуждения коллегами.',
            'initial_review' => 'Куратор проводит первичную проверку эффекта.',
            'kpu' => 'Инициатива передана на КПУ для оценки маршрута.',
            'accepted' => 'Идея принята в работу.',
            'implementation' => 'Идет пилотное внедрение.',
            'implemented' => 'Решение реализовано и подтверждено владельцем.',
            'deployed' => 'Практика внедрена и готова к тиражированию.',
            'transferred' => 'Идея передана в проектный контур.',
            'revise' => 'Требуется уточнение эффекта и плана внедрения.',
            'backlog' => 'Идея помещена в бэклог до появления ресурсов.',
            'rejected' => 'Идея отклонена с объяснением причины.',
            'duplicate' => 'Идея отмечена как дубликат существующей инициативы.',
        ];

        return $comments[$stage] ?? 'Зафиксировано движение по маршруту идеи.';
    }

    private static function feedbackMessage(string $stage): string
    {
        if ($stage === 'rejected') {
            return 'Причина отклонения показана для проверки негативного сценария.';
        }

        if ($stage === 'revise') {
            return 'Нужно уточнить расчет эффекта, владельца и первый шаг пилота.';
        }

        if (in_array($stage, ['implemented', 'deployed'], true)) {
            return 'Результат подтвержден, данные подходят для проверки витрины достижений.';
        }

        return 'Демо-обратная связь помогает проверить карточку идеи и историю маршрута.';
    }

    private static function commentText(int $index, string $stage): string
    {
        $comments = [
            'Предлагаю добавить короткий замер до/после, чтобы быстрее подтвердить эффект.',
            'Хорошо бы приложить пример стандарта и назначить владельца пилота.',
            'Поддерживаю идею, похожая проблема есть на соседнем участке.',
        ];

        if ($stage === 'duplicate') {
            return 'Похоже на уже существующий кейс, стоит связать карточки между собой.';
        }

        return $comments[$index % count($comments)];
    }

    private static function seedContestParts(int $contestId, array $userIds, SqlHelper $sql): int
    {
        $created = 0;
        $ideaRows = Application::getConnection()
            ->query('SELECT ID, OWNER_USER_ID FROM b_uds_ideabank_idea ORDER BY ID DESC LIMIT 5')
            ->fetchAll();

        foreach ($ideaRows as $index => $idea) {
            self::insert('b_uds_ideabank_idea_contest_part', [
                'CONTEST_ID' => $contestId,
                'USER_ID' => (int)($idea['OWNER_USER_ID'] ?? $userIds[$index % count($userIds)]),
                'IDEA_ID' => (int)$idea['ID'],
                'CREATED_AT' => date('Y-m-d H:i:s', strtotime('2024-09-01 09:00:00') + ($index * 86400)),
            ], $sql);
            $created++;
        }

        return $created;
    }

    private static function seedChallengeParts(int $challengeId, array $challenge, array $userIds, SqlHelper $sql): int
    {
        $created = 0;
        $connection = Application::getConnection();
        $dbSql = $connection->getSqlHelper();
        $direction = trim((string)($challenge['BUSINESS_DIRECTION'] ?? ''));
        $tips = array_values(array_filter(array_map('trim', explode(',', (string)($challenge['TIPS'] ?? '')))));
        $conditions = [];

        if ($direction !== '') {
            $conditions[] = "BUSINESS_DIRECTION = '" . $dbSql->forSql($direction) . "'";
        }

        foreach ($tips as $tip) {
            $safeTip = $dbSql->forSql($tip);
            $conditions[] = "(TITLE LIKE '%" . $safeTip . "%' OR DESCRIPTION LIKE '%" . $safeTip . "%' OR KEYWORDS LIKE '%" . $safeTip . "%')";
        }

        $where = $conditions !== [] ? ' WHERE ' . implode(' OR ', $conditions) : '';
        $ideaRows = $connection
            ->query('SELECT ID, OWNER_USER_ID FROM b_uds_ideabank_idea' . $where . ' ORDER BY ID DESC LIMIT 8')
            ->fetchAll();

        foreach ($ideaRows as $index => $idea) {
            self::insert('b_uds_ideabank_idea_challenge_part', [
                'CHALLENGE_ID' => $challengeId,
                'USER_ID' => (int)($idea['OWNER_USER_ID'] ?? $userIds[$index % count($userIds)]),
                'IDEA_ID' => (int)$idea['ID'],
                'CREATED_AT' => date('Y-m-d H:i:s', strtotime('2024-09-10 09:00:00') + ($index * 86400)),
            ], $sql);
            $created++;
        }

        return $created;
    }

    private static function syncIdeaOwner(int $ideaId, int $ownerUserId, SqlHelper $sql): void
    {
        if ($ideaId <= 0 || $ownerUserId <= 0) {
            return;
        }

        $connection = Application::getConnection();
        $connection->queryExecute(
            'UPDATE b_uds_ideabank_idea SET OWNER_USER_ID=' . $ownerUserId . ' WHERE ID=' . $ideaId
        );
        $connection->queryExecute('DELETE FROM b_uds_ideabank_idea_author WHERE IDEA_ID=' . $ideaId);
        self::insert('b_uds_ideabank_idea_author', [
            'IDEA_ID' => $ideaId,
            'USER_ID' => $ownerUserId,
            'SHARE_PERCENT' => 100,
        ], $sql);
        $connection->queryExecute(
            'UPDATE b_uds_ideabank_idea_coin SET USER_ID=' . $ownerUserId . ' WHERE IDEA_ID=' . $ideaId
        );
    }

    private static function cleanupDuplicateDemoIdeas(array $ideas): int
    {
        $connection = Application::getConnection();
        $sql = $connection->getSqlHelper();
        $removed = 0;

        foreach ($ideas as $idea) {
            $title = (string)($idea['TITLE'] ?? '');
            if ($title === '') {
                continue;
            }

            $rows = $connection
                ->query("SELECT ID FROM b_uds_ideabank_idea WHERE TITLE='" . $sql->forSql($title) . "' ORDER BY ID ASC")
                ->fetchAll();
            if (count($rows) <= 1) {
                continue;
            }

            $duplicateIds = array_map(static fn(array $row): int => (int)$row['ID'], array_slice($rows, 1));
            $idList = implode(',', $duplicateIds);
            foreach ([
                'b_uds_ideabank_idea_author',
                'b_uds_ideabank_idea_reaction',
                'b_uds_ideabank_idea_comment',
                'b_uds_ideabank_idea_workflow',
                'b_uds_ideabank_idea_feedback',
                'b_uds_ideabank_idea_expert_review',
                'b_uds_ideabank_idea_committee',
                'b_uds_ideabank_idea_coin',
                'b_uds_ideabank_idea_contest_part',
                'b_uds_ideabank_idea_challenge_part',
            ] as $table) {
                $connection->queryExecute('DELETE FROM ' . $table . ' WHERE IDEA_ID IN (' . $idList . ')');
            }
            $connection->queryExecute('DELETE FROM b_uds_ideabank_idea WHERE ID IN (' . $idList . ')');
            $removed += count($duplicateIds);
        }

        return $removed;
    }

    private static function clearContestParts(int $contestId): void
    {
        if ($contestId > 0) {
            Application::getConnection()->queryExecute('DELETE FROM b_uds_ideabank_idea_contest_part WHERE CONTEST_ID=' . $contestId);
        }
    }

    private static function clearChallengeParts(int $challengeId): void
    {
        if ($challengeId > 0) {
            Application::getConnection()->queryExecute('DELETE FROM b_uds_ideabank_idea_challenge_part WHERE CHALLENGE_ID=' . $challengeId);
        }
    }

    private static function seedDemoLeaderboardCoins(array $userIds, SqlHelper $sql): int
    {
        $connection = Application::getConnection();
        $connection->queryExecute("DELETE FROM b_uds_ideabank_idea_coin WHERE EVENT='demo_leaderboard'");

        $created = 0;
        $bonuses = [1600, 1480, 1360, 1250, 1140, 1040, 960, 880, 800, 720];
        foreach (array_slice($userIds, 0, 10) as $index => $userId) {
            self::insert('b_uds_ideabank_idea_coin', [
                'USER_ID' => (int)$userId,
                'IDEA_ID' => null,
                'EVENT' => 'demo_leaderboard',
                'COINS' => $bonuses[$index] ?? 600,
                'DESCRIPTION' => 'Демо-баланс для рейтинга авторов и амбассадоров идей',
                'CREATED_AT' => date('Y-m-d H:i:s', strtotime('2026-01-10 10:00:00') + ($index * 3600)),
            ], $sql);
            $created++;
        }

        return $created;
    }

    private static function exists(string $table, string $field, string $value): bool
    {
        $connection = Application::getConnection();
        $sql = $connection->getSqlHelper();
        return (bool)$connection->query('SELECT ID FROM ' . $table . ' WHERE ' . $field . "='" . $sql->forSql($value) . "' LIMIT 1")->fetch();
    }

    private static function findId(string $table, string $field, string $value): int
    {
        $connection = Application::getConnection();
        $sql = $connection->getSqlHelper();
        $row = $connection->query('SELECT ID FROM ' . $table . ' WHERE ' . $field . "='" . $sql->forSql($value) . "' LIMIT 1")->fetch();

        return is_array($row) ? (int)$row['ID'] : 0;
    }

    private static function contestHasParts(int $contestId): bool
    {
        $row = Application::getConnection()
            ->query('SELECT ID FROM b_uds_ideabank_idea_contest_part WHERE CONTEST_ID = ' . $contestId . ' LIMIT 1')
            ->fetch();

        return is_array($row);
    }

    private static function challengeHasParts(int $challengeId): bool
    {
        $row = Application::getConnection()
            ->query('SELECT ID FROM b_uds_ideabank_idea_challenge_part WHERE CHALLENGE_ID = ' . $challengeId . ' LIMIT 1')
            ->fetch();

        return is_array($row);
    }

    private static function insert(string $table, array $fields, SqlHelper $sql): int
    {
        $columns = [];
        $values = [];
        foreach ($fields as $field => $value) {
            $columns[] = $field;
            $values[] = $value === null ? 'NULL' : "'" . $sql->forSql((string)$value) . "'";
        }
        Application::getConnection()->queryExecute('INSERT INTO ' . $table . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ')');
        return (int)Application::getConnection()->getInsertedId();
    }
}
