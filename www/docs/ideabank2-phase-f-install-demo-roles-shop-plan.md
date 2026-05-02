# Фаза F: установка, демо-данные, роли, настройки, магазин и E2E `uds.ideabank2`

## 1. Цель

Подготовить модуль `uds.ideabank2` к воспроизводимой демонстрации и приёмке: установка модуля должна создавать базовую инфраструктуру, стандартные роли, настройки, опциональные тестовые данные и, при необходимости, dev-инструменты для E2E-проверки пользовательских сценариев.

Фаза F выполняется отдельно от миграции публичных шаблонов, чтобы не раздувать общий контекст и не смешивать UI-миграцию с install/demo/shop-задачами.

---

## 2. Базовые правила

1. Не изменять ядро Bitrix: запрещены правки `/bitrix/modules`, `/bitrix/components/bitrix`, `/bitrix/js`, `/bitrix/admin` кроме штатного копирования admin-прокси файлов модуля при установке.
2. Основная логика — в `/local/modules/uds.ideabank2/lib`.
3. UI — в `/local/components/uds/*` и публичных страницах `/ideabank/*.php`.
4. Установщик должен быть идемпотентным: повторная установка не создаёт дубли групп, демо-данных, агентов, событий и настроек.
5. Демо-данные должны иметь явные маркеры `XML_ID`/`CODE` вида `DEMO_*`.
6. Debug-инструменты разрешены только для dev/test и должны быть выключены по умолчанию.
7. Секреты, реальные user ID и production-данные не хардкодить.
8. Любые POST-действия — только с CSRF-проверкой Bitrix.
9. После PHP-правок обязательно выполнять `php -l` для изменённых файлов.
10. После install/runtime-правок выполнять smoke/E2E на `http://localhost:8588`.

---

## 3. Chunk-план

## F0. Ревизия текущего модуля

### Цель

Понять фактическую структуру `uds.ideabank2` перед install/demo/shop-изменениями.

### Проверить

- `install/index.php`, `install/version.php`, install/uninstall flow.
- ORM Table-классы.
- Доменные сервисы: идеи, коины, статусы, категории, награды, конкурсы, челленджи.
- Существующие admin-страницы.
- Публичные компоненты `uds:ideabank.*`.
- Текущие права и проверки доступа.
- Наличие/отсутствие сущностей магазина.

### Результат

Короткий технический отчёт: что уже есть, чего нет, какие классы/таблицы использовать в F1–F9.

### DoD

- [x] Зафиксирован список существующих таблиц и сервисов.
- [x] Зафиксированы риски install/uninstall.
- [x] Нет изменений кода, кроме документации при необходимости.

### Статус F0 от 2026-04-30

Ревизия выполнена по текущим файлам модуля `local/modules/uds.ideabank2`, публичным компонентам `local/components/uds/ideabank.*` и плану публичной миграции. На момент ревизии фаза публичной миграции `E1` зафиксирована как завершённая; фаза F начинается с уже частично подготовленной инфраструктурой настроек и групп.

#### Что уже есть в модуле

- Установщик: `local/modules/uds.ideabank2/install/index.php`.
- Версия модуля: `local/modules/uds.ideabank2/install/version.php`.
- Автозагрузка классов: `local/modules/uds.ideabank2/include.php` через `Loader::registerAutoLoadClasses()`.
- Настройки по умолчанию: `local/modules/uds.ideabank2/default_option.php`.
- Административная страница настроек: `local/modules/uds.ideabank2/options.php`.
- Централизованные helper-классы настроек:
  - `Uds\Ideabank2\Config\ModuleOptions`;
  - `Uds\Ideabank2\Config\Feature`.
- Seed/demo-слой: `Uds\Ideabank2\Seed\DemoDataSeeder` и CLI-точка `local/modules/uds.ideabank2/tools/seed_demo.php`.
- Публичный data/view-model слой: `Uds\Ideabank2\Domain\PublicDataService`.
- События модуля: `Uds\Ideabank2\Events\IdeaEvents`, `Uds\Ideabank2\Events\CoinEvents`.
- Admin UI модуля: `local/modules/uds.ideabank2/admin/*.php`.
- Публичные компоненты после миграции `B1–E1`:
  - `uds:ideabank.home`;
  - `uds:ideabank.idea.list`;
  - `uds:ideabank.idea.detail`;
  - `uds:ideabank.idea.form`;
  - `uds:ideabank.news.list`;
  - `uds:ideabank.news.detail`;
  - `uds:ideabank.contest.list`;
  - `uds:ideabank.contest.detail`;
  - `uds:ideabank.docs`;
  - `uds:ideabank.hall`;
  - `uds:ideabank.stats`.

