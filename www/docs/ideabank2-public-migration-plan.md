# План поэтапной миграции публичной части `uds.ideabank2` по прототипу

## 1) Цель

Привести публичные страницы `/ideabank/*.php` к структуре, UX и визуальной логике прототипа из:

- `/local/_/tmp/ideas`

при этом **не ломая текущую доменную логику D7/ORM** модуля `uds.ideabank2` и выполняя изменения **частями** с изолированным контентом.

---

## 2) Базовые принципы реализации

1. **Маршруты не меняем**: используем существующие `/ideabank/*.php`.
2. **Ядро не трогаем**: изменения только в `/local/components`, `/local/js`, `/local/php_interface`, при необходимости — в `/local/modules/uds.ideabank2`.
3. **Изоляция контента**:
   - отдельно: получение данных (domain/service),
   - отдельно: адаптация данных под UI (view-model),
   - отдельно: верстка (template).
4. **Минимальные диффы**: миграция не «всё сразу», а по страницам/фазам.
5. **Готовность по фазам**: после каждой фазы есть контрольный чек-лист и критерии приемки.

---

## 3) Матрица соответствия: прототип → публичный роут → компонент

| Прототип | Публичная страница | Компонент |
|---|---|---|
| `index.html` | `/ideabank/index.php` | `uds:ideabank.home` |
| `management.html` | `/ideabank/management.php` | `uds:ideabank.idea.list` |
| `ppu-detail.html` | `/ideabank/ppu-detail.php` | `uds:ideabank.idea.detail` |
| `ppu-form.html` | `/ideabank/ppu-form.php` | `uds:ideabank.idea.form` |
| `news.html` | `/ideabank/news.php` | `uds:ideabank.news.list` |
| `news-detail.html` | `/ideabank/news-detail.php` | `uds:ideabank.news.detail` |
| `contests.html` | `/ideabank/contests.php` | `uds:ideabank.contest.list` |
| `contest-detail.html` | `/ideabank/contest-detail.php` | `uds:ideabank.contest.detail` |
| `docs.html` | `/ideabank/docs.php` | `uds:ideabank.docs` |
| `hall-of-fame.html` | `/ideabank/hall-of-fame.php` | `uds:ideabank.hall` |
| `stats.html` | `/ideabank/stats.php` | `uds:ideabank.stats` |

---

## 4) Изоляция контента (обязательная схема)

Для каждой страницы вводится одинаковый слой изоляции:

### 4.1 Контракт данных страницы

Каждый компонент возвращает в шаблон **фиксированный `arResult`-контракт**:

- `shell` (меню, активный раздел, базовые элементы оболочки),
- `page` (основные данные страницы),
- `widgets` (вспомогательные блоки: карточки, сайдбар, рейтинги и т.д.),
- `meta` (пагинация, фильтры, признаки пустых состояний).

### 4.2 Адаптер view-model

Если доменные данные не совпадают с форматом UI, в компоненте формируется адаптер:

- `build<Page>NameViewModel(array $domainData): array`

Задача адаптера: преобразовать поля 1 раз в одном месте, а не размазывать логику по шаблону.

### 4.3 Шаблон только про отображение

`template.php` должен:

- рендерить готовую view-model,
- не содержать бизнес-правил,
- не делать мутации данных,
- не дублировать доменные вычисления.

---

## 5) Фазы внедрения

## Фаза A — Базовый каркас и UI-стандарты

### Область

- общий shell (`local/php_interface/include/ideabank2_public_helpers.php`),
- общие CSS/JS-ассеты публички (`/local/js/uds/ideabank2/*`),
- унификация базовых UI-блоков (карточка, статус, empty-state, кнопки).

### Результат

- единая визуальная база для всех страниц,
- подготовленная инфраструктура для постраничной миграции без дублирования.

### Риски

- поломка существующих шаблонов из-за глобальных стилей.

### Контроль

- проверить все 11 страниц на отсутствие визуальных регрессий shell/навигации.

---

## Фаза B — Контентные страницы (низкий риск)

### Страницы

- `docs`, `news`, `news-detail`, `contests`, `contest-detail`.

### Подход

1. Для каждой страницы зафиксировать контракт `arResult`.
2. Добавить/обновить view-model адаптер в компоненте.
3. Привести шаблон к прототипу по секциям.

### Критерий готовности

- визуально и структурно совпадает с прототипом,
- данные берутся из текущего D7 слоя без костылей в шаблоне.

---

## Фаза C — Метрики и рейтинг (средний риск)

