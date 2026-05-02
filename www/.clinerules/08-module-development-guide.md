# Полная инструкция по созданию модулей для коробочной версии Bitrix24 на современном D7/ORM

## Назначение документа

Этот документ предназначен как автономная база знаний для локальной модели, которая должна уметь проектировать, писать, устанавливать, обновлять и сопровождать типовой каркас коробочного модуля Bitrix24 на D7/ORM и расширять его через миграции, события, агенты, права, административные страницы, компоненты и сервисный слой. Документ не должен восприниматься как разрешение генерировать сложные CRM, Bizproc, HL-блоки, REST-интеграции или кластерные сценарии без дополнительных требований: такие части нужно проектировать отдельно после уточнения версии коробки, редакции, включенных модулей и бизнес-процессов. В документе принят современный подход: бизнес-логика, доступ к данным, события, сервисы и UI строятся на D7/ORM, а исторические API старого ядра используются только там, где ядро Bitrix по-прежнему требует их для жизненного цикла модуля, например в классе установщика `CModule`, функциях `RegisterModule` и `UnRegisterModule`, а также в агентах `CAgent` ([CModule](https://dev.1c-bitrix.ru/api_help/main/reference/cmodule/index.php), [RegisterModule](https://dev.1c-bitrix.ru/api_help/main/functions/module/registermodule.php), [UnRegisterModule](https://dev.1c-bitrix.ru/api_help/main/functions/module/unregistermodule.php), [CAgent::AddAgent](https://dev.1c-bitrix.ru/api_help/main/reference/cagent/addagent.php)).

Коробочная версия Bitrix24 технически базируется на Bitrix Framework, поэтому правила модулей, D7, ORM, административных страниц, прав, событий и работы с базой берутся из документации Bitrix Framework и главного модуля ([установка и удаление модулей](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=3475), [описание и параметры модуля](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2824), [ORM-концепции](https://docs.1c-bitrix.ru/pages/orm/orm-concepts.html)).

## Матрица совместимости

Перед генерацией кода локальная модель должна запросить или определить версию PHP, версию модуля `main`, тип базы данных, редакцию коробочного Bitrix24 и список установленных модулей, потому что часть современных возможностей D7 и UI зависит от версии ядра и окружения. Для коробочной версии Bitrix24 официальные технические требования указывают PHP `8.2+`, рекомендуют MySQL `8.x` на Percona Server, а PostgreSQL доступен для соответствующих редакций уровня Pro/Enterprise и рекомендуется начиная с PostgreSQL `11.x` ([Технические требования для коробочной версии](https://helpdesk.bitrix24.ru/open/5825131/)).

| Область | Минимум для генерации без дополнительных проверок | Что проверять на целевой коробке |
| --- | --- | --- |
| PHP | `8.2+` для актуальной коробочной версии Bitrix24 | Фактическую версию PHP и включенные расширения на сервере ([Технические требования для коробочной версии](https://helpdesk.bitrix24.ru/open/5825131/)) |
| База данных | MySQL `8.x`/Percona Server для типового сценария | PostgreSQL использовать только если редакция и окружение коробки официально поддерживают этот режим ([Технические требования для коробочной версии](https://helpdesk.bitrix24.ru/open/5825131/)) |
| Модуль `main` | Версия должна быть известна перед генерацией UI и ORM-функций | `main.ui.filter` с типом поля `entity_selector` требует `main >= 21.200.0` ([main.ui.filter](https://dev.1c-bitrix.ru/api_d7/bitrix/main/systemcomponents/gridandfilter/mainuifilter.php)) |
| ORM-кеширование | Обычные `DataManager`, `getMap()`, `getList()` доступны в D7 | Отключение кешируемости через `isCacheable(): bool` доступно с `main 24.100.0` ([концепция сущности](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=4803&LESSON_PATH=3913.3516.5748.4803)) |
| Автозагрузка | `Loader::includeModule()` и автозагрузка D7 | `Loader::registerNamespace()` нужно проверять на целевой версии ядра, если модуль должен работать на старой коробке ([Loader](https://dev.1c-bitrix.ru/api_d7/bitrix/main/loader/index.php), [registerNamespace](https://dev.1c-bitrix.ru/api_d7/bitrix/main/loader/registernamespace.php)) |
| Редакция Bitrix24 | Коробочная версия на Bitrix Framework | Наличие модулей `crm`, `sale`, `iblock`, `bizproc`, `tasks` и других зависимостей перед регистрацией событий и использованием API |

Если целевая коробка старая или версия `main` неизвестна, инструкция должна использовать консервативный набор: `CModule` только для жизненного цикла установки, `Loader::includeModule()`, ORM `DataManager`, `EventManager`, `Option`, простые поля `main.ui.filter`, явную проверку существования классов и функций перед использованием новых возможностей.

## Главные правила современного модуля

Модуль должен жить в `/local/modules/{vendor}.{module}/`, а не в `/bitrix/modules/`, если это собственная разработка проекта, потому что `/bitrix` считается областью ядра и обновлений продукта; при этом официальные примеры часто показывают `/bitrix/modules/ID модуля`, так как описывают общий механизм ядра, применимый и к установленным системным модулям ([установка и удаление модулей](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=3475), [описание и параметры модуля](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2824)).

Идентификатор партнерского модуля обычно имеет формат `vendor.module`, а класс установщика в `install/index.php` должен называться как ID модуля с заменой точки на подчеркивание, например `vendor_module` для `vendor.module` ([описание и параметры модуля](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2824)).

Класс установщика должен наследоваться от `CModule`, потому что классы описания модулей системы должны наследоваться от `CModule`, иметь имя, соответствующее ID модуля, и располагаться в `/{bitrix|local}/modules/{ID}/install/index.php` ([CModule](https://dev.1c-bitrix.ru/api_help/main/reference/cmodule/index.php), [описание и параметры модуля](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2824)).

Вся прикладная логика должна быть вынесена из установщика в D7-классы внутри `lib/`, потому что `Bitrix\Main\Loader` подключает модули и классы, а ORM-сущности должны наследоваться от `Bitrix\Main\Entity\DataManager` или современного `Bitrix\Main\ORM\Data\DataManager` через алиас ([Loader](https://dev.1c-bitrix.ru/api_d7/bitrix/main/loader/index.php), [DataManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/datamanager/index.php), [ORM-концепции](https://docs.1c-bitrix.ru/pages/orm/orm-concepts.html)).

Для подключения модулей в D7 используется `Loader::includeModule('module.id')`, который является современной заменой `CModule::IncludeModule` для большинства модулей, кроме `main` и `fileman` ([Loader](https://dev.1c-bitrix.ru/api_d7/bitrix/main/loader/index.php), [includeModule](https://dev.1c-bitrix.ru/api_d7/bitrix/main/loader/includemodule.php)).

Для долгосрочной регистрации обработчиков событий нужно использовать `Bitrix\Main\EventManager::registerEventHandler()` или `registerEventHandlerCompatible()`, а не `RegisterModuleDependences`, потому что `EventManager` является D7-классом кратко- и долгосрочной регистрации обработчиков и официально указан как аналог старых функций событий ([EventManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/EventManager/index.php), [RegisterModuleDependences](https://www.dev.1c-bitrix.ru/api_help/main/functions/module/registermoduledependences.php)).

Для настроек модуля используйте `Bitrix\Main\Config\Option`, потому что `Option::set()` сохраняет параметр в базу, запускает событие `OnAfterSetOption` и является аналогом старых `COption::SetOptionInt` и `COption::SetOptionString` ([Option::set](https://dev.1c-bitrix.ru/api_d7/bitrix/main/config/option/set.php), [Config](https://dev.1c-bitrix.ru/api_d7/bitrix/main/config/index.php)).

Для ошибок в D7 следует использовать `Result`, `Error`, `ErrorCollection` и исключения, потому что в D7 обработка ошибок производится через механизм исключений PHP, а результаты ORM-операций возвращают объекты результата с `isSuccess()`, `getErrorMessages()` и `getErrors()` ([SystemException](https://dev.1c-bitrix.ru/api_d7/bitrix/main/systemexception/index.php), [операции с сущностями](https://docs.1c-bitrix.ru/pages/orm/entity-operations.html), [Result::getErrors](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/result/geterrors.php)).

## Рекомендуемая структура модуля

Минимальная структура современного модуля должна отделять установочные файлы, D7-классы, административные точки входа, компоненты, языковые файлы, миграции и публичные ресурсы. В официальной документации обязательным файлом управления модулем является `install/index.php`, а `options.php` подключается страницей настроек модулей для управления параметрами и правами ([описание и параметры модуля](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2824)).

```text
/local/modules/vendor.module/
  include.php
  options.php
  default_option.php
  install/
    index.php
    version.php
    step.php
    unstep.php
    db/
      mysql/
        install.sql
        uninstall.sql
    admin/
      vendor_module_entities.php
      vendor_module_entity_edit.php
    components/
      vendor/
        module.list/
          class.php
          templates/.default/template.php
    js/
      vendor.module/
        script.js
    css/
      vendor.module/
        style.css
  lang/
    ru/
      install/index.php
      options.php
      lib/entity/itemtable.php
  lib/
    Entity/
      ItemTable.php
    Service/
      ItemService.php
    Repository/
      ItemRepository.php
    Event/
      MainHandler.php
      SaleHandler.php
    Agent/
      SyncAgent.php
    Controller/
      AjaxController.php
    Access/
      Permission.php
    Migration/
      Version202604290001.php
```

`include.php` должен оставаться легким: он не должен выполнять тяжелые запросы, изменять данные или регистрировать обработчики на каждом хите, а должен только подключать автозагрузку или совместимость, потому что `Loader::includeModule()` подключает файл модуля и должен быть безопасен для частого вызова ([Loader](https://dev.1c-bitrix.ru/api_d7/bitrix/main/loader/index.php), [CModule::IncludeModule](https://dev.1c-bitrix.ru/api_help/main/reference/cmodule/includemodule.php)).

Классы в `lib/` должны соответствовать неймспейсу модуля, например `Vendor\Module\Entity\ItemTable`, и подключаться через D7-автозагрузку; `Loader::registerAutoLoadClasses()` регистрирует классы для автозагрузки, а `Loader::registerNamespace()` регистрирует пространство имен ([registerAutoLoadClasses](https://dev.1c-bitrix.ru/api_d7/bitrix/main/loader/registerautoloadclasses.php), [registerNamespace](https://dev.1c-bitrix.ru/api_d7/bitrix/main/loader/registernamespace.php)).

Часто используемые классы можно явно зарегистрировать через `Loader::registerAutoLoadClasses()`, а редко используемые классы Bitrix может найти и подключить при первом использовании, что прямо указано в документации по автозагрузке ([registerAutoLoadClasses](https://dev.1c-bitrix.ru/api_d7/bitrix/main/loader/registerautoloadclasses.php)).

```php
<?php
// /local/modules/vendor.module/include.php

use Bitrix\Main\Loader;

Loader::registerNamespace(
    'Vendor\\Module',
    Loader::getDocumentRoot() . '/local/modules/vendor.module/lib'
);
```

## Установщик модуля

Инсталляция модуля выполняется в административном интерфейсе на странице «Настройки > Настройки продукта > Модули» нажатием кнопки «Установить», после чего вызывается `DoInstall()` класса из `install/index.php` ([установка и удаление модулей](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=3475), [описание и параметры модуля](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2824)).

Деинсталляция выполняется нажатием кнопки «Удалить», после чего вызывается `DoUninstall()` класса из того же `install/index.php` ([установка и удаление модулей](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=3475), [описание и параметры модуля](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2824)).

Класс установщика должен содержать обязательные свойства `MODULE_ID`, `MODULE_VERSION`, `MODULE_VERSION_DATE`, `MODULE_NAME`, `MODULE_DESCRIPTION`, а при наличии собственного списка прав также `MODULE_GROUP_RIGHTS = 'Y'` ([CModule](https://dev.1c-bitrix.ru/api_help/main/reference/cmodule/index.php), [описание и параметры модуля](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2824)).

Версия модуля хранится в `install/version.php` в массиве `$arModuleVersion`, где `VERSION` задается в формате `XX.XX.XX`, а `VERSION_DATE` задается строкой в формате `YYYY-MM-DD HH:MI:SS` ([описание и параметры модуля](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2824)).

Регистрация модуля через `RegisterModule($moduleId)` является неотъемлемой частью инсталляции, а `UnRegisterModule($moduleId)` удаляет регистрационную запись и настройки модуля из базы при деинсталляции ([RegisterModule](https://dev.1c-bitrix.ru/api_help/main/functions/module/registermodule.php), [UnRegisterModule](https://dev.1c-bitrix.ru/api_help/main/functions/module/unregistermodule.php)).

Ниже показан боевой шаблон установщика, в котором legacy-API используется только как обязательная точка жизненного цикла модуля, вся логика установки делегируется отдельным методам, а частичная установка откатывается через `rollbackInstall()`. Это важно, потому что `RegisterModule()` регистрирует модуль, `InstallDB()`, `InstallEvents()`, `InstallAgents()` и `InstallFiles()` меняют разные подсистемы, и ошибка на середине процесса без отката оставит систему в неопределенном состоянии ([RegisterModule](https://dev.1c-bitrix.ru/api_help/main/functions/module/registermodule.php), [UnRegisterModule](https://dev.1c-bitrix.ru/api_help/main/functions/module/unregistermodule.php), [EventManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/EventManager/index.php), [CAgent::AddAgent](https://dev.1c-bitrix.ru/api_help/main/reference/cagent/addagent.php)).

```php
<?php
// /local/modules/vendor.module/install/index.php

use Bitrix\Main\Application;
use Bitrix\Main\AccessDeniedException;
use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;
use Bitrix\Main\IO\Directory;
use Bitrix\Main\IO\File;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\NotSupportedException;
use Bitrix\Main\SystemException;

Loc::loadMessages(__FILE__);

class vendor_module extends CModule
{
    public $MODULE_ID = 'vendor.module';
    public $MODULE_GROUP_RIGHTS = 'Y';
    public $errors = [];
    private $moduleRegistered = false;
    private $createdTables = [];
    private $eventsInstalled = false;
    private $agentsInstalled = false;
    private $filesInstalled = false;

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';

        $this->MODULE_VERSION = $arModuleVersion['VERSION'] ?? '1.0.0';
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'] ?? date('Y-m-d H:i:s');
        $this->MODULE_NAME = Loc::getMessage('VENDOR_MODULE_NAME') ?: 'Vendor Module';
        $this->MODULE_DESCRIPTION = Loc::getMessage('VENDOR_MODULE_DESCRIPTION') ?: 'Custom D7 module';
    }

    public function DoInstall(): void
    {
        global $APPLICATION;
        $request = Application::getInstance()->getContext()->getRequest();

        try {
            if (!$this->checkRights()) {
                throw new AccessDeniedException(Loc::getMessage('VENDOR_MODULE_ACCESS_DENIED') ?: 'Access denied');
            }

            $this->checkRequiredModules();

            RegisterModule($this->MODULE_ID);
            $this->moduleRegistered = true;

            if ($request->getPost('restore_options') === 'Y') {
                // Здесь restore безопасен только для Option.
                // Если backup восстанавливает строки таблиц, сначала выполните InstallDB() и миграции.
                $this->restoreOptions();
            }

            $this->InstallDB();
            $this->InstallEvents();
            $this->InstallAgents();
            $this->InstallFiles();
        } catch (\Throwable $e) {
            $this->rollbackInstall();
            $APPLICATION->ThrowException($e->getMessage());
            return;
        }

        $APPLICATION->IncludeAdminFile(
            Loc::getMessage('VENDOR_MODULE_INSTALL_TITLE'),
            __DIR__ . '/step.php'
        );
    }

    public function DoUninstall(): void
    {
        global $APPLICATION;

        $request = Application::getInstance()->getContext()->getRequest();
        $saveTables = $request->getPost('save_tables') === 'Y';
        $saveOptions = $request->getPost('save_options') === 'Y';
        $saveFiles = $request->getPost('save_files') === 'Y';
        $backupRequired = $request->getPost('backup_required') === 'Y';

        if (!$this->checkRights()) {
            $APPLICATION->ThrowException(Loc::getMessage('VENDOR_MODULE_ACCESS_DENIED') ?: 'Access denied');
            return;
        }

        try {
            if ($saveOptions) {
                try {
                    $this->backupOptions();
                } catch (\Throwable $e) {
                    $this->logRollbackError($e, 'backup_options');
                    if ($backupRequired) {
                        throw $e;
                    }
                }
            }

            $this->UnInstallAgents();
            $this->UnInstallEvents();

            if (!$saveFiles) {
                $this->UnInstallFiles();
            }

            if (!$saveTables) {
                $this->UnInstallDB();
            }

            UnRegisterModule($this->MODULE_ID);
        } catch (\Throwable $e) {
            $this->logRollbackError($e, 'uninstall');
            $APPLICATION->ThrowException($e->getMessage());
            return;
        }

        $APPLICATION->IncludeAdminFile(
            Loc::getMessage('VENDOR_MODULE_UNINSTALL_TITLE'),
            __DIR__ . '/unstep.php'
        );
    }

    public function InstallDB(): bool
    {
        $connection = Application::getConnection();
        $sqlHelper = $connection->getSqlHelper();
        $type = $connection->getType();

        $tableName = 'b_vendor_module_item';
        if (!$connection->isTableExists($tableName)) {
            if ($type === 'mysql') {
                $connection->queryExecute("
                    CREATE TABLE {$sqlHelper->quote($tableName)} (
                        ID int NOT NULL AUTO_INCREMENT,
                        UF_XML_ID varchar(100) NOT NULL,
                        NAME varchar(255) NOT NULL,
                        ACTIVE char(1) NOT NULL DEFAULT 'Y',
                        CREATED_AT datetime NOT NULL,
                        UPDATED_AT datetime NULL,
                        PRIMARY KEY (ID),
                        UNIQUE KEY UX_VENDOR_MODULE_ITEM_XML_ID (UF_XML_ID)
                    )
                ");
            } elseif ($type === 'pgsql') {
                $connection->queryExecute("
                    CREATE TABLE {$sqlHelper->quote($tableName)} (
                        ID serial PRIMARY KEY,
                        UF_XML_ID varchar(100) NOT NULL,
                        NAME varchar(255) NOT NULL,
                        ACTIVE char(1) NOT NULL DEFAULT 'Y',
                        CREATED_AT timestamp NOT NULL,
                        UPDATED_AT timestamp NULL,
                        CONSTRAINT UX_VENDOR_MODULE_ITEM_XML_ID UNIQUE (UF_XML_ID)
                    )
                ");
            } else {
                throw new NotSupportedException('Unsupported DB type: ' . $type);
            }

            $this->createdTables[] = $tableName;
        }

        return true;
    }

    public function UnInstallDB(bool $onlyCreatedDuringInstall = false): bool
    {
        $connection = Application::getConnection();
        $tables = $onlyCreatedDuringInstall ? $this->createdTables : ['b_vendor_module_item'];

        foreach ($tables as $tableName) {
            if ($connection->isTableExists($tableName)) {
                $connection->dropTable($tableName);
            }
        }

        return true;
    }

    public function InstallEvents(): bool
    {
        $this->UnInstallEvents();

        EventManager::getInstance()->registerEventHandlerCompatible(
            'main',
            'OnAfterUserAdd',
            $this->MODULE_ID,
            '\\Vendor\\Module\\Event\\MainHandler',
            'onAfterUserAdd'
        );
        $this->eventsInstalled = true;
        return true;
    }

    public function UnInstallEvents(): bool
    {
        EventManager::getInstance()->unRegisterEventHandler(
            'main',
            'OnAfterUserAdd',
            $this->MODULE_ID,
            '\\Vendor\\Module\\Event\\MainHandler',
            'onAfterUserAdd'
        );
        return true;
    }

    public function InstallAgents(): bool
    {
        $this->UnInstallAgents();

        $agentId = CAgent::AddAgent(
            '\\Vendor\\Module\\Agent\\SyncAgent::run();',
            $this->MODULE_ID,
            'N',
            3600
        );

        if (!$agentId) {
            throw new SystemException('Failed to register agent');
        }

        $this->agentsInstalled = true;
        return true;
    }

    public function UnInstallAgents(): bool
    {
        CAgent::RemoveModuleAgents($this->MODULE_ID);
        return true;
    }

    public function InstallFiles(): bool
    {
        $source = __DIR__ . '/admin';
        $target = Application::getDocumentRoot() . '/bitrix/admin';

        $this->assertAdminFilesHaveModulePrefix($source);

        $copied = CopyDirFiles($source, $target, true, true);
        if (!$copied) {
            // CopyDirFiles() может частично скопировать файлы до возврата false.
            // Поэтому очищаем возможные остатки сразу, не дожидаясь rollback-флага.
            $this->UnInstallFiles();
            throw new SystemException('Failed to copy admin files');
        }

        $this->filesInstalled = true;
        return true;
    }

    public function UnInstallFiles(): bool
    {
        $source = __DIR__ . '/admin';
        $target = Application::getDocumentRoot() . '/bitrix/admin';

        DeleteDirFiles($source, $target);

        $remaining = $this->getRemainingTopLevelInstallFiles($source, $target);
        if ($remaining !== []) {
            $this->logRollbackError(
                new SystemException('Failed to delete admin files: ' . implode(', ', $remaining)),
                'files'
            );
        }

        return true;
    }

    private function assertAdminFilesHaveModulePrefix(string $sourceDir): void
    {
        if (!is_dir($sourceDir)) {
            return;
        }

        $prefix = str_replace('.', '_', $this->MODULE_ID) . '_';
        foreach (new \DirectoryIterator($sourceDir) as $file) {
            if ($file->isDot() || $file->isDir()) {
                continue;
            }

            if (!str_starts_with($file->getFilename(), $prefix)) {
                throw new SystemException('Admin file must use module prefix: ' . $file->getFilename());
            }
        }
    }

    private function getRemainingTopLevelInstallFiles(string $sourceDir, string $targetDir): array
    {
        if (!is_dir($sourceDir) || !is_dir($targetDir)) {
            return [];
        }

        $remaining = [];
        foreach (new \DirectoryIterator($sourceDir) as $file) {
            if ($file->isDot() || $file->isDir()) {
                continue;
            }

            $targetFile = $targetDir . '/' . $file->getFilename();
            if (is_file($targetFile)) {
                $remaining[] = $targetFile;
            }
        }

        return $remaining;
    }

    public function GetModuleRightList(): array
    {
        return [
            'reference_id' => ['D', 'R', 'W'],
            'reference' => [
                '[D] ' . Loc::getMessage('VENDOR_MODULE_RIGHT_DENIED'),
                '[R] ' . Loc::getMessage('VENDOR_MODULE_RIGHT_READ'),
                '[W] ' . Loc::getMessage('VENDOR_MODULE_RIGHT_WRITE'),
            ],
        ];
    }

    private function checkRights(): bool
    {
        global $APPLICATION;
        return $APPLICATION->GetGroupRight('main') >= 'W';
    }

    private function checkRequiredModules(): void
    {
        $required = [
            // 'iblock',
            // 'crm',
            // 'sale',
        ];

        foreach ($required as $moduleId) {
            if (!Loader::includeModule($moduleId)) {
                throw new SystemException("Required module {$moduleId} is not installed");
            }
        }
    }

    private function checkOptionalModule(string $moduleId): bool
    {
        return Loader::includeModule($moduleId);
    }

    private function rollbackInstall(): void
    {
        if ($this->agentsInstalled) {
            try {
                $this->UnInstallAgents();
            } catch (\Throwable $e) {
                $this->logRollbackError($e, 'agents');
            }
        }

        if ($this->eventsInstalled) {
            try {
                $this->UnInstallEvents();
            } catch (\Throwable $e) {
                $this->logRollbackError($e, 'events');
            }
        }

        if ($this->filesInstalled) {
            try {
                $this->UnInstallFiles();
            } catch (\Throwable $e) {
                $this->logRollbackError($e, 'files');
            }
        }

        try {
            $this->UnInstallDB(true);
        } catch (\Throwable $e) {
            $this->logRollbackError($e, 'database');
        }

        if ($this->moduleRegistered) {
            try {
                UnRegisterModule($this->MODULE_ID);
            } catch (\Throwable $e) {
                $this->logRollbackError($e, 'module_registration');
            }
        }
    }

    private function backupOptions(): void
    {
        $options = [
            'sync_enabled' => Option::get($this->MODULE_ID, 'sync_enabled', 'N'),
            'sync_limit' => Option::get($this->MODULE_ID, 'sync_limit', '100'),
        ];

        $payload = [
            'module_id' => $this->MODULE_ID,
            'module_version' => $this->MODULE_VERSION,
            'created_at' => date('c'),
            'schema_version' => 1,
            'options' => $options,
        ];

        $backupDir = dirname(Application::getDocumentRoot()) . '/bitrix-module-backups/' . str_replace('.', '_', $this->MODULE_ID);
        if (!Directory::isDirectoryExists($backupDir) && !Directory::createDirectory($backupDir)) {
            throw new SystemException('Failed to create backup directory');
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $path = $backupDir . '/options-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.json';
        $result = File::putFileContents($path, $json);
        if ($result === false) {
            throw new SystemException('Failed to write options backup');
        }

        $this->rotateOptionBackups($backupDir, 10, 30);
    }

    private function rotateOptionBackups(string $backupDir, int $maxFiles = 10, int $maxAgeDays = 30): void
    {
        $files = glob($backupDir . '/options-*.json') ?: [];
        if (!$files) {
            return;
        }

        rsort($files);
        $delete = array_slice($files, $maxFiles);
        $threshold = time() - ($maxAgeDays * 86400);

        foreach (array_slice($files, 0, $maxFiles) as $file) {
            $modifiedAt = is_file($file) ? filemtime($file) : false;
            if ($modifiedAt !== false && $modifiedAt < $threshold) {
                $delete[] = $file;
            }
        }

        foreach (array_unique($delete) as $file) {
            if (is_file($file) && !@unlink($file)) {
                $this->logRollbackError(
                    new SystemException('Failed to delete old options backup: ' . $file),
                    'backup_rotation'
                );
            }
        }
    }

    private function restoreOptions(): void
    {
        $backupDir = dirname(Application::getDocumentRoot()) . '/bitrix-module-backups/' . str_replace('.', '_', $this->MODULE_ID);
        $files = glob($backupDir . '/options-*.json') ?: [];

        if (!$files) {
            return;
        }

        rsort($files);
        $contents = file_get_contents($files[0]);
        if ($contents === false) {
            throw new SystemException('Failed to read options backup');
        }

        $backup = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($backup)) {
            throw new SystemException('Invalid options backup format');
        }

        if (($backup['module_id'] ?? null) !== $this->MODULE_ID) {
            throw new SystemException('Options backup belongs to another module');
        }

        if ((int)($backup['schema_version'] ?? 0) !== 1) {
            throw new SystemException('Unsupported options backup schema version');
        }

        if (($backup['module_version'] ?? null) !== $this->MODULE_VERSION) {
            $this->logRollbackError(
                new SystemException('Options backup module version differs: ' . (string)($backup['module_version'] ?? 'unknown')),
                'restore_options'
            );
            // Для сложных настроек здесь должен быть явный мигратор backup-формата
            // или отказ от восстановления. Для простых Option допустим осознанный log-and-continue.
        }

        $options = $backup['options'] ?? null;
        if (!is_array($options)) {
            throw new SystemException('Invalid options backup format');
        }

        $allowed = ['sync_enabled', 'sync_limit'];

        foreach ($allowed as $name) {
            if (array_key_exists($name, $options)) {
                Option::set($this->MODULE_ID, $name, (string)$options[$name]);
            }
        }
    }

    private function logRollbackError(\Throwable $exception, string $stage): void
    {
        $message = sprintf(
            "[%s] rollback:%s %s: %s\\n%s\\n",
            date('c'),
            $stage,
            get_class($exception),
            $this->maskLogSecrets($exception->getMessage()),
            $this->maskLogSecrets($exception->getTraceAsString())
        );

        $path = dirname(Application::getDocumentRoot()) . '/bitrix-module-install-rollback.log';
        if (!@error_log($message, 3, $path)) {
            @error_log($message);
        }
    }

    private function maskLogSecrets(string $value): string
    {
        $patterns = [
            '/(password|passwd|pwd|token|access_token|refresh_token|authorization|cookie|secret|webhook)(\s*[=:]\s*)([^&\s;]+)/i' => '$1$2***',
            '/([?&](?:password|passwd|pwd|token|access_token|refresh_token|code|client_secret|secret|auth)=)[^&\s]+/i' => '$1***',
            '/(Bearer\s+)[A-Za-z0-9._\-]+/i' => '$1***',
            '/(Basic\s+)[A-Za-z0-9+\/=]+/i' => '$1***',
            '#(https?://[^/\s:@]+:)[^@\s]+(@)#i' => '$1***$2',
            '#https?://[^\s]*(?:webhook|hook)[^\s]*#i' => '[masked-webhook-url]',
        ];

        return preg_replace(array_keys($patterns), array_values($patterns), $value) ?? $value;
    }
}
```


При создании таблиц через SQL следует использовать `Application::getConnection()`, `getType()`, `isTableExists()` и `queryExecute()`, потому что `getType()` возвращает тип БД, `isTableExists()` проверяет существование таблицы, а `queryExecute()` выполняет SQL без возврата результата для операций вроде `INSERT`, `UPDATE` и `DELETE` ([Connection](https://dev.1c-bitrix.ru/api_d7/bitrix/main/db/connection/index.php), [Connection::isTableExists](https://dev.1c-bitrix.ru/api_d7/bitrix/main/db/connection/istableexists.php), [Connection::queryExecute](https://dev.1c-bitrix.ru/api_d7/bitrix/main/db/connection/queryexecute.php)).

Если таблица описана ORM-классом, можно получить SQL создания таблицы через `TableClass::getEntity()->compileDbTableStructureDump()`, потому что ORM умеет генерировать структуру таблицы по карте сущности ([ORM-концепции](https://docs.1c-bitrix.ru/pages/orm/orm-concepts.html), [концепция сущности](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=4803&LESSON_PATH=3913.3516.5748.4803)).

Пример `InstallDB()` нельзя считать автоматически переносимым между СУБД. MySQL/Percona-ветка использует MySQL-синтаксис `AUTO_INCREMENT` и `UNIQUE KEY`, PostgreSQL-ветка должна иметь отдельный DDL или отдельные файлы вроде `install/db/postgresql/install.sql`, а любая новая СУБД должна явно отвергаться через `NotSupportedException`, потому что `NotSupportedException` предназначен для функциональности, которая не поддерживается в принципе ([Connection](https://dev.1c-bitrix.ru/api_d7/bitrix/main/db/connection/index.php), [NotSupportedException](https://dev.1c-bitrix.ru/api_d7/bitrix/main/notsupportedexception/index.php), [Технические требования для коробочной версии](https://helpdesk.bitrix24.ru/open/5825131/)).

В PostgreSQL-ветке `serial` оставлен как простой совместимый пример. В новых схемах можно использовать `GENERATED BY DEFAULT AS IDENTITY`, но только если это подтверждено целевой версией PostgreSQL и Bitrix-окружением; локальная модель не должна автоматически заменять `serial` на identity без проверки целевой коробки.

`rollbackInstall()` должен быть идемпотентным: каждый шаг отката обязан спокойно переживать ситуацию, когда соответствующий ресурс еще не был создан или уже был удален. Минимальный откат снимает агентов, снимает долгосрочные события, удаляет скопированные файлы, удаляет созданные таблицы при установочной ошибке и вызывает `UnRegisterModule()`, потому что `UnRegisterModule()` удаляет регистрационную запись и все настройки модуля из базы данных ([UnRegisterModule](https://dev.1c-bitrix.ru/api_help/main/functions/module/unregistermodule.php)).

Ошибки rollback нельзя гасить молча. Их нужно логировать через метод, который не зависит от успешно установленного модуля, ORM-таблиц модуля или его автозагрузки, потому что откат выполняется в состоянии частичной установки; поэтому безопасный минимум — `error_log()` в защищенный путь вне web root с fallback в системный PHP error log. Такой rollback-лог является аварийным установочным логом и не заменяет штатный логирующий слой модуля для агентов, миграций, интеграций и бизнес-операций.

При обычной деинсталляции нельзя смешивать все данные в один флаг `savedata`. Лучше разделять `save_tables=Y`, `save_options=Y` и `save_files=Y`: первый флаг оставляет собственные таблицы модуля, второй требует заранее экспортировать настройки из `Option`, а третий обычно не нужен и может применяться только к публичным файлам пользователя. Даже если таблицы сохраняются, вызов `UnRegisterModule($this->MODULE_ID)` остается нормальной частью деинсталляции, но он удаляет не только регистрацию, а также настройки модуля из базы, поэтому сохранение `Option` нужно проектировать отдельно ([UnRegisterModule](https://dev.1c-bitrix.ru/api_help/main/functions/module/unregistermodule.php), [Option::set](https://dev.1c-bitrix.ru/api_d7/bitrix/main/config/option/set.php)).

`backupOptions()` в шаблоне — минимальный пример для понятного жизненного цикла, а не универсальное хранилище production-секретов. В промышленном модуле backup должен быть версионированным payload со служебными полями `module_id`, `module_version`, `created_at`, `schema_version` и вложенным объектом `options`; список экспортируемых опций должен быть whitelist, путь резервной копии должен находиться вне web root, создание директории и запись файла должны проверяться, файл должен быть защищен правами ОС и политикой хранения, а секреты лучше хранить во внешнем vault или в зашифрованной таблице, а не в обычных `Option`.

Backup опций при удалении не означает автоматическое восстановление. Если модуль должен восстанавливать настройки при повторной установке, нужно явно реализовать `restoreOptions()` и запускать его только по явному opt-in выбору администратора, например по `restore_options=Y`; локальная модель не должна автоматически поднимать последний backup без подтверждения, потому что файл может быть старым, относиться к другой конфигурации или содержать настройки, которые больше не соответствуют текущей версии модуля.

`restoreOptions()` обязан валидировать не только синтаксис JSON, но и форму данных: результат `json_decode()` должен быть массивом, `module_id` должен соответствовать текущему модулю, `schema_version` должен поддерживаться текущим кодом, а поле `options` должно быть массивом. По умолчанию несовпадение `module_version` должно прерывать restore или проходить через явный совместимый мигратор backup-формата; режим log-and-continue допустим только для простых `Option`, если это осознанно указано в модуле и проверено тестами.

Порядок восстановления зависит от содержимого backup. Если `restoreOptions()` восстанавливает только значения `Option`, его можно выполнять после `RegisterModule()`, но до логики, которая читает эти настройки; если restore восстанавливает строки собственных таблиц или связанные данные, сначала нужно выполнить `InstallDB()` и применить миграции, а уже потом импортировать данные в созданную схему.

Backup-файлы нужно ротировать и создавать с уникальным именем. Минимальное правило — хранить не более N последних файлов и дополнительно удалять backup старше X дней; имя файла должно включать не только timestamp до секунды, но и микросекунды или короткий случайный суффикс, иначе два удаления в одну секунду могут перезаписать один и тот же backup.

Rollback-лог должен маскировать секреты до записи. Даже если лог пишется только в защищенный путь вне web root, сообщение исключения или stack trace могут содержать DSN, токены, cookie, OAuth-коды, webhook-URL, заголовки авторизации или персональные данные, поэтому `logRollbackError()` должен применять маскирование к сообщению и трассировке перед вызовом `error_log()`.

`DoUninstall()` должен быть защищен так же, как `DoInstall()`: сначала проверка прав, затем контролируемый `try/catch`, логирование ошибок и понятная политика для backup. Если backup обязателен, ошибка `backupOptions()` должна прерывать удаление; если backup работает в режиме best-effort, ошибку нужно залогировать и продолжить снятие агентов, событий, файлов и таблиц.

Результат `CopyDirFiles()` в установщике нужно проверять так же строго, как результат регистрации агента: функция возвращает `true` при успешном копировании и `false` при ошибке, поэтому `InstallFiles()` должен бросать исключение и запускать rollback, если административные или публичные файлы не скопированы ([CopyDirFiles](https://dev.1c-bitrix.ru/api_help/main/functions/file/copydirfiles.php)). Если `CopyDirFiles()` вернул `false`, нельзя полагаться только на флаг `filesInstalled`, потому что часть файлов уже могла быть скопирована; обработчик ошибки должен сразу вызвать `UnInstallFiles()` или вести отдельные флаги по каждому каталогу.

Административные файлы модуля должны иметь уникальный префикс, например `vendor_module_*`. Это важно потому, что `DeleteDirFiles()` удаляет в целевом каталоге файлы с такими же именами, какие есть в исходном каталоге; неуникальные имена могут привести к удалению чужого административного файла при деинсталляции.

Если модуль содержит `install/components`, `install/js`, `install/css`, публичные страницы или другие ресурсы, их копирование и удаление должны проверяться так же строго, как копирование `install/admin`. Минимальный шаблон может показывать только admin-файлы, но локальная модель не должна считать остальные каталоги необязательными для контроля ошибок: каждый `CopyDirFiles()` должен проверять результат, каждый rollback должен удалять только те ресурсы, которые действительно были скопированы, а после `DeleteDirFiles()` нужно проверять файловую систему и логировать оставшиеся файлы, чтобы проблемы прав ФС не оставались незаметными. `DeleteDirFiles()` удаляет из целевого каталога файлы, имена которых есть в исходном каталоге, и не работает рекурсивно, поэтому для деревьев каталогов нужны отдельные проверки или `DeleteDirFilesEx()` там, где допустимо рекурсивное удаление ([DeleteDirFiles](https://dev.1c-bitrix.ru/api_help/main/functions/file/deletedirfiles.php), [DeleteDirFilesEx](https://dev.1c-bitrix.ru/api_help/main/functions/file/deletedirfilesex.php)).

Зависимости нужно проверять до регистрации модуля и до регистрации событий. `Loader::includeModule($moduleId)` возвращает `true` при успешном подключении и `false` при неуспешном, поэтому обязательные модули должны приводить к исключению, а события `crm`, `sale`, `iblock`, `bizproc`, `tasks` и других интеграционных модулей нельзя регистрировать, если соответствующий модуль не установлен ([includeModule](https://dev.1c-bitrix.ru/api_d7/bitrix/main/loader/includemodule.php), [SystemException](https://dev.1c-bitrix.ru/api_d7/bitrix/main/systemexception/index.php)).

## ORM и DataManager

Класс, отвечающий за доступ к таблице БД, должен наследоваться от `Bitrix\Main\Entity\DataManager`, а этот класс является алиасом `Bitrix\Main\ORM\Data\DataManager` ([DataManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/datamanager/index.php), [ORM-концепции](https://docs.1c-bitrix.ru/pages/orm/orm-concepts.html)).

ORM-класс должен переопределять `getTableName()` для имени таблицы и `getMap()` для массива полей сущности, а имя класса сущности рекомендуется завершать словом `Table`, например `ItemTable` ([DataManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/datamanager/index.php), [ORM-концепции](https://docs.1c-bitrix.ru/pages/orm/orm-concepts.html)).

Поля в `getMap()` рекомендуется называть в верхнем регистре, а имена полей должны быть уникальными в рамках сущности ([концепция сущности](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=4803&LESSON_PATH=3913.3516.5748.4803), [ORM-концепции](https://docs.1c-bitrix.ru/pages/orm/orm-concepts.html)).

Метод `getMap()` является первичной конфигурацией сущности, а действительный список полей нужно получать через `TableClass::getEntity()->getFields()` ([концепция сущности](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=4803&LESSON_PATH=3913.3516.5748.4803)).

Если сущность использует пользовательские поля, нужно определить `getUfId()`, потому что UF-поля не требуют ручного описания в `getMap()` ([ORM-концепции](https://docs.1c-bitrix.ru/pages/orm/orm-concepts.html), [концепция сущности](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=4803&LESSON_PATH=3913.3516.5748.4803)).

```php
<?php
// /local/modules/vendor.module/lib/Entity/ItemTable.php

namespace Vendor\Module\Entity;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields;
use Bitrix\Main\ORM\Fields\Validators;
use Bitrix\Main\Type\DateTime;

class ItemTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'b_vendor_module_item';
    }

    public static function getUfId(): string
    {
        return 'VENDOR_MODULE_ITEM';
    }

    public static function getMap(): array
    {
        return [
            (new Fields\IntegerField('ID'))
                ->configurePrimary(true)
                ->configureAutocomplete(true),

            (new Fields\StringField('UF_XML_ID'))
                ->configureRequired(true)
                ->configureSize(100)
                ->addValidator(new Validators\LengthValidator(1, 100)),

            (new Fields\StringField('NAME'))
                ->configureRequired(true)
                ->configureSize(255),

            (new Fields\BooleanField('ACTIVE'))
                ->configureStorageValues('N', 'Y')
                ->configureDefaultValue(true),

            (new Fields\DatetimeField('CREATED_AT'))
                ->configureRequired(true)
                ->configureDefaultValue(static fn() => new DateTime()),

            (new Fields\DatetimeField('UPDATED_AT')),
        ];
    }
}
```

Современные ORM-поля включают `IntegerField`, `FloatField`, `DecimalField`, `StringField`, `TextField`, `DateField`, `DateTimeField`, `BooleanField`, `EnumField`, `ArrayField`, `CryptoField` и `SecretField`, а настройки полей могут задаваться параметрами или fluent-методами `configure*` ([ORM-концепции](https://docs.1c-bitrix.ru/pages/orm/orm-concepts.html)).

Для первичного ключа используется `primary => true` или `configurePrimary(true)`, для автоинкремента используется `autocomplete => true` или `configureAutocomplete(true)`, а обязательность задается `required => true` или `configureRequired(true)` ([ORM-концепции](https://docs.1c-bitrix.ru/pages/orm/orm-concepts.html), [концепция сущности](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=4803&LESSON_PATH=3913.3516.5748.4803)).

Для `BooleanField` нужно задавать значения хранения, например `['N', 'Y']`, где первый элемент соответствует `false`, а второй соответствует `true` ([ORM-концепции](https://docs.1c-bitrix.ru/pages/orm/orm-concepts.html), [концепция сущности](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=4803&LESSON_PATH=3913.3516.5748.4803)).

Для `DateField` и `DateTimeField` при записи значений нужно использовать `Bitrix\Main\Type\Date` и `Bitrix\Main\Type\DateTime`, а не строки произвольного формата ([операции с сущностями](https://docs.1c-bitrix.ru/pages/orm/entity-operations.html), [операции с сущностями в курсе](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2244&LESSON_PATH=3913.5062.5748.2244)).

Валидация поля задается через callback `validation` или валидаторы, а типовые валидаторы включают `RegExp`, `Length`, `Range` и `Unique` ([операции с сущностями](https://docs.1c-bitrix.ru/pages/orm/entity-operations.html), [операции с сущностями в курсе](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2244&LESSON_PATH=3913.5062.5748.2244)).

Стандартные коды ошибок ORM-валидации включают `BX_INVALID_VALUE` для ошибки валидатора и `BX_EMPTY_REQUIRED` для незаполненного обязательного поля ([операции с сущностями в курсе](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2244&LESSON_PATH=3913.5062.5748.2244)).

## CRUD-операции ORM

`DataManager::add()` добавляет запись и возвращает `AddResult`, `update()` обновляет запись по первичному ключу и возвращает `UpdateResult`, а `delete()` удаляет запись по первичному ключу ([DataManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/datamanager/index.php), [операции с сущностями](https://docs.1c-bitrix.ru/pages/orm/entity-operations.html)).

Результат операции нужно всегда проверять через `isSuccess()`, а ошибки получать через `getErrorMessages()` или `getErrors()` ([операции с сущностями](https://docs.1c-bitrix.ru/pages/orm/entity-operations.html), [Result::getErrors](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/result/geterrors.php)).

```php
use Bitrix\Main\Type\DateTime;
use Vendor\Module\Entity\ItemTable;

$result = ItemTable::add([
    'UF_XML_ID' => 'SKU-100',
    'NAME' => 'Товар 100',
    'ACTIVE' => true,
    'CREATED_AT' => new DateTime(),
]);

if (!$result->isSuccess()) {
    throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
}

$id = $result->getId();
```

Для составного первичного ключа `delete()` принимает массив ключей, например `Table::delete(['key1' => $value1, 'key2' => $value2])` ([операции с сущностями](https://docs.1c-bitrix.ru/pages/orm/entity-operations.html), [операции с сущностями в курсе](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2244&LESSON_PATH=3913.5062.5748.2244)).

`UpdateResult` позволяет получить количество фактически обновленных строк через `getAffectedRowsCount()` ([операции с сущностями](https://docs.1c-bitrix.ru/pages/orm/entity-operations.html)).

## Выборки ORM

`DataManager::getList()` выполняет запрос и возвращает `Bitrix\Main\DB\Result`, а параметры запроса включают `select`, `filter`, `group`, `order`, `limit`, `offset` и `runtime` ([DataManager::getList](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/datamanager/getlist.php), [DataManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/datamanager/index.php)).

`select` задает поля выборки и поддерживает алиасы вида `'ALIAS' => 'FIELD'`, `filter` задает условия WHERE в виде `'(condition)FIELD' => 'value'`, `group` задает GROUP BY, `order` задает сортировку, `limit` и `offset` задают ограничение и смещение, а `runtime` добавляет динамические поля ([DataManager::getList](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/datamanager/getlist.php)).

```php
$rows = ItemTable::getList([
    'select' => ['ID', 'UF_XML_ID', 'NAME', 'ACTIVE'],
    'filter' => [
        '=ACTIVE' => true,
        '%=NAME' => '%фильтр%',
    ],
    'order' => ['ID' => 'DESC'],
    'limit' => 50,
    'offset' => 0,
])->fetchAll();
```

Префиксы `%=` и `=%` используются для LIKE-условий, а пример `'%=NAME' => '%тест%'` отбирает записи, содержащие подстроку в поле `NAME` ([getList](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=5753)).

`ExpressionField` является виртуальным полем на основе SQL-выражения и может использоваться только при выборке, фильтрации, группировке и сортировке, потому что физической колонки в таблице для записи такого значения нет ([ORM-концепции](https://docs.1c-bitrix.ru/pages/orm/orm-concepts.html), [концепция сущности](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=4803&LESSON_PATH=3913.3516.5748.4803)).

```php
use Bitrix\Main\ORM\Fields\ExpressionField;

$result = ItemTable::getList([
    'select' => ['CNT'],
    'runtime' => [
        new ExpressionField('CNT', 'COUNT(%s)', ['ID']),
    ],
]);
```

`getRow()` возвращает одну строку или `null` по параметрам `getList`, `getRowById()` возвращает строку по первичному ключу, а `getCount()` выполняет COUNT-запрос к сущности ([DataManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/datamanager/index.php), [DataManager::getRow](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/datamanager/getrow.php)).

## Отношения ORM

В ORM отношения между сущностями описывают связь данных разных таблиц, а основные типы отношений включают 1:1, 1:N и N:M ([отношения между сущностями](https://docs.1c-bitrix.ru/pages/orm/entity-relations.html)).

Для связи «многие к одному» или «один к одному» используется `Reference`, для «один ко многим» используется `OneToMany`, а для «многие ко многим» используется `ManyToMany` ([отношения между сущностями](https://docs.1c-bitrix.ru/pages/orm/entity-relations.html)).

Условие соединения задается через `Join::on('this.LOCAL_FIELD', 'ref.REMOTE_FIELD')`, а тип соединения можно настроить через `configureJoinType('inner')` ([отношения между сущностями](https://docs.1c-bitrix.ru/pages/orm/entity-relations.html)).

```php
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Join;

new Reference(
    'USER',
    \Bitrix\Main\UserTable::class,
    Join::on('this.USER_ID', 'ref.ID')
);
```

Связанные сущности можно выбирать через `select`, например `'select' => ['*', 'PUBLISHER']`, а для массивного результата алиасы связи могут формировать префиксы полей ([отношения между сущностями](https://docs.1c-bitrix.ru/pages/orm/entity-relations.html)).

Для объектного подхода ORM поддерживает `fetchObject()`, объектные методы доступа к полям и отношениям, а также методы коллекций для связанных сущностей ([объекты ORM](https://docs.1c-bitrix.ru/pages/orm/objects.html), [отношения между сущностями](https://docs.1c-bitrix.ru/pages/orm/entity-relations.html)).

## ORM-события

`DataManager` поддерживает события добавления, изменения и удаления: `OnBeforeAdd`, `OnAdd`, `OnAfterAdd`, `OnBeforeUpdate`, `OnUpdate`, `OnAfterUpdate`, `OnBeforeDelete`, `OnDelete` и `OnAfterDelete` ([DataManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/datamanager/index.php), [операции с сущностями](https://docs.1c-bitrix.ru/pages/orm/entity-operations.html)).

В `OnBefore*` можно изменить поля через `EventResult::modifyFields()`, удалить поля через `unsetFields()` или добавить ошибку через `addError()` ([операции с сущностями](https://docs.1c-bitrix.ru/pages/orm/entity-operations.html), [EventResult](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/eventresult/index.php)).

```php
use Bitrix\Main\ORM\Event;
use Bitrix\Main\ORM\EventResult;
use Bitrix\Main\ORM\EntityError;

public static function onBeforeUpdate(Event $event): EventResult
{
    $result = new EventResult();
    $fields = $event->getParameter('fields');

    if (isset($fields['UF_XML_ID'])) {
        $result->addError(new EntityError('XML_ID запрещено изменять после создания'));
    }

    return $result;
}
```

Подписка на ORM-события может выполняться через `Bitrix\Main\ORM\EventManager::getInstance()->addEventHandler(TableClass::class, DataManager::EVENT_ON_BEFORE_ADD, $callback)` ([операции с сущностями](https://docs.1c-bitrix.ru/pages/orm/entity-operations.html)).

## Глобальные события модуля

`Bitrix\Main\EventManager` предназначен для краткосрочной и долгосрочной регистрации обработчиков событий и реализует Singleton через `getInstance()` ([EventManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/EventManager/index.php)).

`addEventHandler()` регистрирует обработчик на время выполнения, а `registerEventHandler()` регистрирует долгосрочный обработчик, который должен сниматься через `removeEventHandler()` или `unRegisterEventHandler()` соответственно ([EventManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/EventManager/index.php)).

В обработчики, зарегистрированные обычными D7-методами, передается объект `Bitrix\Main\Event`, а если нужны старые аргументы события, нужно использовать `addEventHandlerCompatible()` или `registerEventHandlerCompatible()` ([EventManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/EventManager/index.php)).

Для старых событий ядра, `sale`, `iblock`, `crm`, `catalog`, `tasks` и других исторических модулей нельзя автоматически выбирать `registerEventHandler()` только потому, что код пишется на D7. Сначала нужно проверить документацию конкретного события и его ожидаемую сигнатуру: если событие исторически передает старые аргументы, нужно регистрировать долгосрочный обработчик через `registerEventHandlerCompatible()` и писать метод с legacy-аргументами; если событие является D7-событием и передает объект `Bitrix\Main\Event`, используется `registerEventHandler()` ([EventManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/EventManager/index.php)).

```php
namespace Vendor\Module\Event;

use Bitrix\Main\Loader;

final class MainHandler
{
    public static function onAfterUserAdd(array &$fields): void
    {
        if (!Loader::includeModule('vendor.module')) {
            return;
        }

        $userId = (int)($fields['ID'] ?? 0);
        // Логика реакции на legacy-событие OnAfterUserAdd.
    }
}
```

Для собственных событий можно создать объект `new Bitrix\Main\Event($moduleId, $eventName, $parameters)`, вызвать `$event->send()`, а затем обработать `$event->getResults()` ([EventManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/EventManager/index.php)).

## Агенты и фоновые задачи

`CAgent::AddAgent()` регистрирует функцию-агент, принимает строку PHP-вызова, ID модуля, режим периодичности, интервал, активность и дату следующего запуска, а при успехе возвращает ID агента ([CAgent::AddAgent](https://dev.1c-bitrix.ru/api_help/main/reference/cagent/addagent.php)).

Если агент ничего не возвращает, он удаляется, поэтому повторяющийся агент обычно должен вернуть строку собственного вызова ([CAgent::AddAgent](https://dev.1c-bitrix.ru/api_help/main/reference/cagent/addagent.php)).

Режим `period = 'Y'` рассчитывает следующий запуск как `next_exec + interval` и используется для задач, которые должны отработать пропущенные запуски, а режим `period = 'N'` рассчитывает следующий запуск от даты последнего выполнения и подходит для обычных периодических задач ([CAgent::AddAgent](https://dev.1c-bitrix.ru/api_help/main/reference/cagent/addagent.php)).

```php
namespace Vendor\Module\Agent;

use Bitrix\Main\Loader;

final class SyncAgent
{
    public static function run(): string
    {
        if (Loader::includeModule('vendor.module')) {
            // Выполнить небольшую порцию фоновой работы.
        }

        return '\\Vendor\\Module\\Agent\\SyncAgent::run();';
    }
}
```

Для тяжелых задач агент должен обрабатывать данные порциями, хранить курсор в `Option` или собственной таблице и не выполнять долгий монолитный процесс на пользовательском хите. Этот вывод является практической архитектурной рекомендацией, основанной на том, что агенты выполняются периодически и должны возвращать собственный вызов для продолжения работы ([CAgent::AddAgent](https://dev.1c-bitrix.ru/api_help/main/reference/cagent/addagent.php), [Option::set](https://dev.1c-bitrix.ru/api_d7/bitrix/main/config/option/set.php)).

## Настройки модуля

Файл `/local/modules/{module.id}/options.php` предназначен для управления параметрами модуля, назначением прав и другими настройками, потому что Bitrix подключает его на странице «Настройки модулей» ([описание и параметры модуля](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2824)).

Файл `default_option.php` задает значения по умолчанию в массиве `{module_id}_default_option`, а `Option::getDefaults()` возвращает массив значений по умолчанию из этого файла ([описание и параметры модуля](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2824), [Option::getDefaults](https://dev.1c-bitrix.ru/api_d7/bitrix/main/config/option/getdefaults.php)).

`Option::set($moduleId, $name, $value, $siteId)` сохраняет значение параметра в базу, где `moduleId` и `name` ограничены 50 символами, а сохраняемое значение ограничено 2000 символами ([Option::set](https://dev.1c-bitrix.ru/api_d7/bitrix/main/config/option/set.php)).

```php
<?php
// /local/modules/vendor.module/default_option.php

$vendor_module_default_option = [
    'sync_enabled' => 'N',
    'sync_limit' => '100',
];
```

```php
<?php
// /local/modules/vendor.module/options.php

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;

defined('B_PROLOG_INCLUDED') || die();

$moduleId = 'vendor.module';
Loc::loadMessages(__FILE__);

$request = Application::getInstance()->getContext()->getRequest();

if ($request->isPost() && check_bitrix_sessid()) {
    Option::set($moduleId, 'sync_enabled', $request->getPost('sync_enabled') === 'Y' ? 'Y' : 'N');
    Option::set($moduleId, 'sync_limit', (string)max(1, (int)$request->getPost('sync_limit')));
}

$syncEnabled = Option::get($moduleId, 'sync_enabled', 'N');
$syncLimit = Option::get($moduleId, 'sync_limit', '100');
?>
<form method="post" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPageParam()) ?>">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="mid" value="<?= htmlspecialcharsbx($moduleId) ?>">
    <table class="adm-detail-content-table edit-table">
        <tr>
            <td width="40%">Включить синхронизацию</td>
            <td><input type="checkbox" name="sync_enabled" value="Y" <?= $syncEnabled === 'Y' ? 'checked' : '' ?>></td>
        </tr>
        <tr>
            <td>Лимит за запуск</td>
            <td><input type="number" name="sync_limit" value="<?= (int)$syncLimit ?>"></td>
        </tr>
    </table>
    <input type="submit" name="save" value="<?= Loc::getMessage('MAIN_SAVE') ?>">
</form>
```

## Права доступа

Если модуль поддерживает собственную схему прав, свойство `MODULE_GROUP_RIGHTS` должно быть равно `Y`, а метод `GetModuleRightList()` должен вернуть массив `reference_id` и `reference` ([CModule](https://dev.1c-bitrix.ru/api_help/main/reference/cmodule/index.php), [CModule::GetModuleRightList](https://dev.1c-bitrix.ru/api_help/main/reference/cmodule/getmodulerightlist.php), [описание и параметры модуля](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2824)).

Стандартные права модуля включают `D` для запрета доступа, `R` для просмотра и `W` для модификации данных ([CMain::GetUserRight](https://dev.1c-bitrix.ru/api_help/main/reference/cmain/getuserright.php)).

`$APPLICATION->GetUserRight($moduleId)` возвращает право пользователя в логике модуля, а собственный набор прав задается методом `GetModuleRightList()` класса установщика ([CMain::GetUserRight](https://dev.1c-bitrix.ru/api_help/main/reference/cmain/getuserright.php)).

```php
namespace Vendor\Module\Access;

use Bitrix\Main\AccessDeniedException;

final class Permission
{
    public const MODULE_ID = 'vendor.module';
    public const DENIED = 'D';
    public const READ = 'R';
    public const WRITE = 'W';

    public static function getCurrentUserRight(): string
    {
        global $APPLICATION;
        return (string)$APPLICATION->GetUserRight(self::MODULE_ID);
    }

    public static function canRead(): bool
    {
        return self::getCurrentUserRight() >= self::READ;
    }

    public static function canWrite(): bool
    {
        return self::getCurrentUserRight() >= self::WRITE;
    }

    public static function requireRead(): void
    {
        if (!self::canRead()) {
            throw new AccessDeniedException('Access denied');
        }
    }

    public static function requireWrite(): void
    {
        if (!self::canWrite()) {
            throw new AccessDeniedException('Access denied');
        }
    }
}
```

Права нужно проверять в административных страницах, AJAX-контроллерах, сервисах записи, агентах с пользовательским контекстом и обработчиках событий, потому что настройка прав сама по себе не запрещает выполнение произвольного кода модуля без явной проверки. Это практическое правило следует из того, что `GetUserRight()` только возвращает право, а применение этого права выполняется кодом модуля ([CMain::GetUserRight](https://dev.1c-bitrix.ru/api_help/main/reference/cmain/getuserright.php)).

## Административные страницы

Административные файлы модуля обычно копируются из `install/admin/` в `/bitrix/admin/`, что соответствует требованиям установки по копированию вызывающих скриптов в административный раздел ([установка и удаление модулей](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=3475), [описание и параметры модуля](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2824)).

Современный список в админке лучше строить на `bitrix:main.ui.grid` и `bitrix:main.ui.filter`, потому что `main.ui.grid` предназначен для визуального представления данных таблицей, а `main.ui.filter` выводит фильтр и поиск ([main.ui.grid и main.ui.filter](https://dev.1c-bitrix.ru/api_d7/bitrix/main/systemcomponents/gridandfilter/index.php), [main.ui.filter](https://dev.1c-bitrix.ru/api_d7/bitrix/main/systemcomponents/gridandfilter/mainuifilter.php)).

`main.ui.filter` является системным компонентом, имеет физический путь `/bitrix/components/bitrix/main.ui.filter` и принимает параметры `FILTER_ID`, `GRID_ID`, `FILTER`, `ENABLE_LABEL`, `ENABLE_LIVE_SEARCH` и `FILTER_PRESETS` ([main.ui.filter](https://dev.1c-bitrix.ru/api_d7/bitrix/main/systemcomponents/gridandfilter/mainuifilter.php)).

Типы полей фильтра включают `string`, `list`, `number`, `date`, `custom_date`, `checkbox`, `custom_entity`, `entity_selector` и `dest_selector`, а `entity_selector` доступен с версии `main 21.200.0` ([main.ui.filter](https://dev.1c-bitrix.ru/api_d7/bitrix/main/systemcomponents/gridandfilter/mainuifilter.php)).

```php
<?php
// /bitrix/admin/vendor_module_entities.php

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

use Bitrix\Main\Loader;
use Bitrix\Main\UI\Filter\Options as FilterOptions;
use Bitrix\Main\UI\PageNavigation;
use Vendor\Module\Access\Permission;
use Vendor\Module\Entity\ItemTable;

Loader::includeModule('vendor.module');
Permission::requireRead();

$APPLICATION->SetTitle('Элементы модуля');

$gridId = 'vendor_module_items';
$filterFields = [
    ['id' => 'ID', 'name' => 'ID', 'type' => 'number'],
    ['id' => 'NAME', 'name' => 'Название', 'type' => 'string'],
    ['id' => 'ACTIVE', 'name' => 'Активность', 'type' => 'checkbox'],
];

$filterOptions = new FilterOptions($gridId);
$filterData = $filterOptions->getFilter($filterFields);

$ormFilter = [];
if (!empty($filterData['NAME'])) {
    $ormFilter['%=NAME'] = '%' . $filterData['NAME'] . '%';
}
if (isset($filterData['ACTIVE'])) {
    $ormFilter['=ACTIVE'] = $filterData['ACTIVE'] === 'Y' ? 'Y' : 'N';
}

$gridOptions = new \Bitrix\Main\Grid\Options($gridId);
$sorting = $gridOptions->GetSorting(['sort' => ['ID' => 'DESC']]);
$allowedSortFields = ['ID', 'NAME', 'ACTIVE'];
$order = [];

foreach ($sorting['sort'] as $field => $direction) {
    if (in_array($field, $allowedSortFields, true)) {
        $order[$field] = mb_strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
    }
}

if (!$order) {
    $order = ['ID' => 'DESC'];
}

$navParams = $gridOptions->GetNavParams(['nPageSize' => 20]);
$nav = new PageNavigation($gridId);
$nav->allowAllRecords(false)
    ->setPageSize((int)$navParams['nPageSize'])
    ->initFromUri();

$totalCount = ItemTable::getCount($ormFilter);
$nav->setRecordCount($totalCount);

$rows = [];
$result = ItemTable::getList([
    'select' => ['ID', 'UF_XML_ID', 'NAME', 'ACTIVE'],
    'filter' => $ormFilter,
    'order' => $order,
    'limit' => $nav->getLimit(),
    'offset' => $nav->getOffset(),
]);

while ($item = $result->fetch()) {
    $actions = [];

    if (Permission::canWrite()) {
        $actions[] = [
            'text' => 'Редактировать',
            'onclick' => 'document.location.href="vendor_module_entity_edit.php?ID=' . (int)$item['ID'] . '"',
        ];
    }

    $rows[] = [
        'id' => $item['ID'],
        'data' => $item,
        'actions' => $actions,
    ];
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$APPLICATION->IncludeComponent('bitrix:main.ui.filter', '', [
    'FILTER_ID' => $gridId,
    'GRID_ID' => $gridId,
    'FILTER' => $filterFields,
    'ENABLE_LIVE_SEARCH' => true,
    'ENABLE_LABEL' => true,
]);

$APPLICATION->IncludeComponent('bitrix:main.ui.grid', '', [
    'GRID_ID' => $gridId,
    'COLUMNS' => [
        ['id' => 'ID', 'name' => 'ID', 'sort' => 'ID', 'default' => true],
        ['id' => 'UF_XML_ID', 'name' => 'XML_ID', 'default' => true],
        ['id' => 'NAME', 'name' => 'Название', 'default' => true],
        ['id' => 'ACTIVE', 'name' => 'Активность', 'default' => true],
    ],
    'ROWS' => $rows,
    'TOTAL_ROWS_COUNT' => $totalCount,
    'NAV_OBJECT' => $nav,
    'SHOW_ROW_CHECKBOXES' => false,
    'SHOW_GRID_SETTINGS_MENU' => true,
    'SHOW_PAGINATION' => true,
    'ALLOW_SORT' => true,
]);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
```

Для административного списка недостаточно включить `'SHOW_PAGINATION' => true`: серверная выборка должна использовать `PageNavigation`, `getCount($filter)`, `limit`, `offset`, `TOTAL_ROWS_COUNT` и `NAV_OBJECT`, иначе интерфейс будет выглядеть как список с пагинацией, но фактически показывать только фиксированный первый набор строк. Сортировку нельзя напрямую принимать из запроса: нужно пропускать ее через whitelist ORM-полей, потому что `getList()` передает `order`, `limit` и `offset` в SQL-уровень выборки ([DataManager::getList](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/datamanager/getlist.php), [main.ui.grid и main.ui.filter](https://dev.1c-bitrix.ru/api_d7/bitrix/main/systemcomponents/gridandfilter/index.php)).

## Компоненты модуля

Компоненты модуля следует размещать в `install/components/{vendor}/{component.name}/` для копирования при установке или сразу в `/local/components/{vendor}/{component.name}/` для проектной разработки. Это практическая структура, совместимая с общим механизмом установки файлов модуля, где установщик может копировать файлы из `install/` в рабочие директории продукта ([описание и параметры модуля](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2824)).

Логику компонента лучше выносить в класс `class.php`, внутри класса подключать модуль через `Loader::includeModule()`, а доступ к данным выполнять через сервисы и ORM-репозитории модуля ([Loader](https://dev.1c-bitrix.ru/api_d7/bitrix/main/loader/index.php), [DataManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/datamanager/index.php)).

Компонент не должен напрямую строить сложный SQL, менять таблицы или регистрировать события, потому что эти обязанности относятся к слою установки, ORM и сервисов. Это архитектурная рекомендация, вытекающая из разделения установщика, D7-классов и компонентов в структуре модуля ([описание и параметры модуля](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2824), [ORM-концепции](https://docs.1c-bitrix.ru/pages/orm/orm-concepts.html)).

## Сервисный слой и репозитории

`DataManager` должен описывать таблицу и базовые операции, а бизнес-правила лучше выносить в сервисы, чтобы ORM-класс не превращался в монолит. Это практическая рекомендация, согласованная с тем, что `DataManager` официально является абстрактным базовым классом для работы с объектами данных и должен переопределять только `getTableName()` и `getMap()` ([DataManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/datamanager/index.php)).

```php
namespace Vendor\Module\Service;

use Bitrix\Main\Error;
use Bitrix\Main\Result;
use Bitrix\Main\Type\DateTime;
use Vendor\Module\Entity\ItemTable;

final class ItemService
{
    public function create(string $xmlId, string $name): Result
    {
        $result = new Result();

        $addResult = ItemTable::add([
            'UF_XML_ID' => $xmlId,
            'NAME' => $name,
            'ACTIVE' => true,
            'CREATED_AT' => new DateTime(),
        ]);

        if (!$addResult->isSuccess()) {
            $result->addErrors($addResult->getErrors());
            return $result;
        }

        return $result->setData(['ID' => $addResult->getId()]);
    }
}
```

Сервисы должны возвращать `Bitrix\Main\Result` или специализированный DTO, а не выбрасывать исключения для ожидаемых бизнес-ошибок, потому что D7-подход поддерживает как результаты операций с ошибками, так и исключения для системных ошибок ([операции с сущностями](https://docs.1c-bitrix.ru/pages/orm/entity-operations.html), [SystemException](https://dev.1c-bitrix.ru/api_d7/bitrix/main/systemexception/index.php)).

## Работа с SQL

Прямой SQL допустим для DDL-операций установки, сложных миграций, массовых операций и редких случаев, где ORM недостаточно выразителен, но обычная бизнес-логика должна использовать ORM. Это практическое правило основано на том, что ORM обеспечивает единый интерфейс `add`, `update`, `delete`, `getList`, события и валидацию, тогда как `queryExecute()` просто выполняет SQL без результата ([DataManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/datamanager/index.php), [Connection::queryExecute](https://dev.1c-bitrix.ru/api_d7/bitrix/main/db/connection/queryexecute.php)).

`query()` возвращает `Bitrix\Main\DB\Result`, `queryScalar()` возвращает значение первого столбца первой строки, а `queryExecute()` выполняет запрос без возврата результата ([выполнение запросов](https://docs.1c-bitrix.ru/pages/database/query-execution.html), [Connection::queryExecute](https://dev.1c-bitrix.ru/api_d7/bitrix/main/db/connection/queryexecute.php)).

`Result::fetch()` возвращает строку с преобразованием типов, `fetchRaw()` возвращает исходные данные, а результат можно обходить через `foreach` или `while ($row = $result->fetch())` ([выполнение запросов](https://docs.1c-bitrix.ru/pages/database/query-execution.html)).

Для безопасной работы с SQL следует использовать `SqlHelper`, `SqlExpression` и методы соединения, которые экранируют имена и значения, потому что документация по выполнению запросов указывает на `SqlExpression` и `SqlHelper` как инструменты безопасной работы с SQL ([выполнение запросов](https://docs.1c-bitrix.ru/pages/database/query-execution.html), [Connection::getSqlHelper](https://dev.1c-bitrix.ru/api_d7/bitrix/main/db/connection/getsqlhelper.php)).

## Транзакции

Для операций, где несколько изменений должны применяться атомарно, используйте `Application::getConnection()->startTransaction()`, `commitTransaction()` и `rollbackTransaction()`, потому что эти методы доступны в D7-соединении наряду с `isTableExists`, `dropTable` и другими методами управления БД ([Connection::isTableExists](https://dev.1c-bitrix.ru/api_d7/bitrix/main/db/connection/istableexists.php)).

```php
$connection = \Bitrix\Main\Application::getConnection();
$connection->startTransaction();

try {
    $result = ItemTable::update($id, ['NAME' => $name]);
    if (!$result->isSuccess()) {
        throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
    }

    $connection->commitTransaction();
} catch (\Throwable $e) {
    $connection->rollbackTransaction();
    throw $e;
}
```

Транзакции особенно важны при установке таблиц, переносе данных, массовой синхронизации и изменении нескольких связанных таблиц. Это практическая рекомендация, основанная на наличии транзакционных методов в D7-соединении и на необходимости сохранять целостность данных при нескольких зависимых операциях ([Connection::isTableExists](https://dev.1c-bitrix.ru/api_d7/bitrix/main/db/connection/istableexists.php), [ORM-отношения](https://docs.1c-bitrix.ru/pages/orm/entity-relations.html)).

## Обработка ошибок

`SystemException` является базовым классом системных исключений, а D7 обрабатывает ошибки через механизм исключений PHP: если ошибка должна быть обработана, исключение нужно поймать через `try/catch` ([SystemException](https://dev.1c-bitrix.ru/api_d7/bitrix/main/systemexception/index.php)).

Иерархия D7-исключений включает `ArgumentException`, `ArgumentNullException`, `ArgumentOutOfRangeException`, `ArgumentTypeException`, `DB\Exception`, `DB\ConnectionException`, `DB\SqlException` и `AccessDeniedException` ([SystemException](https://dev.1c-bitrix.ru/api_d7/bitrix/main/systemexception/index.php)).

Ожидаемые ошибки валидации и бизнес-логики лучше возвращать через `Result`, а системные ошибки, неверные аргументы и запрет доступа оформлять исключениями. Это практическое правило объединяет ORM-результаты операций и D7-механизм исключений ([операции с сущностями](https://docs.1c-bitrix.ru/pages/orm/entity-operations.html), [SystemException](https://dev.1c-bitrix.ru/api_d7/bitrix/main/systemexception/index.php)).

## Миграции и обновления

Для промышленного модуля нужно иметь слой миграций, даже если Bitrix не навязывает единую встроенную систему миграций для кастомных модулей. Это практическая рекомендация: официальные механизмы установки дают `InstallDB()` и `UnInstallDB()`, а D7-соединение дает методы проверки таблиц, изменения структуры и выполнения SQL ([описание и параметры модуля](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2824), [Connection::isTableExists](https://dev.1c-bitrix.ru/api_d7/bitrix/main/db/connection/istableexists.php), [Connection::queryExecute](https://dev.1c-bitrix.ru/api_d7/bitrix/main/db/connection/queryexecute.php)).

Минимальная схема миграций: таблица `b_vendor_module_migration` хранит версию, имя класса, дату применения и статус, а установщик запускает непримененные миграции после `RegisterModule()`. Это практическая архитектурная схема, совместимая с D7-соединением и `Option`, но ее нужно реализовать в модуле самостоятельно ([Connection::isTableExists](https://dev.1c-bitrix.ru/api_d7/bitrix/main/db/connection/istableexists.php), [Option::set](https://dev.1c-bitrix.ru/api_d7/bitrix/main/config/option/set.php)).

Миграция должна быть идемпотентной: перед созданием таблицы проверять `isTableExists()`, перед созданием индекса проверять `isIndexExists()`, а перед удалением проверять наличие объекта. Это практическое правило следует из доступных методов D7-соединения `isTableExists`, `isIndexExists`, `dropTable` и `createIndex` ([Connection::isTableExists](https://dev.1c-bitrix.ru/api_d7/bitrix/main/db/connection/istableexists.php), [Connection::createIndex](https://dev.1c-bitrix.ru/api_d7/bitrix/main/db/connection/createindex.php)).

Отдельный сценарий обновления версии нужен даже для небольшого модуля. Если установленная версия была `1.0.0`, а в `install/version.php` стала `1.1.0`, код обновления должен определить текущую установленную версию, найти непримененные миграции между `1.0.0` и `1.1.0`, выполнить их в порядке возрастания, записать факт применения каждой миграции, обновить служебную версию модуля и безопасно завершиться при повторном запуске. Ошибки миграций нужно логировать и не скрывать, а сами миграции должны быть повторно запускаемыми без разрушения уже примененных изменений; это следует из того, что версия модуля хранится в `install/version.php`, а изменение структуры базы выполняется через D7-соединение и SQL-операции ([описание и параметры модуля](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2824), [Connection::queryExecute](https://dev.1c-bitrix.ru/api_d7/bitrix/main/db/connection/queryexecute.php)).

Для коробочных модулей безопаснее принять стратегию `forward-only` миграций: каждая миграция имеет `up()`, является идемпотентной и не пытается автоматически откатывать продуктивные данные через `down()` без отдельного плана. Если проект требует `down()`, он должен быть явно описан как ручной аварийный сценарий с backup, проверкой зависимостей и тестом на копии базы, потому что автоматический откат схемы может уничтожить данные, созданные пользователями после обновления.

```php
final class UpdateManager
{
    public const MODULE_ID = 'vendor.module';

    public static function run(string $fromVersion, string $toVersion): void
    {
        $migrations = [
            '1.1.0' => \Vendor\Module\Migration\Version202604290001::class,
        ];

        foreach ($migrations as $version => $className) {
            if (version_compare($version, $fromVersion, '<=') || version_compare($version, $toVersion, '>')) {
                continue;
            }

            if (self::isApplied($className)) {
                continue;
            }

            try {
                $className::up();
                self::markApplied($className, $version);
            } catch (\Throwable $e) {
                \Vendor\Module\Log\Logger::exception($e, ['migration' => $className]);
                throw $e;
            }
        }
    }

    private static function isApplied(string $className): bool
    {
        // Проверить b_vendor_module_migration.
        return false;
    }

    private static function markApplied(string $className, string $version): void
    {
        // Записать успешное применение миграции.
    }
}
```

## Логирование и диагностика

Агенты, миграции, внешние интеграции и обработчики событий должны писать диагностические записи в единый логирующий слой модуля, а не разбрасывать `file_put_contents()` по коду. Минимально полезная запись содержит дату, уровень, контекст, код операции, ID сущности, текст ошибки и стек исключения для системных сбоев; при этом токены, пароли, cookie, OAuth-коды, webhook-URL и персональные данные должны маскироваться до записи в лог.

```php
namespace Vendor\Module\Log;

final class Logger
{
    public static function exception(\Throwable $exception, array $context = []): void
    {
        self::write('error', $exception->getMessage(), $context + [
            'exception' => get_class($exception),
            'trace' => $exception->getTraceAsString(),
        ]);
    }

    public static function write(string $level, string $message, array $context = []): void
    {
        $context = self::maskSecrets($context);
        // Записать в собственную таблицу логов, внешний централизованный лог
        // или защищенный файловый путь вне web root.
    }

    private static function maskSecrets(array $context): array
    {
        foreach ($context as $key => $value) {
            if (preg_match('/token|password|secret|cookie|authorization/i', (string)$key)) {
                $context[$key] = '***';
            }
        }

        return $context;
    }
}
```

Логи собственного модуля не нужно писать в `/bitrix/modules/{module}/logs`, потому что собственный код должен жить в `/local/modules`, а `/bitrix` относится к области ядра и продукта. Предпочтительные варианты — собственная таблица логов, внешний централизованный лог или защищенный путь вне web root; если файловый лог все же находится внутри проекта, нужно запретить web-доступ, настроить ротацию, ограничить срок хранения и исключить попадание секретов в текст записей.

Бизнес-ошибки и системные ошибки нужно разделять. Бизнес-ошибка, например «нельзя изменить XML_ID» или «не найден обязательный каталог», должна возвращаться через `Result`/`Error` и быть понятной пользователю; системная ошибка, например падение SQL, недоступность API или исключение миграции, должна логироваться с техническим контекстом и пробрасываться выше либо переводиться в контролируемое сообщение. Для фонового агента полезно хранить в `Option` или собственной таблице `last_success_at`, `last_error_at`, `last_error_message`, `processed_count` и курсор последней успешно обработанной записи, потому что `Option` предназначен для параметров модуля, а агент выполняется периодически и должен иметь диагностируемое состояние ([Option::set](https://dev.1c-bitrix.ru/api_d7/bitrix/main/config/option/set.php), [CAgent::AddAgent](https://dev.1c-bitrix.ru/api_help/main/reference/cagent/addagent.php), [SystemException](https://dev.1c-bitrix.ru/api_d7/bitrix/main/systemexception/index.php)).

## Безопасность

Каждый POST-запрос в административных страницах и настройках должен проверять `check_bitrix_sessid()`, а формы должны включать `bitrix_sessid_post()`. Это практическое правило Bitrix-разработки для защиты от CSRF; оно дополняет официальные требования к административным страницам и настройкам модуля ([описание и параметры модуля](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2824)).

Все значения, выводимые в HTML, нужно экранировать через `htmlspecialcharsbx()`, а входные значения нужно брать через `Application::getInstance()->getContext()->getRequest()`, потому что D7 предоставляет объект контекста и запроса, а UI модуля работает в административных страницах с пользовательским вводом ([Option::set](https://dev.1c-bitrix.ru/api_d7/bitrix/main/config/option/set.php), [main.ui.filter](https://dev.1c-bitrix.ru/api_d7/bitrix/main/systemcomponents/gridandfilter/mainuifilter.php)).

Не передавайте пользовательский ввод напрямую в SQL; используйте ORM-фильтры, `SqlHelper`, `SqlExpression` или методы соединения, потому что прямой SQL через `queryExecute()` выполняется как есть и не предоставляет бизнес-валидацию ORM ([DataManager::getList](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/datamanager/getlist.php), [выполнение запросов](https://docs.1c-bitrix.ru/pages/database/query-execution.html), [Connection::queryExecute](https://dev.1c-bitrix.ru/api_d7/bitrix/main/db/connection/queryexecute.php)).

Секреты, токены и пароли нельзя хранить в обычных `Option`, если они чувствительны; для ORM-таблиц следует использовать `CryptoField` или `SecretField`, потому что современная ORM-документация содержит поля для зашифрованных данных и секретов ([ORM-концепции](https://docs.1c-bitrix.ru/pages/orm/orm-concepts.html)).

## Производительность

Выборки должны указывать только нужные поля в `select`, потому что `getList()` явно управляет SELECT-частью запроса и позволяет выбирать только необходимые данные ([DataManager::getList](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/datamanager/getlist.php)).

Для списков нужно использовать `limit` и `offset`, потому что `getList()` поддерживает эти параметры и они ограничивают размер результата ([DataManager::getList](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/datamanager/getlist.php)).

Сложные вычисления лучше переносить в `ExpressionField` только там, где они реально нужны, потому что `ExpressionField` является SQL-выражением и используется в выборках, фильтрации, группировке и сортировке ([ORM-концепции](https://docs.1c-bitrix.ru/pages/orm/orm-concepts.html)).

Для часто используемых справочников можно использовать кеширование, а с версии `main 24.100.0` ORM-сущность может отключить кешируемость через `isCacheable(): bool`, если кеширование вредно для конкретной сущности ([ORM-концепции](https://docs.1c-bitrix.ru/pages/orm/orm-concepts.html)).

В агентах нужно обрабатывать данные батчами и хранить курсор прогресса, потому что агент запускается периодически и должен быстро вернуть управление, чтобы не создавать долгую нагрузку на хит или cron. Это практическая рекомендация, согласованная с механизмом периодичности и возврата строки собственного вызова у `CAgent` ([CAgent::AddAgent](https://dev.1c-bitrix.ru/api_help/main/reference/cagent/addagent.php)).

## Локализация

Языковые файлы должны располагаться в `lang/{lang}/...`, а PHP-файлы должны вызывать `Loc::loadMessages(__FILE__)`, потому что DataManager-документация указывает загрузку языковых фраз через `Loc::loadMessages(__FILE__)` ([DataManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/datamanager/index.php)).

Тексты названия модуля, описания, прав, ошибок, настроек и административных интерфейсов нужно выносить в языковые файлы. Это практическое правило, согласованное с использованием `Loc` в D7 и с тем, что установщик использует `MODULE_NAME` и `MODULE_DESCRIPTION` ([DataManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/datamanager/index.php), [описание и параметры модуля](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2824)).

## Аннотации и IDE

Bitrix Framework поддерживает ORM-аннотации для подсказок IDE, а файл аннотаций ORM-классов ядра находится в `/bitrix/modules/main/meta/orm.php` ([аннотации ORM](https://docs.1c-bitrix.ru/pages/orm/annotations.html)).

Команда `php bitrix.php orm:annotate` генерирует аннотации, а параметр `-m` позволяет сканировать один модуль, несколько модулей или все модули ([аннотации ORM](https://docs.1c-bitrix.ru/pages/orm/annotations.html)).

Для кастомного модуля полезно генерировать аннотации после добавления или изменения ORM-сущностей, чтобы IDE понимала методы `query()`, `getByPrimary()`, `getList()`, `createObject()` и объектные классы сущностей ([аннотации ORM](https://docs.1c-bitrix.ru/pages/orm/annotations.html)).

## Что считать устаревшим

Не используйте `CModule::IncludeModule()` в новом прикладном коде, если можно использовать `Loader::includeModule()`, потому что `Loader` является D7-классом загрузки файлов, классов и модулей и указан как аналог старого `CModule` ([Loader](https://dev.1c-bitrix.ru/api_d7/bitrix/main/loader/index.php), [includeModule](https://dev.1c-bitrix.ru/api_d7/bitrix/main/loader/includemodule.php)).

Не используйте `RegisterModuleDependences()` для новых долгосрочных обработчиков, если можно использовать `EventManager::registerEventHandler()`, потому что `EventManager` является D7-аналогом старых функций регистрации событий ([EventManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/EventManager/index.php), [RegisterModuleDependences](https://www.dev.1c-bitrix.ru/api_help/main/functions/module/registermoduledependences.php)).

Не пишите бизнес-логику в `install/index.php`, `options.php` или административных прокси-файлах, потому что эти файлы являются точками входа установки, настроек и UI, а современная логика должна быть в `lib/` D7-классах ([описание и параметры модуля](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2824), [Loader](https://dev.1c-bitrix.ru/api_d7/bitrix/main/loader/index.php)).

Не используйте прямой SQL для CRUD по собственным таблицам без необходимости, потому что ORM `DataManager` уже предоставляет `add`, `update`, `delete`, `getList`, валидацию и события ([DataManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/datamanager/index.php), [операции с сущностями](https://docs.1c-bitrix.ru/pages/orm/entity-operations.html)).

Не храните большие JSON, токены и массивы в `Option`, потому что `Option::set()` ограничивает сохраняемое значение 2000 символами и предназначен для параметров модуля, а не для больших данных ([Option::set](https://dev.1c-bitrix.ru/api_d7/bitrix/main/config/option/set.php)).

## Что не генерировать

Локальная модель должна иметь стоп-лист, потому что для Bitrix24 опасен не только неправильный синтаксис, но и код, который частично устанавливается, ломает обновления или небезопасно работает с данными.

Не генерировать:

1. Обработчики `crm`, `sale`, `iblock`, `bizproc`, `tasks` и других интеграционных модулей без версии коробки, списка установленных модулей и проверки `Loader::includeModule()`.
2. PostgreSQL DDL на основе MySQL-примера без отдельной ветки `pgsql`, отдельного SQL-файла или теста на целевой СУБД.
3. Прямые SQL-запросы с пользовательским вводом без ORM, `SqlHelper`, параметризации или строгой валидации.
4. Хранение токенов, паролей, webhook-URL и больших JSON в обычных `Option`.
5. Долгие агенты без batch-обработки, курсора прогресса, лимита за запуск и записи последнего успешного состояния.
6. Административные POST-действия без `check_bitrix_sessid()` и формы без `bitrix_sessid_post()`.
7. Код собственного модуля, который пишет исходники, логи или рабочие данные в `/bitrix/modules`.
8. Установщик без `try/catch`, rollback и диагностики ошибок отката.
9. Grid со включенной визуальной пагинацией без `PageNavigation`, `TOTAL_ROWS_COUNT`, `NAV_OBJECT`, `limit` и `offset`.
10. Деинсталляцию с одним флагом `savedata`, если нужно различать таблицы, настройки и файлы.
11. Административные файлы без уникального префикса модуля, например без `vendor_module_*`.
12. Рекурсивное удаление каталогов через `DeleteDirFilesEx()` без явного whitelist целевого пути.
13. Автоматическое восстановление backup без opt-in подтверждения администратора.
14. Restore backup при несовпадении `module_version` без явной политики совместимости или мигратора формата backup.

## Чек-лист проектирования модуля

Перед написанием кода нужно определить ID модуля, неймспейс, список таблиц, права, события, настройки, административные страницы, агенты, миграции, компоненты и интеграции с другими модулями. Это практический чек-лист, основанный на том, что модуль имеет установщик, параметры, права, ORM-сущности, события и файлы установки ([описание и параметры модуля](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2824), [DataManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/datamanager/index.php), [EventManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/EventManager/index.php)).

| Вопрос | Правильное решение |
| --- | --- |
| Где хранить код | `/local/modules/vendor.module/lib/` |
| Как назвать класс установщика | `vendor_module extends CModule` |
| Как подключать модуль | `Loader::includeModule('vendor.module')` |
| Как описывать таблицы | `DataManager` + `getTableName()` + `getMap()` |
| Как выполнять CRUD | `Table::add/update/delete/getList` |
| Как хранить настройки | `Option::get/set`, `default_option.php`, `options.php` |
| Как регистрировать события | `EventManager::registerEventHandler()` для D7-сигнатуры или `registerEventHandlerCompatible()` для legacy-сигнатуры |
| Как делать фоновые задачи | `CAgent::AddAgent()` с D7-классом-обработчиком |
| Как проверять права | `GetModuleRightList()` + `$APPLICATION->GetUserRight()` |
| Как делать админский список | `main.ui.filter` + `main.ui.grid` |
| Как обрабатывать ошибки | `Result` для бизнес-ошибок, исключения для системных |

## Чек-лист установки

Установщик должен проверить права, выполнить установку внутри `try/catch`, зарегистрировать модуль, создать таблицы, применить миграции, зарегистрировать события, добавить агентов, скопировать административные файлы и показать страницу результата установки только после успешного завершения всех шагов. Если любой шаг падает, установщик должен вызвать `rollbackInstall()`, потому что регистрация модуля, таблицы, события, агенты и файлы изменяют разные подсистемы и должны откатываться согласованно ([описание и параметры модуля](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2824), [RegisterModule](https://dev.1c-bitrix.ru/api_help/main/functions/module/registermodule.php), [UnRegisterModule](https://dev.1c-bitrix.ru/api_help/main/functions/module/unregistermodule.php), [EventManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/EventManager/index.php), [CAgent::AddAgent](https://dev.1c-bitrix.ru/api_help/main/reference/cagent/addagent.php)).

1. Проверить право администратора.
2. Запустить `try/catch` вокруг всех изменяющих действий.
3. Вызвать `RegisterModule($this->MODULE_ID)` и зафиксировать, что регистрация выполнена.
4. Создать только отсутствующие таблицы через миграции или `InstallDB()` и запомнить, какие таблицы реально созданы в этой попытке.
5. Перед регистрацией событий снять возможные старые обработчики, затем зарегистрировать обработчики через `EventManager`.
6. Перед регистрацией агентов удалить возможные агенты модуля, затем зарегистрировать агенты через `CAgent::AddAgent()`.
7. Скопировать административные файлы, компоненты, JS, CSS и публичные файлы, если они нужны.
8. При ошибке вызвать `rollbackInstall()` и откатить только то, что было создано этой попыткой установки.
9. Показать `step.php` через `$APPLICATION->IncludeAdminFile()` только после успешной установки.

## Чек-лист удаления

Удаление должно снять агенты, снять события, удалить файлы, опционально удалить таблицы и затем вызвать `UnRegisterModule($this->MODULE_ID)`. Нужно явно объяснять пользователю, что `UnRegisterModule()` удаляет регистрационную запись и все настройки модуля из базы данных, поэтому «сохранить данные» не равно «сохранить таблицы, настройки и файлы одновременно» ([описание и параметры модуля](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2824), [UnRegisterModule](https://dev.1c-bitrix.ru/api_help/main/functions/module/unregistermodule.php)).

1. Спросить пользователя отдельно про `save_tables`, `save_options` и `save_files`.
2. Удалить агенты модуля.
3. Снять зарегистрированные события.
4. Если `save_files !== 'Y'`, удалить административные и публичные файлы модуля.
5. Если `save_options === 'Y'`, экспортировать настройки до вызова `UnRegisterModule()`.
6. Если `save_tables !== 'Y'`, удалить таблицы и служебные данные.
7. Вызвать `UnRegisterModule($this->MODULE_ID)`.
8. Показать `unstep.php` через `$APPLICATION->IncludeAdminFile()`.

## Тестовый чек-лист

Боевой модуль нужно проверять не только по факту появления в списке модулей, а по устойчивости полного жизненного цикла. Минимальный набор тестов должен покрывать чистую установку, повторную установку после удаления, удаление с сохранением таблиц, удаление без сохранения таблиц, отсутствие дублей агентов, отсутствие дублей событий, права `D`, `R` и `W`, CSRF-проверку всех POST-действий, миграции при обновлении версии и rollback при ошибке на середине установки ([установка и удаление модулей](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=3475), [EventManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/EventManager/index.php), [CAgent::AddAgent](https://dev.1c-bitrix.ru/api_help/main/reference/cagent/addagent.php), [CMain::GetUserRight](https://dev.1c-bitrix.ru/api_help/main/reference/cmain/getuserright.php)).

1. Установка на чистую систему.
2. Повторная установка после удаления.
3. Удаление с `save_tables=Y`.
4. Удаление с `save_tables=N`.
5. Проверка, что агенты не дублируются после повторной установки.
6. Проверка, что события не дублируются после повторной установки.
7. Проверка прав `D`, `R` и `W` в списках, формах, AJAX и сервисах записи.
8. Проверка `check_bitrix_sessid()` на всех POST-действиях.
9. Проверка миграций при обновлении версии, например с `1.0.0` до `1.1.0`.
10. Проверка rollback при искусственной ошибке после создания таблиц, после регистрации событий, после регистрации агентов и после копирования файлов.

После установки нужен smoke-тест: подключить модуль через `Loader::includeModule()`, открыть административный список с правом `R`, выполнить тестовое создание/изменение/удаление записи с правом `W`, проверить, что агент зарегистрирован один раз, события зарегистрированы один раз, настройки читаются из `Option`, лог последней ошибки пуст или содержит ожидаемую тестовую ошибку, а удаление с разными флагами сохраняет ровно те данные, которые обещаны пользователю.

## Промпт-правила для локальной модели

Локальная модель при генерации Bitrix24-модуля должна всегда выбирать D7/ORM для новой логики, использовать `/local/modules`, создавать установщик только как совместимый слой `CModule`, выносить бизнес-логику в `lib/`, описывать таблицы через `DataManager`, регистрировать события через `EventManager`, хранить настройки через `Option`, проверять права через `GetUserRight()` и возвращать ошибки через `Result` либо исключения D7. Эти правила являются синтезом официальных механизмов Bitrix Framework для модулей, Loader, ORM, событий, настроек и прав ([описание и параметры модуля](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2824), [Loader](https://dev.1c-bitrix.ru/api_d7/bitrix/main/loader/index.php), [DataManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/datamanager/index.php), [EventManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/EventManager/index.php), [Option::set](https://dev.1c-bitrix.ru/api_d7/bitrix/main/config/option/set.php), [CMain::GetUserRight](https://dev.1c-bitrix.ru/api_help/main/reference/cmain/getuserright.php)).

Если пользователь просит «создать модуль», модель должна сначала проверить, какие требования уже даны в ТЗ, и не спрашивать их повторно. Уточнять нужно только отсутствующие критические параметры: ID модуля, назначение, таблицы, права, настройки, события, агенты, административные страницы, компоненты, целевую СУБД, версию `main`, обязательные модули-зависимости и требования к сохранению данных при удалении. Если данных достаточно для типового каркаса, нужно генерировать код с явно перечисленными принятыми допущениями, а не останавливать работу лишними вопросами ([описание и параметры модуля](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2824), [ORM-концепции](https://docs.1c-bitrix.ru/pages/orm/orm-concepts.html)).

Если модель генерирует код, она должна сразу выдавать структуру файлов, содержимое `install/index.php`, `install/version.php`, `include.php`, `default_option.php`, `options.php`, ORM-классы, сервисы, обработчики событий, агенты и пример административной страницы. Это практический минимум для типового расширяемого каркаса модуля, основанный на официальных точках расширения модуля и D7 ([описание и параметры модуля](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2824), [DataManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/datamanager/index.php), [EventManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/EventManager/index.php), [main.ui.grid и main.ui.filter](https://dev.1c-bitrix.ru/api_d7/bitrix/main/systemcomponents/gridandfilter/index.php)).

Если модель изменяет уже существующий документ или код модуля, она должна сохранять уже принятые архитектурные решения, если они не противоречат безопасности и совместимости Bitrix. Новые правки нужно вносить точечно: не удалять матрицу совместимости, rollback, проверку прав, проверку результатов файловых операций, backup/restore, стоп-лист и тестовые чек-листы без явной причины.

Если модель видит старые API вроде `CModule`, `RegisterModule`, `UnRegisterModule` или `CAgent`, она не должна автоматически считать их ошибкой, потому что эти API остаются частью жизненного цикла модуля и агентов; модель должна пометить их как совместимые legacy-точки ядра и не переносить на них прикладную бизнес-логику ([CModule](https://dev.1c-bitrix.ru/api_help/main/reference/cmodule/index.php), [RegisterModule](https://dev.1c-bitrix.ru/api_help/main/functions/module/registermodule.php), [UnRegisterModule](https://dev.1c-bitrix.ru/api_help/main/functions/module/unregistermodule.php), [CAgent::AddAgent](https://dev.1c-bitrix.ru/api_help/main/reference/cagent/addagent.php)).

## Минимальный эталон готовности

Модуль можно считать готовым к первому тесту, если он устанавливается и удаляется из административного интерфейса, регистрируется через `RegisterModule`, удаляется через `UnRegisterModule`, создает таблицы идемпотентно, подключается через `Loader::includeModule`, имеет ORM-классы, проверяет права, сохраняет настройки через `Option`, регистрирует и снимает события, регистрирует и удаляет агенты, а все CRUD-операции проверяют `Result::isSuccess()` ([установка и удаление модулей](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=3475), [RegisterModule](https://dev.1c-bitrix.ru/api_help/main/functions/module/registermodule.php), [UnRegisterModule](https://dev.1c-bitrix.ru/api_help/main/functions/module/unregistermodule.php), [Loader](https://dev.1c-bitrix.ru/api_d7/bitrix/main/loader/index.php), [DataManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/entity/datamanager/index.php), [Option::set](https://dev.1c-bitrix.ru/api_d7/bitrix/main/config/option/set.php), [EventManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/EventManager/index.php), [CAgent::AddAgent](https://dev.1c-bitrix.ru/api_help/main/reference/cagent/addagent.php)).

Модуль можно считать готовым к промышленному использованию только после проверки миграций на чистой базе и базе с существующими данными, проверки удаления с сохранением и без сохранения данных, проверки прав для разных групп, проверки административных страниц, проверки событий, проверки агентов, проверки повторной установки, проверки обновления версии и проверки ошибок ORM. Это практический критерий качества, основанный на жизненном цикле установки, D7-операциях, правах, событиях и фоновых задачах Bitrix ([описание и параметры модуля](https://dev.1c-bitrix.ru/learning/course/index.php?COURSE_ID=43&LESSON_ID=2824), [операции с сущностями](https://docs.1c-bitrix.ru/pages/orm/entity-operations.html), [CMain::GetUserRight](https://dev.1c-bitrix.ru/api_help/main/reference/cmain/getuserright.php), [EventManager](https://dev.1c-bitrix.ru/api_d7/bitrix/main/EventManager/index.php), [CAgent::AddAgent](https://dev.1c-bitrix.ru/api_help/main/reference/cagent/addagent.php)).