#### Существующие таблицы и ORM-классы

Текущий install-flow создаёт следующие таблицы и для них уже есть ORM `Table`-классы:

| Таблица | ORM-класс |
|---|---|
| `b_uds_ideabank_idea` | `Uds\Ideabank2\Table\IdeaTable` |
| `b_uds_ideabank_idea_author` | `Uds\Ideabank2\Table\IdeaAuthorTable` |
| `b_uds_ideabank_idea_status` | `Uds\Ideabank2\Table\IdeaStatusTable` |
| `b_uds_ideabank_idea_category` | `Uds\Ideabank2\Table\IdeaCategoryTable` |
| `b_uds_ideabank_idea_reaction` | `Uds\Ideabank2\Table\IdeaReactionTable` |
| `b_uds_ideabank_idea_comment` | `Uds\Ideabank2\Table\IdeaCommentTable` |
| `b_uds_ideabank_idea_workflow` | `Uds\Ideabank2\Table\IdeaWorkflowTable` |
| `b_uds_ideabank_idea_feedback` | `Uds\Ideabank2\Table\IdeaFeedbackTable` |
| `b_uds_ideabank_idea_expert_review` | `Uds\Ideabank2\Table\IdeaExpertReviewTable` |
| `b_uds_ideabank_idea_committee` | `Uds\Ideabank2\Table\IdeaCommitteeTable` |
| `b_uds_ideabank_idea_coin` | `Uds\Ideabank2\Table\IdeaCoinTable` |
| `b_uds_ideabank_idea_contest` | `Uds\Ideabank2\Table\IdeaContestTable` |
| `b_uds_ideabank_idea_contest_part` | `Uds\Ideabank2\Table\IdeaContestPartTable` |
| `b_uds_ideabank_idea_challenge` | `Uds\Ideabank2\Table\IdeaChallengeTable` |
| `b_uds_ideabank_idea_news` | `Uds\Ideabank2\Table\IdeaNewsTable` |
| `b_uds_ideabank_idea_reward_rule` | `Uds\Ideabank2\Table\IdeaRewardRuleTable` |

Отдельных таблиц магазина пока нет. Для F6/F7 нужно добавить минимум:

- `b_uds_ideabank_shop_item`;
- `b_uds_ideabank_shop_order`;
- ORM-классы `ShopItemTable`, `ShopOrderTable`;
- сервис `Uds\Ideabank2\Domain\ShopService`;
- публичный компонент `uds:ideabank.shop` и страницу `/ideabank/shop.php`.

#### Существующие доменные сервисы

| Сервис | Назначение для следующих chunks |
|---|---|
| `IdeaService` | CRUD идей, отправка, модерация, реакции, комментарии, статистика. Использовать как основной доменный слой для F5/F9. |
| `CoinService` | Начисления, баланс, история, leaderboard. Использовать для F6/F7 при покупке за коины; списание покупки нужно добавить отдельным событием/операцией. |
| `ContestService` | CRUD конкурсов и участие. Использовать для demo data и admin/runtime проверок. |
| `ChallengeService` | CRUD челленджей. Использовать для demo data. |
| `ExpertReviewService` | Экспертные оценки. Использовать для F5/F9 ролей экспертов. |
| `CommitteeService` | Решения комитета. Использовать для F5/F9 ролей комитета. |
| `NotificationService` | Уведомления по статусам/новым идеям. Не расширять без отдельной проверки PII/логирования. |
| `TaskIntegrationService` | Интеграция с задачами. Не трогать в F1–F4 без отдельного ТЗ. |
| `PublicDataService` | Сбор данных, `features` и `permissions` для публичного UI. Использовать как точку расширения для F5/F6. |

#### Что уже частично закрывает F1/F2