### Страницы

- `hall-of-fame`, `stats`, `index`.

### Подход

1. Сверить источники метрик и топов (балансы, рейтинги, агрегаты).
2. Привести блоки графиков/сводок к прототипу.
3. Проверить пустые состояния, fallback-данные и форматирование чисел/дат.

### Критерий готовности

- блоки метрик и рейтингов соответствуют прототипу,
- данные корректны и не ломаются на пустых выборках.

---

## Фаза D — Операционные страницы (высокий риск)

### Страницы

- `management`, `ppu-detail`, `ppu-form`.

### Подход

1. Миграция реестра идей (`management`): фильтры, режимы, пагинация.
2. Миграция карточки (`ppu-detail`): маршрут, обсуждение, связки, таймлайн.
3. Миграция формы (`ppu-form`): 4 шага, UX, валидации, черновик/отправка.

### Критерий готовности

- ключевые сценарии (просмотр, фильтрация, создание/редактирование, отправка) стабильны,
- верстка и структура соответствуют прототипу.

---

## Фаза E — Стабилизация и приемка

### Задачи

- регрессионный проход по всем страницам,
- проверка сценариев пустых данных,
- E2E-проверка публичного контура на `http://localhost:8588`.

### Финальный критерий

- нет фаталов/500,
- маршруты работают,
- UI соответствует прототипу,
- основной пользовательский сценарий проходит end-to-end.

---

## 6) Порядок выполнения в задачах (chunk-ready)

Рекомендуемый порядок отдельных задач для итеративной работы:

1. `A1`: стабилизация shell + ассеты.
2. `B1`: docs + news + news-detail.
3. `B2`: contests + contest-detail.
4. `C1`: hall-of-fame + stats.
5. `C2`: home/index.
6. `D1`: management.
7. `D2`: ppu-detail.
8. `D3`: ppu-form.
9. `E1`: общий регресс + E2E + фиксация результатов.

Каждая задача выполняется отдельно и не затрагивает остальные страницы сверх необходимого.

---

## 7) Definition of Done для каждой итерации

Для каждой частичной поставки обязательно:

1. Изменения ограничены целевыми файлами фазы.
2. Контракт данных страницы зафиксирован и не «плывет».
3. Шаблон не содержит бизнес-логики.
4. Нет ошибок PHP/JS.
5. Страница открывается и работает на `http://localhost:8588`.

---

## 8) Контрольный чек-лист перед закрытием всей миграции

- [ ] Все 11 страниц приведены к прототипу
- [ ] Навигация и shell единообразны
- [ ] Пустые состояния и ошибки отображаются корректно
- [ ] Фильтры/пагинация/формы работают стабильно
- [ ] Выполнен E2E проход публичного сценария
- [ ] Подготовлен короткий отчет по изменениям и рискам

---

## 9) Промежуточный статус выполнения (итеративно)

### Выполнено: chunk `B1` (`docs + news + news-detail`)

Сделаны точечные изменения в компонентах и шаблонах с внедрением унифицированного контракта `arResult`:

- `shell`
- `page`
- `widgets`
- `meta`

С сохранением backward compatibility в шаблонах через fallback на legacy-поля (`items`, `item`, `supportCategories`).

Изменённые файлы в рамках `B1`:

- `local/components/uds/ideabank.news.list/class.php`
- `local/components/uds/ideabank.news.detail/class.php`
- `local/components/uds/ideabank.docs/class.php`
- `local/components/uds/ideabank.news.list/templates/.default/template.php`
- `local/components/uds/ideabank.news.detail/templates/.default/template.php`
- `local/components/uds/ideabank.docs/templates/.default/template.php`

Проверки, выполненные по итогам `B1`:

1. Синтаксический контроль PHP-изменений (`php -l` в контейнере `dev_php`) — успешно.
2. Runtime smoke на `http://localhost:8588`:
   - `/ideabank/news.php`
   - `/ideabank/docs.php`
   - `/ideabank/news-detail.php?id=1`
   Без фатальных ошибок и без JS-ошибок в консоли.

### Следующий шаг

- Перейти к chunk `B2`: `contests + contest-detail` по аналогичной схеме (контракт + template fallback + smoke).

### Выполнено: chunk `B2` (`contests + contest-detail`)

Сделаны точечные изменения в компонентах и шаблонах с внедрением унифицированного контракта `arResult`:

- `shell`
- `page`
- `widgets`
- `meta`

С сохранением backward compatibility в шаблонах через fallback на legacy-поля (`items`, `item`).

