<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ModuleManager;
use Bitrix\Main\ModuleTable;
use Bitrix\Main\SystemException;

defined('ADMIN_SECTION') || require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

Loc::loadMessages(__FILE__);

class uds_ideabank2 extends CModule
{
    public $MODULE_ID = 'uds.ideabank2';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME = 'Банк идей UDS (v2)';
    public $MODULE_DESCRIPTION = 'Банк идей: PPU-карточки, модерация, экспертиза, комитет, коины, конкурсы, новости';
    public $MODULE_SHORT_DESCRIPTION = 'Банк идей — модуль нового поколения';
    public $MODULE_GROUP_RIGHTS = 'Y';
    public $MODULE_PARENT = null;

    private bool $eventsInstalled = false;
    private bool $moduleRegistered = false;
    private bool $adminFilesInstalled = false;
    private bool $componentsInstalled = false;
    private bool $publicAssetsInstalled = false;
    private bool $tablesCreated = false;
    private bool $optionsInstalled = false;
    private bool $groupsInstalled = false;
    private bool $pagesInstalled = false;
    private string $investigationLogPath;

    public function __construct()
    {
        include __DIR__ . '/version.php';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'] ?? '1.0.0';
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'] ?? date('Y-m-d H:i:s');
        $this->investigationLogPath = '/tmp/uds-ideabank2-install-investigation.log';
    }

    public function GetModuleRightList(): array
    {
        return [
            'reference_id' => ['D', 'R', 'W'],
            'reference' => [
                '[D] ' . (Loc::getMessage('UDS_IB_DENIED') ?: 'Доступ запрещён'),
                '[R] ' . (Loc::getMessage('UDS_IB_READ') ?: 'Просмотр'),
                '[W] ' . (Loc::getMessage('UDS_IB_WRITE') ?: 'Редактирование'),
            ],
        ];
    }

    private function checkRights(): bool
    {
        global $APPLICATION;
        return $APPLICATION->GetGroupRight('main') >= 'W';
    }