- В `default_option.php` уже есть `feature_*`, `shop_*`, `debug_auth_*` и `group_*_id` опции.
- `ModuleOptions::getFeatures()` и `Feature::all()/isEnabled()` уже реализованы.
- `options.php` уже позволяет редактировать feature flags, лимиты магазина, whitelist `debug_auth_allowed_user_ids` и показывает ID групп ролей.
- `install/index.php::installGroups()` уже создаёт 5 базовых групп ролей с `STRING_ID`:
  - `UDS_IDEABANK2_PARTICIPANTS`;
  - `UDS_IDEABANK2_MODERATORS`;
  - `UDS_IDEABANK2_EXPERTS`;
  - `UDS_IDEABANK2_COMMITTEE`;
  - `UDS_IDEABANK2_ADMINS`.
- `PublicDataService` уже отдаёт `shell.features`, `shell.permissions` и `meta.features/meta.permissions`.

#### Риски install/uninstall

1. `installSeedData()` запускается всегда при установке и создаёт только базовые справочники; полноценные demo data из `DemoDataSeeder` теперь запускаются отдельно по явному флагу `install_demo_data=Y` или через dev CLI.
2. В `DoInstall()` пока нет отдельного `InstallAgents()`: агентов в модуле не обнаружено. Для F8 нужно либо явно зафиксировать, что агенты не нужны, либо добавить idempotent install/uninstall агентов.
3. `rollbackInstall()` снимает admin-файлы/события/регистрацию модуля, но не откатывает созданные таблицы, опции и группы. Для F8 нужен аккуратный rollback policy без удаления реальных данных.
4. Uninstall сейчас использует один режим `uds_ideabank2_delete_mode=full|keep`; для F8 нужно перейти к явным флагам `save_tables`, `save_options`, `save_groups`, `save_demo_data`, `remove_debug_tools`.
5. Группы ролей по умолчанию не удаляются, что безопасно для production; удаление групп допустимо только отдельным dev/test флагом и после проверки состава пользователей.
6. Admin-файлы копируются в `/bitrix/admin` штатным `CopyDirFiles()` из `/local/modules/.../admin`; это не считается ручной правкой ядра, но имена файлов должны оставаться с уникальным префиксом `ideabank`/`uds_ideabank2`.
7. В `DemoDataSeeder` идеи переведены на стабильные `CODE=DEMO_IDEA_*`; для таблиц без `CODE`/`XML_ID` до добавления схемных маркеров сохраняется идемпотентность по существующим уникальным display-полям.
8. В таблицах текущих сущностей не везде есть `XML_ID`; для новых сущностей магазина в F6 нужно сразу добавить `XML_ID` и уникальный индекс на уровне install SQL, а не через ORM `Index`.

#### Рекомендуемый следующий chunk

Следующий безопасный шаг — закрыть F1/F2 как уже частично реализованные: выполнить точечную стабилизацию install/options, добавить self-check для групп/настроек и зафиксировать uninstall policy без удаления реальных групп. После этого переходить к F3/F4.

---

## F1. Стандартные группы пользователей для ролей модуля

### Цель

Добавить в установку модуля создание стандартных групп пользователей под роли Идеабанка.

### Базовые группы

1. `Идеабанк: участники`.
2. `Идеабанк: модераторы`.
3. `Идеабанк: эксперты`.
4. `Идеабанк: комитет`.
5. `Идеабанк: администраторы`.

### Технический подход

- Добавить install-метод, например `InstallGroups()`.
- Искать существующие группы по стабильному `STRING_ID`/`XML_ID`/коду, чтобы не создавать дубли.
- Сохранять ID групп в `Option`:
  - `group_participants_id`,
  - `group_moderators_id`,
  - `group_experts_id`,
  - `group_committee_id`,
  - `group_admins_id`.
- На uninstall по умолчанию не удалять группы, если в них могут быть реальные пользователи.
- Добавить отдельный флаг удаления групп только для dev/test или явного выбора администратора.

### DoD

- [x] Повторная установка не создаёт дубли групп.
- [x] ID групп сохранены в настройках модуля.
- [x] Uninstall policy явно описана.
- [x] Добавлен self-check групп ролей на странице настроек.
- [x] `php -l` изменённых PHP-файлов пройден.

### Статус F1 от 2026-04-30

F1 закрыт точечной стабилизацией без изменения install-flow создания групп:

- `installGroups()` уже идемпотентно ищет группы по `STRING_ID` и сохраняет ID в `Option`.
- На странице настроек добавлен блок `Self-check install/options`, который проверяет сохранённые `group_*_id`, наличие групп в `b_group` и соответствие ожидаемым `STRING_ID`.
- Uninstall policy остаётся безопасной: реальные группы ролей по умолчанию не удаляются; удаление групп допустимо только отдельным dev/test-флагом в следующих chunks.
- Проверка синтаксиса выполнена в контейнере `dev_php` для изменённых PHP-файлов.

---

## F2. Настройки и feature flags

### Цель

Сделать централизованный блок настроек, через который можно включать/отключать публичные и доменные возможности.

### Предлагаемые файлы

```text
/local/modules/uds.ideabank2/default_option.php
/local/modules/uds.ideabank2/options.php
/local/modules/uds.ideabank2/lib/Config/ModuleOptions.php
/local/modules/uds.ideabank2/lib/Config/Feature.php
```

### Группы настроек

#### Публичный контур

- `feature_public_home`.
- `feature_public_news`.
- `feature_public_contests`.
- `feature_public_docs`.
- `feature_public_stats`.
- `feature_public_hall`.
- `feature_public_idea_detail`.
- `feature_public_idea_form`.

#### Workflow идей

- `feature_moderation`.
- `feature_expertise`.
- `feature_committee`.
- `feature_drafts`.
- `feature_edit_after_submit`.
- `feature_comments`.
- `feature_reactions`.
- `feature_voting`.

#### Коины и награды

- `feature_coins`.
- `feature_auto_coin_accrual`.
- `feature_manual_coin_accrual`.
- `feature_rewards`.
- `feature_leaderboard`.

#### Корпоративный магазин

- `feature_shop`.
- `feature_shop_orders`.
- `feature_shop_order_moderation`.
- `feature_shop_cancel_order`.
- `shop_monthly_limit`.
- `shop_min_balance_after_purchase`.

#### Demo/debug

- `feature_demo_data`.
- `debug_auth_enabled`.
- `debug_auth_allowed_user_ids`.

### Правило для компонентов

Компоненты собирают `features` и `permissions` в контракт:

```php
$arResult['meta']['features'] = [...];
$arResult['meta']['permissions'] = [...];
```

Шаблоны только отображают/скрывают готовые блоки и не читают `Option` напрямую.

### DoD

- [x] Настройки доступны через страницу настроек модуля.
- [x] Есть helper для чтения feature flags.
- [x] Публичные компоненты могут получать единый массив `features`.
- [x] Добавлен self-check feature flags/debug_auth на странице настроек.
- [x] `php -l` изменённых PHP-файлов пройден.

### Статус F2 от 2026-04-30

F2 закрыт точечной стабилизацией существующих настроек:

- `ModuleOptions` получил единый список role group options и безопасные read-only accessors для списков boolean/integer options.
- Новый `Uds\Ideabank2\Config\SelfCheck` проверяет наличие feature flags в `Option`, группы ролей и состояние `debug_auth_enabled`/whitelist.
- `options.php` выводит self-check без мутаций данных: это диагностика install/options, а не автоматический repair.
- Значения feature flags по-прежнему редактируются централизованно через `ModuleOptions`, публичные компоненты получают готовый массив features через доменный слой.
- Проверка синтаксиса выполнена в контейнере `dev_php` для изменённых PHP-файлов.

---

## F3. Демо-данные в установке модуля

### Цель

Добавить опциональную установку тестовых данных для демонстрации и E2E.

### Предлагаемые классы

```text
/local/modules/uds.ideabank2/lib/Demo/DemoDataInstaller.php
/local/modules/uds.ideabank2/lib/Demo/DemoDataCleaner.php
```

### Что создавать

- Категории идей.
- Бизнес-направления.
- Статусы workflow.
- Идеи в разных состояниях:
  - черновик,
  - на модерации,
  - на экспертизе,
  - на комитете,
  - утверждена,
  - отклонена,
  - внедрена.
- Комментарии и обсуждения.
- Реакции/голоса, если включены.
- Экспертные оценки.
- Решения комитета.
- Начисления коинов.
- Награды/бейджи.
- Конкурсы, челленджи, новости, документы.
- Демо-товары магазина после реализации F6.

### Install policy

- Демо-данные устанавливаются только при явном флаге `install_demo_data=Y`.
- Повторный запуск обновляет/пропускает демо-сущности, а не создаёт дубли.
- Удаление демо-данных — отдельное действие/флаг.