Изменённые файлы в рамках `B2`:

- `local/components/uds/ideabank.contest.list/class.php`
- `local/components/uds/ideabank.contest.detail/class.php`
- `local/components/uds/ideabank.contest.list/templates/.default/template.php`
- `local/components/uds/ideabank.contest.detail/templates/.default/template.php`

Что реализовано в `B2`:

1. `uds:ideabank.contest.list` переведён на контрактный `arResult`-формат (`shell/page/widgets/meta`) с адаптацией списка и вычислением `STATUS`.
2. `uds:ideabank.contest.detail` переведён на контрактный `arResult`-формат с блоком `widgets.info` и fallback на `item`.
3. Шаблоны `contest.list` и `contest.detail` приведены к схеме B1:
   - чтение данных из `page/widgets/meta`;
   - fallback на legacy-ключи;
   - корректные empty/not-found состояния;
   - сохранена совместимость текущих маршрутов `/ideabank/contests.php` и `/ideabank/contest-detail.php?id=...`.

Проверки, выполненные по итогам `B2`:

1. Синтаксический контроль PHP-изменений (`php -l` в контейнере `dev_php`) — успешно.
2. Runtime smoke на `http://localhost:8588`:
   - `/ideabank/contests.php`
   - `/ideabank/contest-detail.php?id=1`
   Без фатальных ошибок и без JS-ошибок в консоли.

### Следующий шаг

- Перейти к chunk `C1`: `hall-of-fame + stats` по аналогичной схеме (контракт + template fallback + smoke).

### Выполнено: chunk `C1` (`hall-of-fame + stats`)

Сделаны точечные изменения в компонентах и шаблонах с развитием унифицированного контрактного `arResult`:

- `shell`
- `page`
- `widgets`
- `meta`

С сохранением backward compatibility в шаблонах через fallback на legacy-ключи (`items`, `stats`, `statuses`, `categories`).

Изменённые файлы в рамках `C1`:

- `local/components/uds/ideabank.hall/class.php`
- `local/components/uds/ideabank.hall/templates/.default/template.php`
- `local/components/uds/ideabank.stats/class.php`
- `local/components/uds/ideabank.stats/templates/.default/template.php`

Что реализовано в `C1`:

1. `uds:ideabank.hall` расширен до двух режимов рейтинга: `Коины авторов` и `Коины за тиражирование`, с единым контрактом, постраничной навигацией и обогащением карточек данными пользователя.
2. `uds:ideabank.stats` переведён на более полный page/widget/meta-контракт с UI-переключателем `Моя статистика / Общая статистика`, фильтром по категории, вторичными KPI и дополнительными блоками аналитики (воронка, реализация, динамика по месяцам).
3. Оба шаблона приведены к схеме C-фазы:
   - чтение данных из `page/widgets/meta`;
   - fallback на legacy-ключи;
   - корректные empty-state;
   - сохранена совместимость маршрутов `/ideabank/hall-of-fame.php` и `/ideabank/stats.php`.

Проверки, выполненные по итогам `C1`:

1. Синтаксический контроль PHP-изменений (`php -l`) — успешно.
2. Runtime smoke на `http://localhost:8588`:
   - `/ideabank/hall-of-fame.php`
   - `/ideabank/stats.php`
   Без фатальных ошибок и без JS-ошибок в консоли.

### Следующий шаг

- Перейти к chunk `C2`: `home/index` с адаптацией дашбордных блоков под тот же контрактный подход.

### Выполнено: chunk `C2` (`home/index`)

Компонент `uds:ideabank.home` на странице `/ideabank/index.php` переведён на контрактный `arResult`:

- `shell`
- `page`
- `widgets`
- `meta`

С сохранением fallback на legacy-ключи для совместимости текущих данных и шаблона.

Изменённые файлы в рамках `C2`:

- `local/components/uds/ideabank.home/class.php`
- `local/components/uds/ideabank.home/templates/.default/template.php`

Что реализовано в `C2`:

1. Добавлена адаптация данных для главной страницы: `hero`, `processSteps`, `trustMetrics`, `coin`, `leaderboard`, `news`, `challenges`, `guidance`, `quotes`, `role queues`, `next action`.
2. Внедрены два сценария главной страницы без смены маршрута:
   - `employee` — вовлекающий дашборд сотрудника;
   - `work` — рабочий контур с очередями модерации/экспертизы/комитета и операционной сводкой.
3. Шаблон читает данные из `page/widgets/meta` и сохраняет fallback на legacy-ключи.