    public function DoInstall(): bool
    {
        global $APPLICATION;

        $this->logInvestigation('DoInstall:start', [
            'module_id' => $this->MODULE_ID,
            'request_uri' => (string)($_SERVER['REQUEST_URI'] ?? ''),
        ]);

        if (!$this->checkRights()) {
            $this->logInvestigation('DoInstall:access_denied');
            $APPLICATION->ThrowException(Loc::getMessage('UDS_IB_ACCESS_DENIED') ?: 'Доступ к установке модуля запрещён');
            return false;
        }

        try {
            $this->registerModuleIfNeeded();

            if (!$this->isModuleRegistered()) {
                throw new SystemException('Module row was not created in b_module after register attempt');
            }

            $this->moduleRegistered = true;
            $this->logInvestigation('DoInstall:module_registered_confirmed');

            $this->installTables();
            $this->tablesCreated = true;
            $this->installOptions();
            $this->optionsInstalled = true;
            $this->installGroups();
            $this->groupsInstalled = true;
            $this->installSeedData();
            if ($this->shouldInstallDemoData()) {
                $this->installDemoData();
            }
            $this->installEvents();
            $this->eventsInstalled = true;
            $this->installComponents();
            $this->componentsInstalled = true;
            $this->installPublicAssets();
            $this->publicAssetsInstalled = true;
            $this->installAdminFiles();
            $this->adminFilesInstalled = true;
            $this->installMenu();
            $this->installPages();
            $this->pagesInstalled = true;

            $this->logInvestigation('DoInstall:success');
        } catch (\Throwable $e) {
            $this->logInvestigation('DoInstall:exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $this->rollbackInstall();
            $APPLICATION->ThrowException($e->getMessage());
            return false;
        }

        if (!isset($_REQUEST['mode']) && defined('ADMIN_SECTION')) {
            LocalRedirect('/bitrix/admin/partner_modules.php?lang=' . LANG);
        }

        return true;
    }

    public function DoUninstall(): bool
    {
        global $APPLICATION;

        try {
            $request = Application::getInstance()->getContext()->getRequest();
            $step = (int)($request->get('step') ?: $request->getPost('step') ?: 1);

            if (!isset($_REQUEST['mode']) && defined('ADMIN_SECTION') && $step < 2) {
                $APPLICATION->IncludeAdminFile(
                    Loc::getMessage('UDS_IB_UNINSTALL_TITLE') ?: 'Удаление модуля Банк идей UDS',
                    __DIR__ . '/unstep1.php'
                );

                return true;
            }

            if (!isset($_REQUEST['mode']) && defined('ADMIN_SECTION') && !check_bitrix_sessid()) {
                throw new SystemException('Некорректная CSRF-сессия удаления модуля');
            }

            $this->uninstallEvents();
            $this->uninstallMenu();
            $this->uninstallAdminFiles();
            $this->uninstallComponents();
            $this->uninstallPublicAssets();
            $this->uninstallGeneratedPages();
            $this->uninstallRights();

            $deleteMode = (string)($request->getPost('uds_ideabank2_delete_mode') ?: $request->get('uds_ideabank2_delete_mode') ?: 'keep');
            if ($deleteMode === 'full') {
                $this->uninstallTables();
                $this->uninstallRoleGroupsIfEmpty();
                $this->uninstallOptions();
            }

            if (ModuleManager::isModuleInstalled($this->MODULE_ID)) {
                ModuleManager::unRegisterModule($this->MODULE_ID);
            }

            if (!isset($_REQUEST['mode']) && defined('ADMIN_SECTION')) {
                $APPLICATION->IncludeAdminFile(
                    Loc::getMessage('UDS_IB_UNINSTALL_TITLE') ?: 'Удаление модуля Банк идей UDS',
                    __DIR__ . '/unstep2.php'
                );
            }
        } catch (\Throwable $e) {
            $APPLICATION->ThrowException($e->getMessage());
            return false;
        }

        return true;
    }

    public function DoUpdate()
    {
        if ($this->MODULE_VERSION !== '1.0.0') {
            $this->installSeedData();
        }

        // Гарантируем наличие ролевых групп после обновлений модуля
        // (включая группу модераторов) и актуальные option-связки role->group_id.
        $this->installOptions();
        $this->installGroups();
        $this->installPages();
        $this->installComponents();
        $this->installPublicAssets();
        $this->installAdminFiles();
        $this->installMenu();
    }

    private function shouldInstallDemoData(): bool
    {
        $request = Application::getInstance()->getContext()->getRequest();

        return $request->get('install_demo_data') === 'Y'
            || $request->getPost('install_demo_data') === 'Y';
    }

    private function installDemoData(): void
    {
        if (!class_exists('\\Uds\\Ideabank2\\Seed\\DemoDataSeeder')) {
            require_once dirname(__DIR__) . '/include.php';
        }

        $result = \Uds\Ideabank2\Seed\DemoDataSeeder::run();
        Option::set($this->MODULE_ID, 'feature_demo_data', 'Y');
        $this->logInvestigation('installDemoData:done', $result);
    }

    public function GetInstallPath(): string
    {
        return str_replace('\\', '/', __DIR__);
    }

    private function rollbackInstall(): void
    {
        $this->logInvestigation('rollbackInstall:start', [
            'events_installed' => $this->eventsInstalled ? 'Y' : 'N',
            'module_registered' => $this->moduleRegistered ? 'Y' : 'N',
            'admin_files_installed' => $this->adminFilesInstalled ? 'Y' : 'N',
            'components_installed' => $this->componentsInstalled ? 'Y' : 'N',
            'public_assets_installed' => $this->publicAssetsInstalled ? 'Y' : 'N',
            'tables_created' => $this->tablesCreated ? 'Y' : 'N',
            'options_installed' => $this->optionsInstalled ? 'Y' : 'N',
            'groups_installed' => $this->groupsInstalled ? 'Y' : 'N',
            'pages_installed' => $this->pagesInstalled ? 'Y' : 'N',
        ]);

        if ($this->pagesInstalled) {
            try {
                $this->uninstallGeneratedPages();
            } catch (\Throwable $e) {
                $this->logInvestigation('rollbackInstall:uninstallGeneratedPages_exception', ['message' => $e->getMessage()]);
            }
        }

        if ($this->adminFilesInstalled) {
            try {
                $this->uninstallAdminFiles();
            } catch (\Throwable $e) {
                $this->logInvestigation('rollbackInstall:uninstallAdminFiles_exception', ['message' => $e->getMessage()]);
            }
        }

        if ($this->componentsInstalled) {
            try {
                $this->uninstallComponents();
            } catch (\Throwable $e) {
                $this->logInvestigation('rollbackInstall:uninstallComponents_exception', ['message' => $e->getMessage()]);
            }
        }

        if ($this->publicAssetsInstalled) {
            try {
                $this->uninstallPublicAssets();
            } catch (\Throwable $e) {
                $this->logInvestigation('rollbackInstall:uninstallPublicAssets_exception', ['message' => $e->getMessage()]);
            }
        }

        if ($this->eventsInstalled) {
            try {
                $this->uninstallEvents();
            } catch (\Throwable $e) {
                $this->logInvestigation('rollbackInstall:uninstallEvents_exception', ['message' => $e->getMessage()]);
            }
        }

        if ($this->moduleRegistered) {
            try {
                if (ModuleManager::isModuleInstalled($this->MODULE_ID)) {
                    ModuleManager::unRegisterModule($this->MODULE_ID);
                }
            } catch (\Throwable $e) {
                $this->logInvestigation('rollbackInstall:unregister_exception', ['message' => $e->getMessage()]);
            }
        }

        $this->logInvestigation('rollbackInstall:done');
    }

    private function isModuleRegistered(): bool
    {
        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();

        return (bool)$connection
            ->query("SELECT ID FROM b_module WHERE ID = '" . $sqlHelper->forSql($this->MODULE_ID) . "'")
            ->fetch();
    }

    private function registerModuleIfNeeded(): void
    {
        $this->logInvestigation('registerModuleIfNeeded:begin', [
            'is_installed_before' => ModuleManager::isModuleInstalled($this->MODULE_ID) ? 'Y' : 'N',
            'row_exists_before' => $this->isModuleRegistered() ? 'Y' : 'N',
        ]);

        if (ModuleManager::isModuleInstalled($this->MODULE_ID) && $this->isModuleRegistered()) {
            $this->logInvestigation('registerModuleIfNeeded:skip_already_installed');
            return;
        }

        $this->clearModuleListCache();
        ModuleTable::cleanCache();

        if (!$this->isModuleRegistered()) {
            ModuleManager::registerModule($this->MODULE_ID);
            $this->logInvestigation('registerModuleIfNeeded:register_called');
        }

        ModuleTable::cleanCache();
        $this->clearModuleListCache();

        $this->logInvestigation('registerModuleIfNeeded:end', [
            'is_installed_after' => ModuleManager::isModuleInstalled($this->MODULE_ID) ? 'Y' : 'N',
            'row_exists_after' => $this->isModuleRegistered() ? 'Y' : 'N',
        ]);
    }

    private function clearModuleListCache(): void
    {
        $managedCache = Application::getInstance()->getManagedCache();
        $managedCache->cleanDir('orm_b_module');

        if (function_exists('BXClearCache')) {
            BXClearCache(true, '/orm_b_module/');
        }
    }

    private function logInvestigation(string $step, array $context = []): void
    {
        $line = sprintf(
            "[%s] %s %s\n",
            date('c'),
            $step,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
        );

        @file_put_contents($this->investigationLogPath, $line, FILE_APPEND);
    }

    private function seedRowExists(string $tableName, string $codeField, string $code): bool
    {
        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();

        $quotedTable = $sqlHelper->quote($tableName);
        $quotedField = $sqlHelper->quote($codeField);
        $safeCode = $sqlHelper->forSql($code);

        $result = $connection->query(
            "SELECT ID FROM " . $quotedTable . " WHERE " . $quotedField . " = '" . $safeCode . "' LIMIT 1"
        );

        return (bool)$result->fetch();
    }

    public function installTables(): void
    {
        $connection = Application::getConnection();
        $sql = [
            "CREATE TABLE IF NOT EXISTS b_uds_ideabank_idea (
                ID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                TITLE VARCHAR(255) NOT NULL,
                CODE VARCHAR(32) NULL,
                TYPE VARCHAR(64) NULL DEFAULT 'Идея улучшения',
                CATEGORY_ID INT NULL,
                STATUS_ID INT NOT NULL DEFAULT 1,
                STAGE VARCHAR(64) NULL,
                DESCRIPTION TEXT NULL,
                PROBLEM TEXT NULL,
                LOSSES TEXT NULL,
                PROPOSAL TEXT NULL,
                ECONOMIC_EFFECT DOUBLE NULL DEFAULT 0,
                CONFIRMED_EFFECT DOUBLE NULL DEFAULT 0,
                IMPLEMENTATION_DAYS INT NULL DEFAULT 0,
                OWNER_USER_ID INT NOT NULL,
                ASSIGNEE_USER_ID INT NULL,
                IS_DRAFT ENUM('Y','N') NOT NULL DEFAULT 'N',
                IS_ANONYMOUS ENUM('Y','N') NOT NULL DEFAULT 'N',
                IS_HIDDEN ENUM('Y','N') NOT NULL DEFAULT 'N',
                SOURCE_ID INT NULL,
                BUSINESS_DIRECTION VARCHAR(128) NULL,
                KEYWORDS VARCHAR(512) NULL,
                ADDITIONAL_WORK TEXT NULL,
                EXTRA_EFFECTS TEXT NULL,
                ECONOMIC_EFFECT_TEXT TEXT NULL,
                IMPLEMENTATION_PLAN TEXT NULL,
                RESOURCES_NEEDED TEXT NULL,
                RISKS TEXT NULL,
                TARGET_DATE DATE NULL,
                PROGRESS_PERCENT INT NULL DEFAULT 0,
                SUBMISSION_REWARD_GRANTED_AT DATETIME NULL,
                SUBMISSION_COIN_REWARD INT NULL,
                ACCEPTED_REWARD_GRANTED_AT DATETIME NULL,
                ACCEPTED_COIN_REWARD INT NULL,
                IMPLEMENTED_REWARD_GRANTED_AT DATETIME NULL,
                IMPLEMENTED_COIN_REWARD INT NULL,
                REPLICATION_REWARD_GRANTED_AT DATETIME NULL,
                REPLICATION_COIN_REWARD INT NULL,
                ENGAGEMENT_REWARD_GRANTED_AT DATETIME NULL,
                ENGAGEMENT_COIN_REWARD INT NULL,
                MODERATED_AT DATETIME NULL,
                MODERATED_BY INT NULL,
                MODERATION_DECISION VARCHAR(32) NULL,
                MODERATION_FEEDBACK TEXT NULL,
                PUBLISHED_AT DATETIME NULL,
                CREATED_AT DATETIME NOT NULL,
                SUBMITTED_AT DATETIME NULL,
                UPDATED_AT DATETIME NULL
            )",
            "CREATE TABLE IF NOT EXISTS b_uds_ideabank_idea_author (
                ID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                IDEA_ID INT NOT NULL,
                USER_ID INT NOT NULL,
                SHARE_PERCENT INT NOT NULL DEFAULT 100
            )",
            "CREATE TABLE IF NOT EXISTS b_uds_ideabank_idea_status (
                ID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                NAME VARCHAR(128) NOT NULL,
                CODE VARCHAR(32) NOT NULL,
                SORT INT NOT NULL DEFAULT 500,
                COLOR VARCHAR(16) NULL
            )",
            "CREATE TABLE IF NOT EXISTS b_uds_ideabank_idea_category (
                ID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                NAME VARCHAR(128) NOT NULL,
                CODE VARCHAR(32) NOT NULL,
                SORT INT NOT NULL DEFAULT 500
            )",
            "CREATE TABLE IF NOT EXISTS b_uds_ideabank_idea_reaction (
                ID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                IDEA_ID INT NOT NULL,
                USER_ID INT NOT NULL,
                TYPE VARCHAR(16) NOT NULL,
                CREATED_AT DATETIME NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS b_uds_ideabank_idea_comment (
                ID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                IDEA_ID INT NOT NULL,
                USER_ID INT NOT NULL,
                TEXT TEXT NOT NULL,
                CREATED_AT DATETIME NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS b_uds_ideabank_idea_file (
                ID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                IDEA_ID INT NOT NULL,
                FILE_ID INT NOT NULL,
                ORIGINAL_NAME VARCHAR(255) NULL,
                SIZE INT NULL DEFAULT 0,
                CONTENT_TYPE VARCHAR(255) NULL,
                CREATED_BY INT NULL,
                CREATED_AT DATETIME NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS b_uds_ideabank_idea_workflow (
                ID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                IDEA_ID INT NOT NULL,
                USER_ID INT NOT NULL,
                STATUS VARCHAR(64) NOT NULL,
                STAGE VARCHAR(64) NOT NULL,
                COMMENT TEXT NULL,
                CREATED_AT DATETIME NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS b_uds_ideabank_idea_feedback (
                ID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                IDEA_ID INT NOT NULL,
                USER_ID INT NOT NULL,
                STAGE VARCHAR(64) NOT NULL,
                TONE VARCHAR(16) NOT NULL DEFAULT 'info',
                MESSAGE TEXT NOT NULL,
                CREATED_AT DATETIME NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS b_uds_ideabank_idea_expert_review (
                ID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                IDEA_ID INT NOT NULL,
                EXPERT_USER_ID INT NOT NULL,
                SCORE INT NOT NULL DEFAULT 0,
                COMMENT TEXT NULL,
                CREATED_AT DATETIME NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS b_uds_ideabank_idea_committee (
                ID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                IDEA_ID INT NOT NULL,
                USER_ID INT NOT NULL,
                DECISION VARCHAR(64) NOT NULL,
                SUMMARY TEXT NULL,
                DECIDED_AT DATETIME NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS b_uds_ideabank_idea_coin (
                ID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                USER_ID INT NOT NULL,
                IDEA_ID INT NULL,
                EVENT VARCHAR(32) NOT NULL,
                COINS INT NOT NULL DEFAULT 0,
                DESCRIPTION TEXT NULL,
                CREATED_AT DATETIME NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS b_uds_ideabank_idea_contest (
                ID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                TITLE VARCHAR(255) NOT NULL,
                DESCRIPTION TEXT NULL,
                DATE_LABEL VARCHAR(128) NULL,
                DEADLINE DATE NULL,
                ORGANIZER_USER_ID INT NULL,
                IMAGE VARCHAR(512) NULL,
                REQUIREMENTS TEXT NULL
            )",
            "CREATE TABLE IF NOT EXISTS b_uds_ideabank_idea_contest_part (
                ID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                CONTEST_ID INT NOT NULL,
                USER_ID INT NOT NULL,
                IDEA_ID INT NULL,
                CREATED_AT DATETIME NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS b_uds_ideabank_idea_challenge (
                ID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                TITLE VARCHAR(255) NOT NULL,
                PERIOD VARCHAR(128) NULL,
                TARGET TEXT NULL,
                REWARD_BONUS INT NULL DEFAULT 0,
                BUSINESS_DIRECTION VARCHAR(128) NULL,
                TIPS VARCHAR(512) NULL
            )",
            "CREATE TABLE IF NOT EXISTS b_uds_ideabank_idea_challenge_part (
                ID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                CHALLENGE_ID INT NOT NULL,
                USER_ID INT NOT NULL,
                IDEA_ID INT NOT NULL,
                CREATED_AT DATETIME NOT NULL
            )",
            "CREATE TABLE IF NOT EXISTS b_uds_ideabank_idea_news (
                ID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                CATEGORY VARCHAR(128) NULL,
                TITLE VARCHAR(255) NOT NULL,
                EXCERPT TEXT NULL,
                BODY TEXT NULL,
                IMAGE VARCHAR(512) NULL,
                HERO_IMAGE VARCHAR(512) NULL,
                AUTHOR_USER_ID INT NULL,
                DATE DATE NULL,
                QUOTE TEXT NULL
            )",
            "CREATE TABLE IF NOT EXISTS b_uds_ideabank_idea_reward_rule (
                ID INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                EVENT VARCHAR(32) NOT NULL,
                LABEL VARCHAR(128) NOT NULL,
                COINS INT NOT NULL DEFAULT 0,
                DESCRIPTION TEXT NULL
            )",
        ];

        foreach ($sql as $query) {
            try {
                $connection->queryExecute($query);
            } catch (\Throwable $e) {
                $this->logInvestigation('installTables:query_exception', ['message' => $e->getMessage()]);
            }
        }

        $this->ensureIdeaTableColumns();
    }

    private function ensureIdeaTableColumns(): void
    {
        $connection = Application::getConnection();
        $columns = [];

        try {
            $result = $connection->query('SHOW COLUMNS FROM b_uds_ideabank_idea');
            while ($row = $result->fetch()) {
                $columns[(string)$row['Field']] = true;
            }

            if (empty($columns['IS_HIDDEN'])) {
                $connection->queryExecute("ALTER TABLE b_uds_ideabank_idea ADD IS_HIDDEN ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER IS_ANONYMOUS");
            }
        } catch (\Throwable $e) {
            $this->logInvestigation('ensureIdeaTableColumns:exception', ['message' => $e->getMessage()]);
        }
    }

    public function installSeedData(): void
    {
        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();

        $statuses = [
            ['NAME' => 'На модерации', 'CODE' => 'moderation', 'SORT' => 10, 'COLOR' => '#f0ad4e'],
            ['NAME' => 'Опубликовано', 'CODE' => 'published', 'SORT' => 20, 'COLOR' => '#5bc0de'],
            ['NAME' => 'Первичная проверка', 'CODE' => 'initial_review', 'SORT' => 30, 'COLOR' => '#5bc0de'],
            ['NAME' => 'КПУ', 'CODE' => 'kpu', 'SORT' => 40, 'COLOR' => '#d9534f'],
            ['NAME' => 'Передано в проект', 'CODE' => 'transferred', 'SORT' => 50, 'COLOR' => '#5bc0de'],
            ['NAME' => 'Принято', 'CODE' => 'accepted', 'SORT' => 60, 'COLOR' => '#5cb85c'],
            ['NAME' => 'Реализация', 'CODE' => 'implementation', 'SORT' => 70, 'COLOR' => '#0b5fc8'],
            ['NAME' => 'Реализовано', 'CODE' => 'implemented', 'SORT' => 80, 'COLOR' => '#5cb85c'],
            ['NAME' => 'Внедрено', 'CODE' => 'deployed', 'SORT' => 90, 'COLOR' => '#368149'],
            ['NAME' => 'Дополнить', 'CODE' => 'revise', 'SORT' => 100, 'COLOR' => '#f0ad4e'],
            ['NAME' => 'Бэклог', 'CODE' => 'backlog', 'SORT' => 110, 'COLOR' => '#999'],
            ['NAME' => 'Отклонено', 'CODE' => 'rejected', 'SORT' => 120, 'COLOR' => '#d9534f'],
            ['NAME' => 'Дубликат', 'CODE' => 'duplicate', 'SORT' => 130, 'COLOR' => '#999'],
        ];

        foreach ($statuses as $s) {
            if ($this->seedRowExists('b_uds_ideabank_idea_status', 'CODE', $s['CODE'])) {
                continue;
            }

            $sql = sprintf(
                "INSERT INTO b_uds_ideabank_idea_status (NAME, CODE, SORT, COLOR) VALUES ('%s', '%s', %d, '%s')",
                $sqlHelper->forSql($s['NAME']),
                $sqlHelper->forSql($s['CODE']),
                $s['SORT'],
                $sqlHelper->forSql((string)$s['COLOR'])
            );
            $connection->queryExecute($sql);
        }

        $categories = [
            ['NAME' => 'Организация', 'CODE' => 'organization', 'SORT' => 10],
            ['NAME' => 'Качество', 'CODE' => 'quality', 'SORT' => 20],
            ['NAME' => 'Стоимость', 'CODE' => 'cost', 'SORT' => 30],
            ['NAME' => 'Охрана труда', 'CODE' => 'safety', 'SORT' => 40],
            ['NAME' => 'Культура производства', 'CODE' => 'culture', 'SORT' => 50],
            ['NAME' => 'Эффективность', 'CODE' => 'efficiency', 'SORT' => 60],
            ['NAME' => 'Другое', 'CODE' => 'other', 'SORT' => 70],
        ];

        foreach ($categories as $c) {
            if ($this->seedRowExists('b_uds_ideabank_idea_category', 'CODE', $c['CODE'])) {
                continue;
            }

            $sql = sprintf(
                "INSERT INTO b_uds_ideabank_idea_category (NAME, CODE, SORT) VALUES ('%s', '%s', %d)",
                $sqlHelper->forSql($c['NAME']),
                $sqlHelper->forSql($c['CODE']),
                $c['SORT']
            );
            $connection->queryExecute($sql);
        }

        $rewards = [
            ['EVENT' => 'submitted', 'LABEL' => 'Идея отправлена', 'COINS' => 30, 'DESCRIPTION' => 'Базовый бонус за подачу идеи распределяется между авторами по долям участия.'],
            ['EVENT' => 'engagement', 'LABEL' => 'Идея получила отклик коллег', 'COINS' => 12, 'DESCRIPTION' => 'Небольшое поощрение за интерес коллег, комментарии и голоса.'],
            ['EVENT' => 'accepted', 'LABEL' => 'Идея принята в работу', 'COINS' => 45, 'DESCRIPTION' => 'Дополнительный бонус за прохождение первичной проверки.'],
            ['EVENT' => 'implemented', 'LABEL' => 'Идея реализована', 'COINS' => 120, 'DESCRIPTION' => 'Главная награда за доведенное до результата улучшение.'],
            ['EVENT' => 'replicated', 'LABEL' => 'Идею взяли за основу', 'COINS' => 80, 'DESCRIPTION' => 'Награда за тиражирование сильной практики на другие участки.'],
        ];

        foreach ($rewards as $r) {
            if ($this->seedRowExists('b_uds_ideabank_idea_reward_rule', 'EVENT', $r['EVENT'])) {
                continue;
            }

            $sql = sprintf(
                "INSERT INTO b_uds_ideabank_idea_reward_rule (EVENT, LABEL, COINS, DESCRIPTION) VALUES ('%s', '%s', %d, '%s')",
                $sqlHelper->forSql($r['EVENT']),
                $sqlHelper->forSql($r['LABEL']),
                $r['COINS'],
                $sqlHelper->forSql($r['DESCRIPTION'])
            );
            $connection->queryExecute($sql);
        }
    }

    public function installOptions(): void
    {
        $defaults = [];
        $defaultOptionPath = dirname(__DIR__) . '/default_option.php';
        if (is_file($defaultOptionPath)) {
            include $defaultOptionPath;
            $defaults = $uds_ideabank2_default_option ?? [];
        }

        foreach ($defaults as $name => $value) {
            $current = Option::get($this->MODULE_ID, (string)$name, null);
            if ($current === null) {
                Option::set($this->MODULE_ID, (string)$name, (string)$value);
            }
        }
    }

    public function uninstallOptions(): void
    {
        Option::delete($this->MODULE_ID);
    }

    public function installGroups(): void
    {
        if (!class_exists('CGroup')) {
            throw new SystemException('CGroup API is not available');
        }

        $groups = [
            'group_participants_id' => ['STRING_ID' => 'UDS_IDEABANK2_PARTICIPANTS', 'NAME' => 'Идеабанк: участники'],
            'group_moderators_id' => ['STRING_ID' => 'UDS_IDEABANK2_MODERATORS', 'NAME' => 'Идеабанк: модераторы'],
            'group_experts_id' => ['STRING_ID' => 'UDS_IDEABANK2_EXPERTS', 'NAME' => 'Идеабанк: эксперты'],
            'group_committee_id' => ['STRING_ID' => 'UDS_IDEABANK2_COMMITTEE', 'NAME' => 'Идеабанк: комитет'],
            'group_admins_id' => ['STRING_ID' => 'UDS_IDEABANK2_ADMINS', 'NAME' => 'Администратор банка идей'],
        ];

        $groupIdsByOption = [];

        foreach ($groups as $optionName => $group) {
            $groupId = $this->findGroupIdByStringId($group['STRING_ID']);
            if ($groupId <= 0) {
                $groupApi = new CGroup();
                $groupId = (int)$groupApi->Add([
                    'ACTIVE' => 'Y',
                    'C_SORT' => 500,
                    'NAME' => $group['NAME'],
                    'STRING_ID' => $group['STRING_ID'],
                    'DESCRIPTION' => 'Служебная группа роли модуля uds.ideabank2. Создана установщиком модуля.',
                ]);

                if ($groupId <= 0) {
                    $error = trim((string)($groupApi->LAST_ERROR ?? ''));
                    throw new SystemException('Failed to create group ' . $group['STRING_ID'] . ($error !== '' ? ': ' . $error : ''));
                }
            }

            Option::set($this->MODULE_ID, $optionName, (string)$groupId);
            $groupIdsByOption[$optionName] = $groupId;
        }

        $this->installRights($groupIdsByOption);
    }

    public function installRights(array $groupIdsByOption = []): void
    {
        if (!class_exists('CGroup')) {
            throw new SystemException('CGroup API is not available');
        }

        $rights = [
            'group_participants_id' => 'R',
            'group_moderators_id' => 'W',
            'group_experts_id' => 'W',
            'group_committee_id' => 'W',
            'group_admins_id' => 'W',
        ];

        foreach ($rights as $optionName => $right) {
            $groupId = (int)($groupIdsByOption[$optionName] ?? Option::get($this->MODULE_ID, $optionName, '0'));
            if ($groupId <= 0) {
                continue;
            }

            CGroup::SetModulePermission($groupId, $this->MODULE_ID, $right);
        }
    }

    public function uninstallRights(): void
    {
        if (!class_exists('CGroup')) {
            return;
        }

        foreach ($this->getRoleGroups() as $group) {
            $groupId = $this->findGroupIdByStringId($group['STRING_ID']);
            if ($groupId <= 0) {
                continue;
            }

            CGroup::SetModulePermission($groupId, $this->MODULE_ID, false);
        }
    }

    public function uninstallRoleGroupsIfEmpty(): void
    {
        if (!class_exists('CGroup')) {
            return;
        }

        foreach ($this->getRoleGroups() as $group) {
            $groupId = $this->findGroupIdByStringId($group['STRING_ID']);
            if ($groupId <= 0 || $this->groupHasUsers($groupId)) {
                continue;
            }

            CGroup::Delete($groupId);
        }
    }

    private function getRoleGroups(): array
    {
        return [
            'group_participants_id' => ['STRING_ID' => 'UDS_IDEABANK2_PARTICIPANTS', 'NAME' => 'Идеабанк: участники'],
            'group_moderators_id' => ['STRING_ID' => 'UDS_IDEABANK2_MODERATORS', 'NAME' => 'Идеабанк: модераторы'],
            'group_experts_id' => ['STRING_ID' => 'UDS_IDEABANK2_EXPERTS', 'NAME' => 'Идеабанк: эксперты'],
            'group_committee_id' => ['STRING_ID' => 'UDS_IDEABANK2_COMMITTEE', 'NAME' => 'Идеабанк: комитет'],
            'group_admins_id' => ['STRING_ID' => 'UDS_IDEABANK2_ADMINS', 'NAME' => 'Администратор банка идей'],
        ];
    }

    private function findGroupIdByStringId(string $stringId): int
    {
        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();
        $row = $connection->query(
            "SELECT ID FROM b_group WHERE STRING_ID = '" . $sqlHelper->forSql($stringId) . "' LIMIT 1"
        )->fetch();

        return is_array($row) ? (int)$row['ID'] : 0;
    }

    private function groupHasUsers(int $groupId): bool
    {
        $connection = Application::getConnection();
        $row = $connection->query(
            'SELECT USER_ID FROM b_user_group WHERE GROUP_ID = ' . $groupId . ' LIMIT 1'
        )->fetch();

        return is_array($row);
    }

    private function getModuleEvents(): array
    {
        return [
            ['EVENT_NAME' => 'onBeforeIdeaCreate', 'CLASS' => '\\Uds\\Ideabank2\\Events\\IdeaEvents', 'METHOD' => 'onBeforeIdeaCreate'],
            ['EVENT_NAME' => 'onAfterIdeaCreate', 'CLASS' => '\\Uds\\Ideabank2\\Events\\IdeaEvents', 'METHOD' => 'onAfterIdeaCreate'],
            ['EVENT_NAME' => 'onBeforeIdeaStatusChange', 'CLASS' => '\\Uds\\Ideabank2\\Events\\IdeaEvents', 'METHOD' => 'onBeforeIdeaStatusChange'],
            ['EVENT_NAME' => 'onAfterIdeaStatusChange', 'CLASS' => '\\Uds\\Ideabank2\\Events\\IdeaEvents', 'METHOD' => 'onAfterIdeaStatusChange'],
            ['EVENT_NAME' => 'onBeforeCoinAward', 'CLASS' => '\\Uds\\Ideabank2\\Events\\CoinEvents', 'METHOD' => 'onBeforeCoinAward'],
        ];
    }

    public function installEvents(): void
    {
        $this->uninstallEvents();
        $eventManager = EventManager::getInstance();

        foreach ($this->getModuleEvents() as $event) {
            $eventManager->registerEventHandler(
                $this->MODULE_ID,
                $event['EVENT_NAME'],
                $this->MODULE_ID,
                $event['CLASS'],
                $event['METHOD']
            );
        }

        $eventManager->registerEventHandlerCompatible(
            'main',
            'OnBuildGlobalMenu',
            $this->MODULE_ID,
            '',
            'udsIdeabank2OnBuildGlobalMenu',
            100,
            '/local/modules/uds.ideabank2/lib/Uds/Ideabank2/Admin/menu_handler.php'
        );
    }

    public function uninstallEvents(): void
    {
        $eventManager = EventManager::getInstance();

        foreach ($this->getModuleEvents() as $event) {
            $eventManager->unRegisterEventHandler(
                $this->MODULE_ID,
                $event['EVENT_NAME'],
                $this->MODULE_ID,
                $event['CLASS'],
                $event['METHOD']
            );
        }

        $eventManager->unRegisterEventHandler(
            'main',
            'OnBuildGlobalMenu',
            $this->MODULE_ID,
            '',
            'udsIdeabank2OnBuildGlobalMenu'
        );
    }

    public function installAdminFiles(): void
    {
        $source = __DIR__ . '/../admin';
        $target = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin';

        if (!is_dir($source)) {
            return;
        }

        $copied = CopyDirFiles($source, $target, true, true);
        if (!$copied) {
            throw new SystemException('Failed to copy admin files from ' . $source . ' to ' . $target);
        }

        $localAdminDir = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') . '/local/admin';
        $this->ensureDirectory($localAdminDir);
        foreach ($this->getAdminProxyMap() as $proxyFile => $moduleAdminFile) {
            $this->writeFileIfMissing(
                $localAdminDir . '/' . $proxyFile,
                $this->buildLocalAdminProxy($moduleAdminFile)
            );
        }
    }

    public function uninstallAdminFiles(): void
    {
        $source = __DIR__ . '/../admin';
        $target = $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin';

        if (!is_dir($source)) {
            return;
        }

        DeleteDirFiles($source, $target);

        $localAdminDir = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') . '/local/admin';
        foreach (array_keys($this->getAdminProxyMap()) as $proxyFile) {
            $path = $localAdminDir . '/' . $proxyFile;
            if ($this->isGeneratedPublicFile($path)) {
                @unlink($path);
            }
        }
    }

    private function getAdminProxyMap(): array
    {
        return [
            'uds_ideabank2.php' => 'ideabank.php',
            'uds_ideabank2_moderation.php' => 'ideabank_moderation.php',
            'uds_ideabank2_statuses.php' => 'ideabank_statuses.php',
            'uds_ideabank2_categories.php' => 'ideabank_categories.php',
            'uds_ideabank2_rewards.php' => 'ideabank_rewards.php',
            'uds_ideabank2_contests.php' => 'ideabank_contests.php',
            'uds_ideabank2_challenges.php' => 'ideabank_challenges.php',
        ];
    }

    private function buildLocalAdminProxy(string $moduleAdminFile): string
    {
        return "<?php\n"
            . "// UDS_IDEABANK2_GENERATED_PUBLIC_PAGE\n"
            . "require_once \$_SERVER['DOCUMENT_ROOT'] . '/local/modules/uds.ideabank2/admin/" . addslashes($moduleAdminFile) . "';\n";
    }

    public function installComponents(): void
    {
        $source = __DIR__ . '/components';
        $target = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') . '/local/components';

        if (!is_dir($source)) {
            throw new SystemException('Public component package is missing: ' . $source);
        }

        $this->ensureDirectory($target);
        $copied = CopyDirFiles($source, $target, true, true);
        if (!$copied) {
            throw new SystemException('Failed to copy public components from ' . $source . ' to ' . $target);
        }
    }

    public function uninstallComponents(): void
    {
        $baseDir = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') . '/local/components/uds';

        foreach ($this->getPublicComponentNames() as $componentName) {
            $this->deleteDirectory($baseDir . '/' . $componentName);
        }
    }

    private function getPublicComponentNames(): array
    {
        return [
            'ideabank.home',
            'ideabank.idea.list',
            'ideabank.idea.form',
            'ideabank.idea.detail',
            'ideabank.news.list',
            'ideabank.news.detail',
            'ideabank.contest.list',
            'ideabank.contest.detail',
            'ideabank.docs',
            'ideabank.hall',
            'ideabank.stats',
        ];
    }

    public function installPublicAssets(): void
    {
        $documentRoot = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/');

        $assetSource = __DIR__ . '/js';
        if (!is_dir($assetSource)) {
            throw new SystemException('Public asset package is missing: ' . $assetSource);
        }

        $assetTarget = $documentRoot . '/local/js';
        $this->ensureDirectory($assetTarget);
        if (!CopyDirFiles($assetSource, $assetTarget, true, true)) {
            throw new SystemException('Failed to copy public assets from ' . $assetSource . ' to ' . $assetTarget);
        }

        $helperSource = __DIR__ . '/php_interface/include/ideabank2_public_helpers.php';
        if (!is_file($helperSource)) {
            throw new SystemException('Public helper file is missing: ' . $helperSource);
        }

        $helperTargetDir = $documentRoot . '/local/php_interface/include';
        $this->ensureDirectory($helperTargetDir);
        if (!CopyDirFiles(dirname($helperSource), $helperTargetDir, true, true)) {
            throw new SystemException('Failed to copy public helper to ' . $helperTargetDir);
        }
    }

    public function uninstallPublicAssets(): void
    {
        $documentRoot = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/');

        @unlink($documentRoot . '/local/js/uds/ideabank2/public.css');
        @unlink($documentRoot . '/local/js/uds/ideabank2/public.js');
        @unlink($documentRoot . '/local/php_interface/include/ideabank2_public_helpers.php');

        $this->deleteDirectoryIfEmpty($documentRoot . '/local/js/uds/ideabank2');
    }

    private function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        if (function_exists('DeleteDirFilesEx')) {
            DeleteDirFilesEx(str_replace(rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/'), '', $path));
            return;
        }

        $items = scandir($path);
        if (!is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path . '/' . $item;
            if (is_dir($itemPath)) {
                $this->deleteDirectory($itemPath);
            } else {
                @unlink($itemPath);
            }
        }

        @rmdir($path);
    }

    private function deleteDirectoryIfEmpty(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === ['.', '..'] || $items === ['..', '.']) {
            @rmdir($path);
        }
    }

    public function installMenu(): void
    {
        $this->clearAdminMenuCache();
    }

    public function uninstallMenu(): void
    {
        $this->clearAdminMenuCache();
    }

    private function clearAdminMenuCache(): void
    {
        if (function_exists('BXClearCache')) {
            BXClearCache(true, '/menu/');
            BXClearCache(true, '/bitrix/menu/');
        }
    }

    public function uninstallTables(): void
    {
        $connection = Application::getConnection();
        $prefix = 'b_uds_ideabank_';

        try {
            if ($connection->isTableExists($prefix . 'idea_file')) {
                $result = $connection->query('SELECT FILE_ID FROM ' . $prefix . 'idea_file');
                while ($row = $result->fetch()) {
                    if (!empty($row['FILE_ID'])) {
                        \CFile::Delete((int)$row['FILE_ID']);
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        $names = [
            'idea', 'idea_author', 'idea_status', 'idea_category',
            'idea_reaction', 'idea_comment', 'idea_file', 'idea_workflow', 'idea_feedback',
            'idea_expert_review', 'idea_committee', 'idea_coin',
            'idea_contest', 'idea_contest_part', 'idea_challenge_part', 'idea_challenge',
            'idea_news', 'idea_reward_rule',
        ];

        foreach ($names as $name) {
            try {
                $connection->dropTable($prefix . $name);
            } catch (\Throwable $e) {
            }
        }
    }

    public function installPages(): void
    {
        $baseDir = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') . '/ideabank';
        $this->ensureDirectory($baseDir);

        foreach ($this->getPublicPageMap() as $fileName => $page) {
            $this->writeFileIfMissing(
                $baseDir . '/' . $fileName,
                $this->buildPublicPage((string)$page['title'], (string)$page['component'])
            );
        }

        $this->writeFileIfMissing($baseDir . '/.section.php', $this->getSectionFileContents());
        $this->writeFileIfMissing($baseDir . '/.top.menu.php', $this->getTopMenuContents());
        $this->writeFileIfMissing($baseDir . '/.left.menu.php', $this->getLeftMenuContents());
        $this->writeFileIfMissing($baseDir . '/debug_auth.php', $this->getDebugAuthContents());

        $this->clearPublicMenuCache();
    }

    private function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!CheckDirPath($path . '/')) {
            throw new SystemException('Failed to create directory ' . $path);
        }
    }

    private function writeFileIfMissing(string $path, string $contents): void
    {
        if (is_file($path)) {
            return;
        }

        $this->ensureDirectory(dirname($path));
        $result = file_put_contents($path, $contents);
        if ($result === false) {
            throw new SystemException('Failed to create file ' . $path);
        }
    }

    private function getPublicPageMap(): array
    {
        return [
            'index.php' => ['title' => 'Банк идей', 'component' => 'uds:ideabank.home'],
            'management.php' => ['title' => 'Список идей', 'component' => 'uds:ideabank.idea.list'],
            'ppu-form.php' => ['title' => 'Форма идеи', 'component' => 'uds:ideabank.idea.form'],
            'ppu-detail.php' => ['title' => 'Карточка идеи', 'component' => 'uds:ideabank.idea.detail'],
            'news.php' => ['title' => 'Новости банка идей', 'component' => 'uds:ideabank.news.list'],
            'news-detail.php' => ['title' => 'Новость банка идей', 'component' => 'uds:ideabank.news.detail'],
            'contests.php' => ['title' => 'Конкурсы идей', 'component' => 'uds:ideabank.contest.list'],
            'contest-detail.php' => ['title' => 'Конкурс идей', 'component' => 'uds:ideabank.contest.detail'],
            'docs.php' => ['title' => 'Документация банка идей', 'component' => 'uds:ideabank.docs'],
            'hall-of-fame.php' => ['title' => 'Аллея славы', 'component' => 'uds:ideabank.hall'],
            'stats.php' => ['title' => 'Статистика банка идей', 'component' => 'uds:ideabank.stats'],
        ];
    }

    private function buildPublicPage(string $title, string $component): string
    {
        return "<?php\n"
            . "// UDS_IDEABANK2_GENERATED_PUBLIC_PAGE\n"
            . "require(\$_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');\n"
            . "\$APPLICATION->SetPageProperty('topMenuSectionDir', '/ideabank/');\n"
            . "require(\$_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_after.php');\n"
            . "\$APPLICATION->SetTitle('" . addslashes($title) . "');\n"
            . "\$APPLICATION->IncludeComponent('" . addslashes($component) . "', '', []);\n"
            . "require(\$_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php');\n";
    }

    private function getSectionFileContents(): string
    {
        return "<?php\n// UDS_IDEABANK2_GENERATED_PUBLIC_PAGE\n\$sSectionName = 'Банк идей';\n";
    }

    private function getTopMenuContents(): string
    {
        return <<<'PHP'
<?php
// UDS_IDEABANK2_GENERATED_PUBLIC_PAGE
$aMenuLinks = [
    ['Банк идей', '/ideabank/', [], [], 'CModule::IncludeModule("uds.ideabank2")'],
    ['Список идей', '/ideabank/management.php', [], [], 'CModule::IncludeModule("uds.ideabank2")'],
    ['Подать идею', '/ideabank/ppu-form.php', [], [], 'CModule::IncludeModule("uds.ideabank2")'],
    ['Конкурсы', '/ideabank/contests.php', [], [], 'CModule::IncludeModule("uds.ideabank2")'],
    ['Новости', '/ideabank/news.php', [], [], 'CModule::IncludeModule("uds.ideabank2")'],
    ['Аллея славы', '/ideabank/hall-of-fame.php', [], [], 'CModule::IncludeModule("uds.ideabank2")'],
    ['Статистика', '/ideabank/stats.php', [], [], 'CModule::IncludeModule("uds.ideabank2")'],
    ['Документы', '/ideabank/docs.php', [], [], 'CModule::IncludeModule("uds.ideabank2")'],
];
PHP;
    }

    private function getLeftMenuContents(): string
    {
        return <<<'PHP'
<?php
// UDS_IDEABANK2_GENERATED_PUBLIC_PAGE
$aMenuLinks = [
    ['Банк идей', '/ideabank/', [], [], 'CModule::IncludeModule("uds.ideabank2")'],
    ['Список идей', '/ideabank/management.php', [], [], 'CModule::IncludeModule("uds.ideabank2")'],
    ['Подать идею', '/ideabank/ppu-form.php', [], [], 'CModule::IncludeModule("uds.ideabank2")'],
    ['Конкурсы', '/ideabank/contests.php', [], [], 'CModule::IncludeModule("uds.ideabank2")'],
    ['Новости', '/ideabank/news.php', [], [], 'CModule::IncludeModule("uds.ideabank2")'],
    ['Аллея славы', '/ideabank/hall-of-fame.php', [], [], 'CModule::IncludeModule("uds.ideabank2")'],
    ['Статистика', '/ideabank/stats.php', [], [], 'CModule::IncludeModule("uds.ideabank2")'],
    ['Документы', '/ideabank/docs.php', [], [], 'CModule::IncludeModule("uds.ideabank2")'],
];
PHP;
    }

    private function getDebugAuthContents(): string
    {
        return <<<'PHP'
<?php
// UDS_IDEABANK2_GENERATED_PUBLIC_PAGE

declare(strict_types=1);

define('NOT_CHECK_PERMISSIONS', true);
define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Uds\Ideabank2\Debug\DebugAuth;

$request = Application::getInstance()->getContext()->getRequest();

if (!Loader::includeModule('uds.ideabank2')) {
    http_response_code(503);
    echo 'Module uds.ideabank2 is not available';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
    return;
}

$error = '';
$requestedRedirect = DebugAuth::getSafeRedirect((string)$request->get('redirect'));

if ($request->isPost()) {
    $userId = (int)$request->getPost('user_id');
    $requestedRedirect = DebugAuth::getSafeRedirect((string)$request->getPost('redirect'));

    if (!check_bitrix_sessid()) {
        $error = 'Некорректная CSRF-сессия.';
    } elseif (!DebugAuth::canAuthorizeUser($userId)) {
        $error = 'Пользователь не разрешён для debug_auth или инструмент выключен.';
    } else {
        global $USER;
        $USER->Authorize($userId);
        LocalRedirect($requestedRedirect);
    }
}

$allowedUserIds = DebugAuth::getAllowedUserIds();
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ideabank debug auth</title>
</head>
<body style="margin:0; font-family: Arial, sans-serif; background:#f3f6fb; color:#172033;">
<div class="ideabank-debug-auth" style="max-width:760px; margin:24px auto; padding:20px; border:1px solid #d7d7d7; border-radius:8px; background:#fff;">
    <h1 style="margin-top:0;">Ideabank debug auth</h1>
    <p><strong>Статус:</strong> <?= htmlspecialcharsbx(DebugAuth::getStatusMessage()) ?></p>
    <p><strong>Whitelist user IDs:</strong> <?= htmlspecialcharsbx($allowedUserIds === [] ? 'пусто' : implode(', ', $allowedUserIds)) ?></p>

    <?php if ($error !== ''): ?>
        <div style="margin: 12px 0; padding: 12px; color: #8a1f11; background: #fde9e7; border: 1px solid #f5b7b1;">
            <?= htmlspecialcharsbx($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPageParam('', ['logout', 'login', 'USER_LOGIN'])) ?>">
        <?= bitrix_sessid_post() ?>
        <input type="hidden" name="redirect" value="<?= htmlspecialcharsbx($requestedRedirect) ?>">
        <label for="debug-auth-user-id">User ID из whitelist</label><br>
        <input id="debug-auth-user-id" type="number" name="user_id" min="1" value="<?= (int)($allowedUserIds[0] ?? 0) ?>" style="width: 180px; margin: 8px 0;">
        <br>
        <button type="submit" <?= DebugAuth::isEnabled() && DebugAuth::isDevEnvironment() ? '' : 'disabled' ?>>Авторизоваться</button>
    </form>

    <p style="margin-top: 16px; color: #666;">
        Инструмент предназначен только для локальных dev/test E2E-проверок. Он не принимает пароль,
        не авторизует пользователей вне whitelist и не выполняет redirect за пределы <code>/ideabank/</code>.
    </p>
</div>
</body>
</html>
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
PHP;
    }

    public function uninstallGeneratedPages(): void
    {
        $baseDir = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') . '/ideabank';
        if (!is_dir($baseDir)) {
            return;
        }

        $files = array_merge(
            array_keys($this->getPublicPageMap()),
            ['.section.php', '.top.menu.php', '.left.menu.php', 'debug_auth.php']
        );

        foreach ($files as $fileName) {
            $path = $baseDir . '/' . $fileName;
            if (!$this->isGeneratedPublicFile($path)) {
                continue;
            }

            @unlink($path);
        }

        $this->clearPublicMenuCache();
    }

    private function isGeneratedPublicFile(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }

        $contents = (string)file_get_contents($path, false, null, 0, 256);

        return strpos($contents, 'UDS_IDEABANK2_GENERATED_PUBLIC_PAGE') !== false;
    }

    private function clearPublicMenuCache(): void
    {
        if (function_exists('BXClearCache')) {
            BXClearCache(true, '/ideabank/');
            BXClearCache(true, '/menu/');
        }
    }
}