### DoD

- [x] Демо-данные создаются идемпотентно.
- [x] Основные demo-идеи помечены стабильными `CODE=DEMO_IDEA_*`.
- [x] Demo install выполняется только по явному `install_demo_data=Y` или через CLI-точку `tools/seed_demo.php`.
- [x] Реальные данные не удаляются.
- [x] `php -l` изменённых PHP-файлов пройден.

### Статус F3 от 2026-04-30

F3 закрыт безопасной точечной стабилизацией текущего demo seed-flow:

- `DoInstall()` больше не запускает полноценный `DemoDataSeeder` автоматически; базовые справочники статусов/категорий/правил наград остаются частью обязательной установки.
- Добавлен явный opt-in `install_demo_data=Y`: только при этом флаге установщик вызывает `installDemoData()` и включает `feature_demo_data=Y`.
- CLI-точка `local/modules/uds.ideabank2/tools/seed_demo.php` сохранена как явный dev/test способ наполнить демо-данные без автоматических мутаций при обычной установке.
- Demo-коды идей заменены с прототипных `№2830xx` на стабильные `DEMO_IDEA_*`; идемпотентность по идеям теперь опирается на эти маркеры в поле `CODE`.
- Для связанных записей, комментариев, реакций, workflow, feedback, экспертиз, решений комитета и коинов сохраняется безопасная политика: они создаются только при первом создании соответствующей demo-идеи, поэтому повторный запуск не плодит дубли.
- Для новостей, конкурсов и челленджей текущие таблицы не имеют `CODE`/`XML_ID`; в F3 не менялась схема БД, поэтому идемпотентность пока сохранена по `TITLE`. При F6/F7 для новых сущностей магазина нужно сразу добавлять `XML_ID=DEMO_SHOP_*`.
- Проверка синтаксиса выполнена в контейнере `dev_php` для изменённых PHP-файлов.

---

## F4. Временный защищённый `debug_auth.php`

### Цель

Ускорить E2E-проверку ролей через штатную авторизацию Bitrix:

```php
$USER->Authorize($userId);
```

### Предлагаемый маршрут

```text
/ideabank/debug_auth.php
```

Файл временный и не должен быть включён в production без явного решения.

### Обязательные защиты

1. Работает только если `debug_auth_enabled=Y`.
2. Работает только в dev/test режиме.
3. Принимает только пользователей из whitelist `debug_auth_allowed_user_ids`.
4. Не позволяет авторизоваться под произвольным `user_id`.
5. Не логирует пароли, сессии, токены, персональные данные.
6. После авторизации делает redirect на безопасный URL `/ideabank/` или URL из whitelist.
7. В документации явно помечен как временный dev-only инструмент.

### DoD

- [x] Авторизация работает для разрешённых demo user IDs.
- [x] Для неразрешённых ID возвращается отказ.
- [x] При выключенной настройке файл не авторизует никого.
- [x] `php -l` файла пройден.
- [x] Browser/E2E smoke выполнен.

### Статус F4 от 2026-04-30

F4 закрыт добавлением защищённого dev-only маршрута `ideabank/debug_auth.php` и сервисного helper-класса `Uds\Ideabank2\Debug\DebugAuth`:

- `debug_auth.php` не авторизует никого, если `debug_auth_enabled=N`.
- Инструмент работает только на локальных dev/test hostnames `localhost`, `127.0.0.1`, `dev.bx` или при явном окружении `UDS_IDEABANK2_DEBUG_AUTH=Y`.
- Авторизация возможна только POST-запросом с `check_bitrix_sessid()` и только для ID из `debug_auth_allowed_user_ids`.
- Redirect ограничен безопасным контуром `/ideabank/`; внешние URL и protocol-relative URL отбрасываются в `/ideabank/`.
- Пароли, токены и персональные данные не принимаются и не логируются.
- Класс `DebugAuth` зарегистрирован в автозагрузке модуля через `include.php`.
- Проверки выполнены: `docker exec dev_php php -l` для изменённых PHP-файлов прошёл без ошибок, `curl http://localhost:8588/ideabank/debug_auth.php` вернул HTTP 200, страница открыта через browser_action без console errors.

---

## F5. Правка публичных блоков под `features` и `permissions`

### Цель

Сделать публичный UI управляемым настройками и правами ролей.

### Блоки