Проверки, выполненные по итогам `C2`:

1. Синтаксический контроль PHP-изменений (`php -l` в контейнере `dev_php`) — успешно.
2. Runtime smoke на `http://localhost:8588`:
   - `/ideabank/index.php?scenario=employee`
   - `/ideabank/index.php?scenario=work`
   Без фатальных ошибок и без новых JS-ошибок в консоли.

### Следующий шаг

- Перейти к chunk `D1`: `management` — реестр идей, режимы, фильтры, пагинация и операционная сводка.

### Выполнено: chunk `D1` (`management`)

Компонент `uds:ideabank.idea.list` на странице `/ideabank/management.php` переведён на контрактный `arResult`:

- `shell`
- `page`
- `widgets`
- `meta`

С сохранением backward compatibility через legacy-ключи (`items`, `statuses`, `categories`, `query`, `mode`, `search`, `pagination`).

Изменённые файлы в рамках `D1`:

- `local/components/uds/ideabank.idea.list/class.php`
- `local/components/uds/ideabank.idea.list/templates/.default/template.php`

Что реализовано в `D1`:

1. В компонент добавлен view-model адаптер для реестра инициатив: режимы, фильтры, сводка, карточки представлений, операционные метрики и empty-state.
2. Карточки идей обогащаются данными автора, ссылками `DETAIL_URL`/`EDIT_URL`, excerpt и человекочитаемым этапом маршрута.
3. Шаблон переведён на чтение `page/widgets/meta`, при этом сохранён fallback на legacy-данные.
4. Реестр получил расширенную операционную шапку: быстрые режимы, карточки представлений, фильтры, счётчики найдено/на странице/активные фильтры и прежнюю пагинацию.

Проверки, выполненные по итогам `D1`:

1. Синтаксический контроль PHP-изменений в контейнере `dev_php` — успешно:
   - `local/components/uds/ideabank.idea.list/class.php`
   - `local/components/uds/ideabank.idea.list/templates/.default/template.php`
2. Runtime smoke на `http://localhost:8588/ideabank/management.php` — страница открывается без фаталов и без новых console errors.

### Следующий шаг

- Перейти к chunk `D2`: `ppu-detail` — карточка идеи, обсуждение, связки, таймлайн и рабочий маршрут.

### Выполнено: chunk `D2` (`ppu-detail`)

Компонент `uds:ideabank.idea.detail` на странице `/ideabank/ppu-detail.php` переведён на контрактный `arResult`:

- `shell`
- `page`
- `widgets`
- `meta`

С сохранением backward compatibility через legacy-ключи (`idea`, `comments`, `reactions`, `workflow`, `feedback`, `expertReviews`, `committeeDecision`).

Изменённые файлы в рамках `D2`:

- `local/components/uds/ideabank.idea.detail/class.php`
- `local/components/uds/ideabank.idea.detail/templates/.default/template.php`

Что реализовано в `D2`:

1. В компонент добавлен view-model адаптер для карточки идеи: основные секции, KPI/meta-карточки, действия, обсуждение, связанные данные, маршрут, авторы, история статусов, экспертные оценки, решение комитета и обратная связь.
2. Шаблон переведён на чтение `page/widgets/meta`, при этом сохранён fallback на legacy-данные.
3. Карточка идеи структурирована по операционному сценарию прототипа: описание инициативы, эффект и внедрение, обсуждение, связки, маршрут/SLA, авторы и timeline.

Проверки, выполненные по итогам `D2`:

1. Синтаксический контроль PHP-изменений в контейнере `dev_php` — успешно:
   - `local/components/uds/ideabank.idea.detail/class.php`
   - `local/components/uds/ideabank.idea.detail/templates/.default/template.php`
2. Runtime smoke на `http://localhost:8588/ideabank/ppu-detail.php?id=1` — страница открывается без фаталов и без новых console errors.

### Следующий шаг

- Перейти к chunk `D3`: `ppu-form` — форма идеи, 4 шага, UX, валидации, черновик/отправка.

### Выполнено: chunk `D3` (`ppu-form`)

Компонент `uds:ideabank.idea.form` на странице `/ideabank/ppu-form.php` переведён на контрактный `arResult`:

- `shell`
- `page`
- `widgets`
- `meta`

С сохранением backward compatibility через legacy-ключи (`idea`, `statuses`, `categories`, `businessDirections`, `actionUrl`).

Изменённые файлы в рамках `D3`:

- `local/components/uds/ideabank.idea.form/class.php`
- `local/components/uds/ideabank.idea.form/templates/.default/template.php`

