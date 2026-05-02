# uds.ideabank2 ORM Compatibility Rules

Эти правила зафиксированы на основе реальных ошибок, выявленных при запуске модуля uds.ideabank2 на коробочной Bitrix24 с актуальной версией D7.

## Проблема 1: Bitrix\Main\ORM\Index недоступен

**Симптом:** `Class "Bitrix\Main\ORM\Index" not found` при инициализации ORM-сущности.

**Причина:** Класс `Bitrix\Main\ORM\Index` отсутствует в используемой версии Bitrix D7. Индексы нельзя описывать в `getMap()` ORM-таблицы.

**Правило:**
- Никогда не используйте `new Index(...)` в `getMap()` ORM-таблиц.
- Никогда не импортируйте `use Bitrix\Main\ORM\Index;` в файлы Table.
- Индексы БД создавайте через SQL-миграции в `InstallDB()` установщика или отдельные миграционные скрипты.

**Затронутые файлы (исправлено):**
- `lib/Uds/Ideabank2/Table/IdeaTable.php` — удалены `Index('idx_user')`, `Index('idx_status')`, `Index('idx_category')`
- `lib/Uds/Ideabank2/Table/IdeaCoinTable.php` — удалены `Index('idx_user')`, `Index('idx_idea')`

## Проблема 2: ORM не поддерживает SQL-агрегации в select

**Симптом:** `Unknown field definition 'SUM(COINS)'` или `Unknown field definition 'COUNT(CASE WHEN ...)'` при вызове `getList()` с агрегатными функциями в `select`.

**Причина:** Bitrix ORM не поддерживает сложные SQL-выражения (SUM, COUNT с CASE, AVG и т.д.) в параметре `select` метода `getList()`. Формат `['ALIAS' => 'SQL_EXPRESSION']` интерпретируется как определение нового поля сущности, а не как SQL-выражение.

**Правило:**
- Никогда не используйте SQL-агрегации (SUM, COUNT, MAX, MIN, AVG) в `select` ORM `getList()`.
- Никогда не используйте `ExpressionField` с параметризованными placeholder'ами (`%d`, `%s`) — ORM требует строгие типы и может выбросить `ArgumentException`.
- Для агрегаций и сложных SQL-выражений используйте raw SQL через `Application::getConnection()->query()`.

**Шаблон правильного подхода:**

```php
// ПЛОХО — вызовет SystemException:
$row = SomeTable::getList([
    'select' => ['TOTAL' => 'SUM(COINS)'],
    'filter' => ['=USER_ID' => $userId],
])->fetch();

// ХОРОШО — raw SQL:
$connection = \Bitrix\Main\Application::getConnection();
$sqlHelper = $connection->getSqlHelper();
$tableName = $sqlHelper->quote(SomeTable::getTableName());

$result = $connection->query("
    SELECT COALESCE(SUM(COINS), 0) AS TOTAL
    FROM {$tableName}
    WHERE USER_ID = " . (int)$userId . "
");
$row = $result->fetch();
```

**Затронутые файлы (исправлено):**
- `lib/Uds/Ideabank2/Domain/IdeaService.php` — `getStats()` переписан на raw SQL
- `lib/Uds/Ideabank2/Domain/CoinService.php` — `getBalance()` и `getLeaderboard()` переписаны на raw SQL

## Checklist при создании нового Table класса

1. Не используйте `Index` в `getMap()` — создавайте индексы через SQL-миграции.
2. Не используйте `ExpressionField` — используйте raw SQL для сложных выражений.
3. После создания файла выполните `php -l` для проверки синтаксиса.

## Checklist при использовании агрегаций

1. Если нужна агрегация (COUNT, SUM, GROUP BY) — используйте `Connection::query()` напрямую.
2. Всегда кавычьте имена таблиц через `$sqlHelper->quote()`.
3. Всегда приводите пользовательские ID к `(int)` перед конкатенацией в SQL.
4. Для `SUM()` используйте `COALESCE(SUM(...), 0)` чтобы избежать NULL.

## Затронутые файлы

| Файл | Исправление |
|------|------------|
| `lib/Uds/Ideabank2/Table/IdeaTable.php` | Удалены 3 Index из getMap() |
| `lib/Uds/Ideabank2/Table/IdeaCoinTable.php` | Удалены 2 Index из getMap() |
| `lib/Uds/Ideabank2/Domain/IdeaService.php` | getStats() → raw SQL через Connection::query() |
| `lib/Uds/Ideabank2/Domain/CoinService.php` | getBalance(), getLeaderboard() → raw SQL |

## Верификация

Все 45 PHP-файлов модуля прошли `php -l` без ошибок.
Все PHP-файлы компонента `uds.ideabank` прошли `php -l` без ошибок.
Страница `/ideabank/` загружается без фатальных PHP ошибок.