#### Главная

- Показывать блоки по `features`.
- Отображать разные действия для участника, модератора, эксперта, комитета, администратора.

#### Реестр идей

- Фильтры и быстрые режимы по ролям.
- Действия в карточках только по правам.
- Empty-state с понятными CTA.

#### Карточка идеи

- Блок модерации только модераторам/админам.
- Блок экспертизы только экспертам/админам.
- Блок комитета только комитету/админам.
- Комментарии/реакции/голосование по feature flags.

#### Форма идеи

- Создание/редактирование по правам.
- Черновики по feature flag.
- Подсказки и checklist из view-model.

#### Статистика и аллея славы

- Учитывать включение коинов, рейтинга, наград.

### DoD

- [x] Шаблоны не содержат бизнес-логики доступа.
- [x] Компоненты передают `features`, `permissions`, `actions` в контракте.
- [x] Runtime smoke публичных страниц пройден.

### Статус F5 от 2026-04-30

F5 закрыт точечной стабилизацией публичного контракта `features/permissions` без переноса бизнес-логики в шаблоны:

- `PublicDataService::getCurrentUserPermissions()` теперь учитывает не только группы ролей, но и feature flags `feature_moderation`, `feature_expertise`, `feature_committee`, `feature_public_idea_form`, `feature_drafts`, `feature_edit_after_submit`, `feature_coins`, `feature_rewards`, `feature_leaderboard`, `feature_shop`.
- `getIdeaListData()` нормализует недоступные режимы: `mode=moderation` не отдаётся пользователям без права модерации, `mode=drafts` отключается при выключенных черновиках.
- `getIdeaDetailData()` больше не подготавливает комментарии, реакции, экспертные оценки, решения комитета и обратную связь, если соответствующие features/permissions выключены.
- `getHomeData()` не загружает коиновый/рейтинговый/новостной/конкурсный контент при выключенных feature flags.
- Шаблон главной страницы только отображает уже подготовленные `features`/`permissions`: скрывает блоки коинов, истории начислений, рейтинга, новостей, челленджей и шагов подачи идеи, когда контракт говорит, что они недоступны.
- Проверки выполнены: `docker exec dev_php php -l` для изменённых PHP-файлов прошёл без ошибок; `curl` для `/ideabank/`, `/ideabank/management.php?mode=moderation`, `/ideabank/ppu-form.php` вернул HTTP 200; главная страница открыта через `browser_action` без console errors.

---

## F6. MVP корпоративного магазина

### Цель

Добавить корпоративный магазин, где пользователь может покупать товары за коины.

### Минимальные сущности

#### Товары магазина

- `ID`.
- `XML_ID`.
- `NAME`.
- `DESCRIPTION`.
- `PRICE_COINS`.
- `QUANTITY`.
- `ACTIVE`.
- `SORT`.
- `IMAGE_ID`, если нужно.
- `CREATED_AT`.
- `UPDATED_AT`.

#### Заказы магазина

- `ID`.
- `USER_ID`.
- `ITEM_ID`.
- `PRICE_COINS`.
- `STATUS`.
- `COMMENT`.
- `CREATED_AT`.
- `UPDATED_AT`.

### Сервис

```text
/local/modules/uds.ideabank2/lib/Domain/ShopService.php
```

Методы:

- `getCatalog()`.
- `canBuy(int $userId, int $itemId)`.
- `buy(int $userId, int $itemId)`.
- `getUserOrders(int $userId)`.
- `cancelOrder(int $userId, int $orderId)`, если включено настройкой.

### UI

```text
/ideabank/shop.php
/local/components/uds/ideabank.shop/
```

### Важные требования

- Покупка выполняется транзакционно.
- Баланс проверяется перед списанием.
- Списание коинов и создание заказа должны быть атомарными.
- Нельзя уходить в отрицательный баланс, если это не разрешено настройкой.
- Все ошибки возвращаются через `Result`/контролируемые сообщения.

### DoD

- Пользователь видит каталог и свой баланс.
- Пользователь может купить товар при достаточном балансе.
- Создаётся заказ и списываются коины.
- Недостаток баланса корректно отображается.
- `php -l` и runtime smoke пройдены.

---

## F7. Демо-данные магазина и история заказов

### Цель

Расширить магазин до демонстрационного сценария.

### Сделать