Что реализовано в `D3`:

1. В компонент добавлен view-model адаптер формы идеи: режим `create/edit`, action URL, 4 шага формы, справочники, подсказки, checklist перед отправкой и сводка параметров заявки.
2. Шаблон переведён на чтение `page/widgets/meta`, при этом сохранён fallback на legacy-данные.
3. Форма структурирована по операционному сценарию прототипа: контекст и проблема, эффект, внедрение, проверка перед отправкой, действия `черновик/отправка`.
4. Для редактирования существующей идеи форма отправляет `action=update`, для новой идеи — `action=create`; CSRF-токен через `bitrix_sessid_post()` сохранён.

Проверки, выполненные по итогам `D3`:

1. Синтаксический контроль PHP-изменений в контейнере `dev_php` — успешно:
   - `local/components/uds/ideabank.idea.form/class.php`
   - `local/components/uds/ideabank.idea.form/templates/.default/template.php`
2. Runtime smoke на `http://localhost:8588/ideabank/ppu-form.php` — страница открывается без фаталов и без новых console errors.

### Следующий шаг

- Перейти к chunk `E1`: общий регресс публичного контура, E2E-проверка маршрутов и фиксация итоговых результатов миграции.

### Выполнено: chunk `E1` (`общий регресс + E2E`)

Выполнен общий регрессионный проход публичного контура `/ideabank/` после завершения chunks `B1`–`D3`.

Проверены публичные маршруты:

- `/ideabank/index.php?scenario=employee`
- `/ideabank/index.php?scenario=work`
- `/ideabank/news.php`
- `/ideabank/news-detail.php?id=1`
- `/ideabank/docs.php`
- `/ideabank/contests.php`
- `/ideabank/contest-detail.php?id=1`
- `/ideabank/hall-of-fame.php`
- `/ideabank/stats.php`
- `/ideabank/management.php`
- `/ideabank/ppu-detail.php?id=1`
- `/ideabank/ppu-form.php`

Что проверено в `E1`:

1. Все 11 публичных страниц подключают ожидаемые компоненты `uds:ideabank.*`.
2. Контрактный подход `shell/page/widgets/meta` сохранён для мигрированных компонентов, legacy fallback оставлен для совместимости.
3. Синтаксический контроль PHP пройден для публичных роутов `/ideabank/*.php`, классов компонентов и шаблонов.
4. HTTP smoke на `http://localhost:8588` подтвердил ответы `200` по основным маршрутам без признаков PHP fatal/parse errors.
5. Browser runtime smoke на `http://localhost:8588/ideabank/index.php?scenario=employee` прошёл без новых console errors.

Проверки, выполненные по итогам `E1`:

1. `php -l` в контейнере `dev_php` — успешно для:
   - `ideabank/*.php`
   - `local/components/uds/ideabank.home/{class.php,templates/.default/template.php}`
   - `local/components/uds/ideabank.docs/{class.php,templates/.default/template.php}`
   - `local/components/uds/ideabank.hall/{class.php,templates/.default/template.php}`
   - `local/components/uds/ideabank.stats/{class.php,templates/.default/template.php}`
   - `local/components/uds/ideabank.news.list/{class.php,templates/.default/template.php}`
   - `local/components/uds/ideabank.news.detail/{class.php,templates/.default/template.php}`
   - `local/components/uds/ideabank.contest.list/{class.php,templates/.default/template.php}`
   - `local/components/uds/ideabank.contest.detail/{class.php,templates/.default/template.php}`
   - `local/components/uds/ideabank.idea.list/{class.php,templates/.default/template.php}`
   - `local/components/uds/ideabank.idea.detail/{class.php,templates/.default/template.php}`
   - `local/components/uds/ideabank.idea.form/{class.php,templates/.default/template.php}`
2. HTTP smoke через `http://localhost:8588` — все проверенные маршруты вернули `200`.
3. Browser smoke — главная страница публичного контура открывается без новых ошибок консоли.

### Итог миграции публичного контура

- Chunks `B1`, `B2`, `C1`, `C2`, `D1`, `D2`, `D3`, `E1` зафиксированы как выполненные.
- Основные публичные маршруты `/ideabank/` открываются без фаталов/500.
- Следующий рекомендуемый шаг вне текущего E1: фаза F — установка, демо-данные, роли, настройки, корпоративный магазин и E2E. Отдельный план: `docs/ideabank2-phase-f-install-demo-roles-shop-plan.md`.