- Демо-товары с `XML_ID=DEMO_SHOP_*`.
- Начальные коины demo-пользователям.
- История моих заказов.
- Статусы заказов:
  - `NEW`,
  - `APPROVED`,
  - `ISSUED`,
  - `CANCELLED`.
- Если включена модерация заказов — admin/операционный список заказов.

### DoD

- Demo install создаёт товары.
- Участник может купить товар в E2E.
- Заказ виден в истории.
- Баланс меняется корректно.

---

## F8. Единый install/uninstall/self-check процесс

### Цель

Собрать установку модуля, шаблонов, ролей, настроек, демо-данных и проверок в единый управляемый процесс.

### Install flow

1. `checkRights()`.
2. `checkRequiredModules()`.
3. `RegisterModule()`.
4. `InstallDB()`.
5. `InstallOptions()`.
6. `InstallGroups()`.
7. `InstallEvents()`.
8. `InstallAgents()`.
9. `InstallFiles()`.
10. `InstallDemoData()` при `install_demo_data=Y`.
11. `InstallDebugTools()` при `debug_tools=Y`.
12. `SelfCheck()`.

### Uninstall policy

Отдельные флаги:

- `save_tables=Y`.
- `save_options=Y`.
- `save_groups=Y`.
- `save_demo_data=Y`.
- `remove_debug_tools=Y`.

### Self-check

- Таблицы существуют.
- Настройки записаны.
- Группы найдены.
- События не продублированы.
- Агенты не продублированы.
- Публичные страницы/компоненты доступны в файловой системе.
- Демо-данные существуют, если выбран demo install.

### DoD

- Чистая установка проходит.
- Повторная установка после удаления проходит.
- Нет дублей групп/агентов/событий/демо-данных.
- Rollback при ошибке не оставляет модуль в полуустановленном состоянии.

---

## F9. E2E пользовательских сценариев по ролям

### Цель

Проверить, что роли, права, demo data, UI, workflow, коины и магазин работают вместе.

### Участник

1. Авторизоваться как участник.
2. Открыть `/ideabank/`.
3. Создать идею.
4. Сохранить черновик.
5. Отправить идею.
6. Проверить карточку идеи.
7. Проверить баланс коинов.
8. Купить товар в магазине.
9. Проверить историю заказов.

### Модератор

1. Авторизоваться как модератор.
2. Открыть реестр/очередь модерации.
3. Вернуть идею на доработку.
4. Передать идею на экспертизу.

### Эксперт

1. Открыть очередь экспертизы.
2. Заполнить экспертную оценку.
3. Добавить рекомендацию.
4. Передать дальше.

### Комитет

1. Открыть очередь комитета.
2. Принять решение.
3. Назначить награду/коины.
4. Проверить финальный статус идеи.

### Администратор

1. Открыть настройки модуля.
2. Включить/выключить блоки.
3. Проверить реакцию публичного UI на настройки.
4. Проверить магазин и заказы.
5. Проверить демо-данные и группы.

### DoD

- Все сценарии проходят на `http://localhost:8588`.
- Нет фаталов/500.
- Нет новых console errors.
- Права ролей соответствуют ожидаемому поведению.

---

## 4. Рекомендуемый порядок выполнения

1. `F0` — ревизия модуля.
2. `F1` — группы ролей.
3. `F2` — настройки и feature flags.
4. `F3` — demo data installer без магазина.
5. `F4` — защищённый dev-only `debug_auth.php`.
6. `F5` — публичные блоки под `features/permissions`.
7. `F6` — MVP магазина.
8. `F7` — демо-товары, покупка, история заказов.
9. `F8` — единый install/uninstall/self-check.
10. `F9` — E2E по ролям и итоговая документация.

---

## 5. Общий Definition of Done фазы F

- [x] Проведена ревизия текущего модуля.
- [x] Установка создаёт стандартные группы ролей без дублей.
- [x] Настройки и feature flags централизованы.
- [x] Демо-данные устанавливаются опционально и идемпотентно.
- [x] `debug_auth.php` работает только в dev/debug режиме и только по whitelist.
- [x] Публичные блоки управляются `features/permissions`.
- [ ] Корпоративный магазин поддерживает покупку за коины.
- [ ] Install/uninstall/self-check объединены в понятный процесс.
- [ ] E2E по ролям пройден.
- [ ] Документация обновлена.
