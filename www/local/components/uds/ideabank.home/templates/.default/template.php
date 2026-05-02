<?php
defined('B_PROLOG_INCLUDED') || die();
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/ideabank2_public_helpers.php';

$page = is_array($arResult['page'] ?? null) ? $arResult['page'] : [];
$widgets = is_array($arResult['widgets'] ?? null) ? $arResult['widgets'] : [];
$meta = is_array($arResult['meta'] ?? null) ? $arResult['meta'] : [];
$coinWidget = is_array($widgets['coin'] ?? null) ? $widgets['coin'] : [];
$roleSwitch = is_array($widgets['roleSwitch'] ?? null) ? $widgets['roleSwitch'] : [];
$roleWidget = is_array($widgets['role'] ?? null) ? $widgets['role'] : [];
$adminSettings = is_array($widgets['adminSettings'] ?? null) ? $widgets['adminSettings'] : [];
$quickLinks = is_array($widgets['quickLinks']['items'] ?? null) ? $widgets['quickLinks']['items'] : [];
$newsItems = is_array($widgets['news']['items'] ?? null) ? $widgets['news']['items'] : (is_array($arResult['news'] ?? null) ? $arResult['news'] : []);
$challengeItems = is_array($widgets['challenges']['items'] ?? null) ? $widgets['challenges']['items'] : (is_array($arResult['challenges'] ?? null) ? $arResult['challenges'] : []);
$guidanceCards = is_array($widgets['guidance']['items'] ?? null) ? $widgets['guidance']['items'] : [];
$quotes = is_array($widgets['quotes']['items'] ?? null) ? $widgets['quotes']['items'] : [];
$leaderboard = is_array($widgets['leaderboard']['items'] ?? null) ? $widgets['leaderboard']['items'] : (is_array($arResult['leaderboard'] ?? null) ? $arResult['leaderboard'] : []);
$leaderCta = is_array($widgets['leaderCta'] ?? null) ? $widgets['leaderCta'] : [];
$shareIdeas = is_array($widgets['shareIdeas']['items'] ?? null) ? $widgets['shareIdeas']['items'] : (is_array($arResult['shareIdeas'] ?? null) ? $arResult['shareIdeas'] : []);
$ideaTabs = is_array($widgets['ideaTabs'] ?? null) ? array_values($widgets['ideaTabs']) : [];
$showcaseIdeas = is_array($page['showcaseIdeas'] ?? null) ? $page['showcaseIdeas'] : (is_array($arResult['ideas'] ?? null) ? $arResult['ideas'] : []);
$hero = is_array($page['hero'] ?? null) ? $page['hero'] : [];
$processSteps = is_array($page['processSteps'] ?? null) ? $page['processSteps'] : [];
$trustMetrics = is_array($page['trustMetrics'] ?? null) ? array_values($page['trustMetrics']) : [];
$currentScenario = (string)($meta['scenario'] ?? 'employee');
$stats = $arResult['stats'] ?? [];
$coinTotals = is_array($arResult['coinTotals'] ?? null) ? $arResult['coinTotals'] : [];
$coinHistory = is_array($coinWidget['history'] ?? null) ? $coinWidget['history'] : (is_array($arResult['coinHistory'] ?? null) ? $arResult['coinHistory'] : []);
$coinTiles = is_array($coinWidget['tiles'] ?? null) ? $coinWidget['tiles'] : [];
$coinLevel = is_array($coinWidget['level'] ?? null) ? $coinWidget['level'] : [];
$features = is_array($meta['features'] ?? null) ? $meta['features'] : [];
$permissions = is_array($meta['permissions'] ?? null) ? $meta['permissions'] : [];
$canViewCoins = !empty($permissions['canViewCoins']) || !empty($features['feature_coins']);
$canViewLeaderboard = !empty($permissions['canViewLeaderboard']) || !empty($features['feature_leaderboard']);
$coinBalance = (int)($coinTotals['balance'] ?? ($arResult['coinBalance'] ?? 0));
$coinEarned = (int)($coinTotals['earned'] ?? 0);
$coinSpent = (int)($coinTotals['spent'] ?? 0);
$coinLevelTitle = (string)($coinLevel['title'] ?? 'Новый автор идей');
$coinLevelTarget = max((int)($coinLevel['target'] ?? 300), 1);
$coinLevelLeft = max((int)($coinLevel['left'] ?? max($coinLevelTarget - $coinBalance, 0)), 0);
$coinLevelProgress = min(100, max(0, (int)($coinLevel['progress'] ?? round($coinBalance / $coinLevelTarget * 100))));

udsIbShellStart(
    is_array($arResult['shell'] ?? null) ? $arResult['shell'] : [],
    (string)($page['title'] ?? 'Банк идей'),
    (string)($page['subtitle'] ?? 'Настраивай бизнес. Меняй бизнес.')
);
?>
<section class="page-section home-layout home-layout--stack">
    <div class="home-main home-main--stack">
        <?php $nextAction = is_array($roleWidget['nextAction'] ?? null) ? $roleWidget['nextAction'] : []; ?>
        <section class="panel panel--accent role-dashboard role-dashboard--<?= udsIbE($currentScenario) ?>">
            <div class="role-dashboard__top">
                <div class="role-dashboard__intro">
                    <h2 class="home-hero__title"><?= udsIbText($hero['title'] ?? 'Настраивай бизнес. Меняй бизнес.') ?></h2>
                    <p class="home-hero__text"><?= udsIbText($hero['text'] ?? '') ?></p>
                </div>
                <?php if (count((array)($roleSwitch['options'] ?? [])) > 1): ?>
                    <div class="role-mode-switch">
                        <span>Интерфейс</span>
                        <div class="role-mode-switch__tabs">
                            <?php foreach ((array)$roleSwitch['options'] as $option): ?>
                                <?php $isActiveRole = (string)($option['code'] ?? '') === $currentScenario; ?>
                                <a class="tab <?= $isActiveRole ? 'is-active' : '' ?>" href="<?= udsIbE($option['url'] ?? '/ideabank/') ?>">
                                    <?= udsIbText($option['badge'] ?? $option['title'] ?? '') ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="home-hero__actions">
                <?php foreach ((array)($hero['actions'] ?? []) as $action): ?>
                    <a class="<?= !empty($action['primary']) ? 'primary-button' : 'outline-button' ?>" href="<?= udsIbE($action['url'] ?? '#') ?>"><?= udsIbText($action['title'] ?? '') ?></a>
                <?php endforeach; ?>
            </div>

            <?php if ($currentScenario === 'work' && $roleWidget !== []): ?>
                <div class="role-dashboard__work">
                    <div class="role-dashboard__work-header">
                        <div class="panel__title"><?= udsIbText($nextAction['title'] ?? 'Рабочий контур') ?></div>
                        <p class="panel__subtitle"><?= udsIbText($nextAction['text'] ?? '') ?></p>
                    </div>
                <?php if (is_array($roleWidget['workMetrics'] ?? null) && $roleWidget['workMetrics'] !== []): ?>
                    <div class="work-metrics-grid">
                        <?php foreach ($roleWidget['workMetrics'] as $metric): ?>
                            <article class="work-metric-card">
                                <strong><?= udsIbText($metric['value'] ?? 0) ?></strong>
                                <span><?= udsIbText($metric['label'] ?? '') ?></span>
                                <p><?= udsIbText($metric['caption'] ?? '') ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (is_array($roleWidget['workLinks'] ?? null) && $roleWidget['workLinks'] !== []): ?>
                    <div class="home-side-links home-side-links--inline">
                        <?php foreach ($roleWidget['workLinks'] as $link): ?>
                            <a class="quick-link-card" href="<?= udsIbE($link['url'] ?? '#') ?>"><strong><?= udsIbText($link['title'] ?? '') ?></strong><span><?= udsIbText($link['text'] ?? '') ?></span></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($currentScenario === 'admin' && $adminSettings !== []): ?>
            <?php
            $settingsMessage = is_array($adminSettings['message'] ?? null) ? $adminSettings['message'] : [];
            $settingsSections = is_array($adminSettings['sections'] ?? null) ? $adminSettings['sections'] : [];
            $roleGroups = is_array($adminSettings['roleGroups'] ?? null) ? $adminSettings['roleGroups'] : [];
            $selfCheck = is_array($adminSettings['selfCheck'] ?? null) ? $adminSettings['selfCheck'] : [];
            ?>
            <section class="panel module-settings-panel">
                <div class="panel__header">
                    <div>
                        <div class="panel__title">Настройка модуля</div>
                        <p class="panel__subtitle">Публичная панель администратора банка идей: включение разделов, workflow, коинов, dev-доступа и контроль установки.</p>
                    </div>
                    <a class="text-link" href="/bitrix/admin/settings.php?mid=uds.ideabank2&lang=ru">Открыть в админке</a>
                </div>
                <?php if ($settingsMessage !== []): ?>
                    <div class="settings-message settings-message--<?= udsIbE($settingsMessage['type'] ?? 'success') ?>">
                        <?= udsIbText($settingsMessage['text'] ?? '') ?>
                    </div>
                <?php endif; ?>
                <form class="module-settings-form" method="post" action="/ideabank/index.php?scenario=admin">
                    <?= bitrix_sessid_post() ?>
                    <input type="hidden" name="uds_ib_action" value="save_public_settings">
                    <div class="module-settings-grid">
                        <?php foreach ($settingsSections as $section): ?>
                            <?php $fields = is_array($section['fields'] ?? null) ? $section['fields'] : []; ?>
                            <section class="settings-section">
                                <div class="settings-section__head">
                                    <div>
                                        <strong><?= udsIbText($section['title'] ?? '') ?></strong>
                                        <p><?= udsIbText($section['text'] ?? '') ?></p>
                                    </div>
                                </div>
                                <div class="settings-fields">
                                    <?php foreach ($fields as $field): ?>
                                        <?php
                                        $fieldType = (string)($field['type'] ?? 'toggle');
                                        $fieldName = (string)($field['name'] ?? '');
                                        ?>
                                        <label class="settings-field settings-field--<?= udsIbE($fieldType) ?>">
                                            <span class="settings-field__body">
                                                <span class="settings-field__title"><?= udsIbText($field['title'] ?? '') ?></span>
                                                <span class="settings-field__text"><?= udsIbText($field['text'] ?? '') ?></span>
                                            </span>
                                            <?php if ($fieldType === 'number'): ?>
                                                <input
                                                    class="settings-input settings-input--number"
                                                    type="number"
                                                    name="<?= udsIbE($fieldName) ?>"
                                                    value="<?= (int)($field['value'] ?? 0) ?>"
                                                    min="<?= (int)($field['min'] ?? 0) ?>"
                                                    <?= array_key_exists('max', $field) && $field['max'] !== null ? 'max="' . (int)$field['max'] . '"' : '' ?>
                                                >
                                            <?php elseif ($fieldType === 'text'): ?>
                                                <input
                                                    class="settings-input settings-input--text"
                                                    type="text"
                                                    name="<?= udsIbE($fieldName) ?>"
                                                    value="<?= udsIbE($field['value'] ?? '') ?>"
                                                    placeholder="1, 2, 3"
                                                >
                                            <?php else: ?>
                                                <span class="settings-toggle">
                                                    <input type="checkbox" name="<?= udsIbE($fieldName) ?>" value="Y" <?= !empty($field['enabled']) ? 'checked' : '' ?>>
                                                    <span></span>
                                                </span>
                                            <?php endif; ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </section>
                        <?php endforeach; ?>
                    </div>

                    <div class="module-settings-footer">
                        <div class="settings-summary">
                            <div>
                                <strong>Ролевые группы</strong>
                                <span>Создаются установщиком и используются для доступа к публичным режимам.</span>
                            </div>
                            <div class="settings-role-groups">
                                <?php foreach ($roleGroups as $group): ?>
                                    <span><?= udsIbText($group['title'] ?? '') ?>: <strong><?= (int)($group['value'] ?? 0) ?></strong></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="settings-summary">
                            <?php $selfCheckOk = !empty($selfCheck['ok']); ?>
                            <div>
                                <strong>Self-check: <span class="<?= $selfCheckOk ? 'settings-ok' : 'settings-error' ?>"><?= $selfCheckOk ? 'OK' : 'ERROR' ?></span></strong>
                                <span>Проверка feature flags, групп ролей и debug-auth.</span>
                            </div>
                            <details class="settings-check-details">
                                <summary>Показать проверки</summary>
                                <?php foreach ((array)($selfCheck['items'] ?? []) as $item): ?>
                                    <div class="settings-check-row">
                                        <span><?= udsIbText($item['code'] ?? 'check') ?></span>
                                        <strong class="settings-check-row--<?= udsIbE($item['status'] ?? 'warning') ?>"><?= udsIbText(mb_strtoupper((string)($item['status'] ?? 'warning'))) ?></strong>
                                        <em><?= udsIbText($item['message'] ?? '', 140) ?></em>
                                    </div>
                                <?php endforeach; ?>
                            </details>
                        </div>
                        <div class="module-settings-actions">
                            <button class="primary-button" type="submit">Сохранить настройки</button>
                        </div>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <section class="panel public-ideas-feed public-ideas-widget" id="best-ideas" data-uds-idea-tabs>
                <div class="panel__header">
                    <div>
                        <div class="panel__title">Идеи в банке</div>
                        <p class="panel__subtitle">Следите за сильными инициативами, новыми предложениями и своими идеями.</p>
                    </div>
                    <a class="text-link" href="/ideabank/management.php">Все идеи</a>
                </div>
                <?php if ($ideaTabs !== []): ?>
                    <div class="idea-tabs" role="tablist" aria-label="Фильтр идей">
                        <?php foreach ($ideaTabs as $tabIndex => $tab): ?>
                            <?php $tabCode = (string)($tab['code'] ?? ('tab' . $tabIndex)); ?>
                            <?php $tabItems = is_array($tab['items'] ?? null) ? $tab['items'] : []; ?>
                            <button
                                class="tab idea-tab <?= $tabIndex === 0 ? 'is-active' : '' ?>"
                                type="button"
                                role="tab"
                                aria-selected="<?= $tabIndex === 0 ? 'true' : 'false' ?>"
                                data-uds-idea-tab="<?= udsIbE($tabCode) ?>"
                            >
                                <?= udsIbText($tab['title'] ?? '') ?>
                                <span><?= count($tabItems) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <?php foreach ($ideaTabs as $tabIndex => $tab): ?>
                        <?php $tabCode = (string)($tab['code'] ?? ('tab' . $tabIndex)); ?>
                        <?php $tabItems = is_array($tab['items'] ?? null) ? $tab['items'] : []; ?>
                        <div class="idea-tab-panel <?= $tabIndex === 0 ? 'is-active' : '' ?>" data-uds-idea-panel="<?= udsIbE($tabCode) ?>" <?= $tabIndex === 0 ? '' : 'hidden' ?>>
                            <?php if ($tabItems !== []): ?>
                                <div class="public-ideas-feed__list public-ideas-feed__list--widget">
                                    <?php foreach ($tabItems as $idea): ?>
                                        <?php
                                        $status = is_array($idea['STATUS'] ?? null) ? $idea['STATUS'] : [];
                                        $statusCode = (string)($status['CODE'] ?? $idea['STAGE'] ?? '');
                                        $statusName = (string)($status['NAME'] ?? 'Стадия не указана');
                                        $detailUrl = (string)($idea['DETAIL_URL'] ?? ('/ideabank/ppu-detail.php?id=' . (int)($idea['ID'] ?? 0)));
                                        $effect = (float)($idea['EFFECT_VALUE'] ?? ($idea['ECONOMIC_EFFECT'] ?? 0));
                                        $confirmed = (float)($idea['CONFIRMED_EFFECT_VALUE'] ?? ($idea['CONFIRMED_EFFECT'] ?? 0));
                                        $days = (int)($idea['IMPLEMENTATION_DAYS_VALUE'] ?? ($idea['IMPLEMENTATION_DAYS'] ?? 0));
                                        $confirmedPercent = $effect > 0 ? min(100, max(0, (int)round($confirmed / $effect * 100))) : 0;
                                        ?>
                                        <article class="idea-list-card">
                                            <div class="idea-list-card__main">
                                                <div class="idea-list-card__top">
                                                    <strong><?= udsIbText($idea['TITLE'] ?? '') ?></strong>
                                                    <div class="idea-list-card__badges">
                                                        <?php if ((string)($idea['IS_HIDDEN'] ?? 'N') === 'Y'): ?><span class="status status--hidden">Скрытая</span><?php endif; ?>
                                                        <span class="status<?= udsIbStatusClass($statusCode) ?>"><?= udsIbText($statusName) ?></span>
                                                    </div>
                                                </div>
                                                <div class="idea-list-card__meta">
                                                    <span><?= udsIbText($idea['BUSINESS_DIRECTION'] ?? 'Направление не указано') ?></span>
                                                    <span><?= udsIbText($idea['CATEGORY']['NAME'] ?? 'Без категории') ?></span>
                                                    <span><?= udsIbText($idea['OWNER_LABEL'] ?? 'Автор идеи') ?></span>
                                                </div>
                                                <p class="idea-list-card__text"><?= udsIbText($idea['EXCERPT'] ?? '', 180) ?></p>
                                            </div>
                                            <div class="idea-list-card__metrics">
                                                <div><span>Эффект</span><strong><?= udsIbMoney($effect) ?></strong></div>
                                                <div><span>Подтверждено</span><strong><?= udsIbMoney($confirmed) ?></strong></div>
                                                <div><span>Подтверждение</span><strong><?= $confirmedPercent ?>%</strong></div>
                                                <div><span>Срок</span><strong><?= $days > 0 ? $days . ' дн.' : 'не указан' ?></strong></div>
                                            </div>
                                            <a class="text-link idea-list-card__link" href="<?= udsIbE($detailUrl) ?>">Открыть карточку</a>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state empty-state--compact"><?= udsIbText($tab['empty'] ?? 'Идей пока нет.') ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state empty-state--compact">Идей пока нет.</div>
                <?php endif; ?>
        </section>

        <?php if ($currentScenario === 'employee'): ?>
            <?php if ($canViewCoins): ?>
                <section class="panel panel--accent coin-hero coin-hero--motivational">
                    <div class="coin-hero__top">
                        <div class="coin-hero__intro">
                            <div class="coin-panel__eyebrow">Мои коины</div>
                            <div class="coin-panel__balance"><?= udsIbCoins($coinBalance) ?></div>
                            <div class="coin-level">
                                <span>Уровень: <strong><?= udsIbText($coinLevelTitle) ?></strong></span>
                                <span>До следующего уровня: <strong><?= udsIbCoins($coinLevelLeft) ?></strong></span>
                            </div>
                            <div class="coin-progress" aria-label="<?= udsIbE($coinLevelProgress . '% до следующего уровня') ?>">
                                <span style="width: <?= $coinLevelProgress ?>%;"></span>
                            </div>
                            <div class="coin-progress__caption"><?= udsIbCoins($coinBalance) ?> / <?= udsIbCoins($coinLevelTarget) ?></div>
                        </div>
                        <div class="coin-panel__hero-icon coin-panel__hero-icon--coin" aria-hidden="true"></div>
                    </div>
                    <div class="coin-hero__body coin-hero__body--motivational">
                        <div class="coin-history-compact coin-history-compact--featured">
                            <div class="coin-history__header">
                                <div>
                                    <div class="coin-rules__title">Последние операции</div>
                                    <div class="coin-history__caption">Движение по вашему балансу</div>
                                </div>
                                <a class="text-link" href="/ideabank/stats.php?scope=mine">Все операции</a>
                            </div>
                            <?php if ($coinHistory !== []): ?>
                                <div class="coin-history__list coin-history__list--compact">
                                    <?php foreach (array_slice($coinHistory, 0, 4) as $coin): ?>
                                        <?php
                                        $coinAmount = (int)($coin['amount'] ?? $coin['COINS'] ?? 0);
                                        $coinTitle = (string)($coin['title'] ?? $coin['DESCRIPTION'] ?? $coin['EVENT'] ?? 'Операция по коинам');
                                        $coinBadge = (string)($coin['badge'] ?? 'Баланс');
                                        $coinIdea = (string)($coin['idea'] ?? '');
                                        $coinDate = $coin['date'] ?? $coin['CREATED_AT'] ?? null;
                                        ?>
                                        <div class="coin-history__item coin-history__item--compact">
                                            <div class="coin-history__main">
                                                <div class="coin-history__meta">
                                                    <span class="coin-operation-badge"><?= udsIbText($coinBadge) ?></span>
                                                    <span><?= udsIbDate($coinDate) ?></span>
                                                </div>
                                                <strong><?= udsIbText($coinTitle) ?></strong>
                                                <?php if ($coinIdea !== ''): ?>
                                                    <div class="uds-ib-muted"><?= udsIbText($coinIdea, 80) ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="coin-history__amount"><?= ($coinAmount > 0 ? '+' : '') . udsIbCoins($coinAmount) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state empty-state--compact">Операций пока нет. Подайте первую идею, чтобы получить стартовые коины.</div>
                            <?php endif; ?>
                        </div>
                        <?php if ($coinTiles !== []): ?>
                            <div class="coin-rules coin-rules--compact">
                                <div>
                                    <div class="coin-rules__title">Правила начислений</div>
                                    <div class="coin-history__caption">За что растет ваш баланс</div>
                                </div>
                                <div class="coin-panel__grid coin-panel__grid--rules">
                                    <?php foreach ($coinTiles as $tile): ?>
                                        <article class="coin-tile <?= !empty($tile['accent']) ? 'coin-tile--accent' : '' ?>">
                                            <div class="coin-tile__icon coin-tile__icon--<?= udsIbE($tile['icon'] ?? 'idea') ?>" aria-hidden="true"></div>
                                            <div>
                                                <div class="coin-tile__value">+<?= udsIbCoins($tile['value'] ?? 0) ?></div>
                                                <div class="coin-tile__title"><?= udsIbText($tile['title'] ?? '') ?></div>
                                                <div class="coin-tile__text"><?= udsIbText($tile['text'] ?? '', 90) ?></div>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="coin-shop-note">
                        <div>
                            <div class="coin-hero__actions-title">На что можно потратить коины?</div>
                            <div class="coin-hero__actions-text">
                                Скоро здесь появится магазин наград. Сейчас коины показывают вклад, активность и признание автора.
                            </div>
                        </div>
                        <div class="coin-hero__actions-buttons">
                            <button class="outline-button coin-shop-button" type="button" disabled>Магазин наград</button>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($processSteps !== []): ?>
                <section class="panel process-steps-panel process-steps-panel--combined">
                    <div class="panel__header">
                        <div>
                            <div class="panel__title">Ваша идея может изменить процесс</div>
                            <p class="panel__subtitle">Каждое улучшение начинается с наблюдения: что можно сделать проще, быстрее или удобнее. Предложите идею, соберите поддержку коллег и доведите инициативу до реального результата.</p>
                        </div>
                    </div>
                    <div class="process-steps-grid">
                        <?php foreach ($processSteps as $index => $step): ?>
                            <?php $stepAction = is_array($step['action'] ?? null) ? $step['action'] : null; ?>
                            <?php $stepShareIdeas = is_array($step['shareIdeas'] ?? null) ? $step['shareIdeas'] : []; ?>
                            <?php $stepMetric = is_array($trustMetrics[$index] ?? null) ? $trustMetrics[$index] : null; ?>
                            <article class="process-step-card">
                                <div class="process-step-card__top">
                                    <span class="process-step-card__number"><?= (int)($step['number'] ?? 0) ?></span>
                                    <span class="process-step-card__icon"><?= udsIbText($step['icon'] ?? '') ?></span>
                                </div>
                                <strong><?= udsIbText($step['title'] ?? '') ?></strong>
                                <p><?= udsIbText($step['text'] ?? '') ?></p>
                                <?php if ($stepShareIdeas !== []): ?>
                                    <div class="process-step-share">
                                        <select class="idea-share-panel__select" data-uds-share-idea-select>
                                            <?php foreach ($stepShareIdeas as $idea): ?>
                                                <option value="<?= udsIbE($idea['URL'] ?? '#') ?>" data-title="<?= udsIbE($idea['TITLE'] ?? '') ?>">
                                                    <?= udsIbText($idea['TITLE'] ?? '') ?><?= !empty($idea['STATUS_LABEL']) ? ' · ' . udsIbText($idea['STATUS_LABEL']) : '' ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="primary-button" type="button" data-uds-share-idea>Позвать коллег поддержать</button>
                                    </div>
                                <?php endif; ?>
                                <?php if ($stepAction !== null): ?>
                                    <?php if (!empty($stepAction['disabled'])): ?>
                                        <button
                                            class="<?= !empty($stepAction['primary']) ? 'primary-button' : 'outline-button' ?>"
                                            type="button"
                                            disabled
                                            title="<?= udsIbE($stepAction['hint'] ?? '') ?>"
                                        ><?= udsIbText($stepAction['title'] ?? '') ?></button>
                                    <?php else: ?>
                                        <a class="<?= !empty($stepAction['primary']) ? 'primary-button' : 'outline-button' ?>" href="<?= udsIbE($stepAction['url'] ?? '#') ?>"><?= udsIbText($stepAction['title'] ?? '') ?></a>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($stepMetric !== null): ?>
                                    <div class="process-step-card__metric">
                                        <strong><?= udsIbText($stepMetric['value'] ?? 0) ?></strong>
                                        <span><?= udsIbText($stepMetric['label'] ?? '') ?></span>
                                        <p><?= udsIbText($stepMetric['caption'] ?? '') ?></p>
                                    </div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

        <?php endif; ?>

        <?php if ($currentScenario !== 'work'): ?>
            <?php if ($newsItems !== []): ?>
                <section class="panel home-news">
                    <div class="panel__header"><div class="panel__title">Новости банка идей</div><a class="text-link" href="/ideabank/news.php">Все новости</a></div>
                    <div class="news-list">
                        <?php foreach ($newsItems as $item): ?>
                            <?php $newsImage = trim((string)($item['IMAGE'] ?? $item['HERO_IMAGE'] ?? '')); ?>
                            <article class="news-card">
                                <a class="news-card__preview" href="/ideabank/news-detail.php?id=<?= (int)($item['ID'] ?? 0) ?>" aria-label="<?= udsIbE($item['TITLE'] ?? 'Новость') ?>">
                                    <?php if ($newsImage !== ''): ?>
                                        <img src="<?= udsIbE($newsImage) ?>" alt="">
                                    <?php else: ?>
                                        <span><?= mb_substr((string)($item['CATEGORY'] ?? 'Новости'), 0, 1) ?></span>
                                    <?php endif; ?>
                                </a>
                                <div class="news-card__body">
                                    <div>
                                        <div class="coin-panel__eyebrow"><?= udsIbText($item['CATEGORY'] ?? 'Новости') ?></div>
                                        <h3 class="news-card__title"><a href="/ideabank/news-detail.php?id=<?= (int)($item['ID'] ?? 0) ?>"><?= udsIbText($item['TITLE'] ?? '') ?></a></h3>
                                        <p><?= udsIbText($item['EXCERPT'] ?? '', 180) ?></p>
                                    </div>
                                    <span class="uds-ib-muted"><?= udsIbDate($item['DATE'] ?? null) ?></span>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($challengeItems !== [] || $leaderCta !== []): ?>
                <section class="panel theme-challenges">
                    <div class="panel__header theme-challenges__header">
                        <div>
                            <div class="panel__title">Тематические челленджи</div>
                            <p class="panel__subtitle">Руководители запускают темы для сбора идей, а сотрудники привязывают к ним свои инициативы.</p>
                        </div>
                        <div class="theme-challenges__actions">
                            <?php if ($leaderCta !== []): ?>
                                <a class="primary-button" href="<?= udsIbE($leaderCta['url'] ?? '/ideabank/contests.php') ?>"><?= udsIbText($leaderCta['button'] ?? 'Запустить челлендж') ?></a>
                            <?php endif; ?>
                            <a class="text-link" href="/ideabank/contests.php">Все программы</a>
                        </div>
                    </div>
                    <?php if ($challengeItems !== []): ?>
                        <div class="challenge-grid">
                            <?php foreach ($challengeItems as $item): ?>
                                <?php $challengeIcon = preg_replace('/[^a-z0-9_-]/i', '', (string)($item['ICON'] ?? 'target')); ?>
                                <?php $challengeStats = is_array($item['IDEA_STATS'] ?? null) ? $item['IDEA_STATS'] : []; ?>
                                <?php
                                $challengeTotal = (int)($item['IDEA_TOTAL'] ?? 0);
                                $challengeImplemented = 0;
                                foreach ($challengeStats as $stat) {
                                    $statLabel = mb_strtolower((string)($stat['label'] ?? ''));
                                    if (str_contains($statLabel, 'реализ') || str_contains($statLabel, 'внедр')) {
                                        $challengeImplemented += (int)($stat['value'] ?? 0);
                                    }
                                }
                                $challengeProgress = $challengeTotal > 0 ? min(100, max(0, (int)round($challengeImplemented / $challengeTotal * 100))) : 0;
                                ?>
                                <article class="challenge-card challenge-card--featured uds-ib-card--span-6">
                                    <div class="challenge-card__preview challenge-card__preview--icon challenge-card__preview--<?= udsIbE($challengeIcon) ?>" aria-hidden="true">
                                        <span></span>
                                    </div>
                                    <div class="challenge-card__body">
                                        <div class="challenge-card__content">
                                            <div class="challenge-card__meta">
                                                <span><?= udsIbText($item['PERIOD'] ?? '') ?></span>
                                                <span><?= udsIbText($item['BUSINESS_DIRECTION'] ?? 'Фокус улучшений') ?></span>
                                            </div>
                                            <div class="challenge-card__title"><?= udsIbText($item['TITLE'] ?? '') ?></div>
                                            <p class="challenge-card__text"><strong>Цель:</strong> <?= udsIbText($item['TARGET'] ?? '', 180) ?></p>
                                            <div class="challenge-card__progress" aria-label="<?= (int)$challengeProgress ?>% реализовано">
                                                <span style="width: <?= (int)$challengeProgress ?>%;"></span>
                                            </div>
                                            <div class="challenge-card__progress-caption"><?= (int)$challengeProgress ?>% идей доведены до результата</div>
                                        </div>
                                        <div class="challenge-card__side">
                                            <div class="challenge-card__reward">+<?= udsIbCoins($item['REWARD_BONUS'] ?? 0) ?><span>бонус за участие</span></div>
                                            <div class="challenge-card__stats">
                                                <div class="challenge-card__stat challenge-card__stat--total"><strong><?= $challengeTotal ?></strong><span>идей всего</span></div>
                                                <?php foreach ($challengeStats as $stat): ?>
                                                    <div class="challenge-card__stat"><strong><?= (int)($stat['value'] ?? 0) ?></strong><span><?= udsIbText($stat['label'] ?? '') ?></span></div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state empty-state--compact">Активных тематических челленджей пока нет.</div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($guidanceCards !== []): ?>
                <section class="panel culture-panel">
                    <div class="panel__header"><div><div class="panel__title">Документы и информация</div><p class="panel__subtitle">Короткие материалы, которые помогают быстрее оформить сильную инициативу.</p></div><a class="text-link" href="/ideabank/docs.php">Все документы</a></div>
                    <div class="challenge-grid">
                        <?php foreach ($guidanceCards as $card): ?>
                            <a class="challenge-card challenge-card--soft doc-teaser-card" href="<?= udsIbE($card['url'] ?? '/ideabank/docs.php') ?>">
                                <span class="coin-panel__eyebrow"><?= udsIbText($card['type'] ?? 'Документ') ?></span>
                                <span class="challenge-card__title"><?= udsIbText($card['title'] ?? '') ?></span>
                                <span class="challenge-card__text"><?= udsIbText($card['text'] ?? '') ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($quotes !== []): ?>
                <section class="panel culture-panel">
                    <div class="panel__header"><div class="panel__title">Голоса авторов</div><a class="text-link" href="/ideabank/management.php?mode=best">Смотреть идеи</a></div>
                    <div class="quote-grid">
                        <?php foreach ($quotes as $quote): ?>
                            <article class="quote-card"><div class="quote-card__mark">“</div><p class="quote-card__text"><?= udsIbText($quote['text'] ?? '') ?></p><div class="quote-card__author"><?= udsIbText($quote['author'] ?? '') ?></div></article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

        <?php if ($canViewLeaderboard): ?>
        <section class="panel leaderboard-panel">
                <div class="panel__header"><div><div class="coin-panel__eyebrow">Лидеры вовлечения</div><div class="panel__title">Рейтинг авторов и амбассадоров идей</div></div><a class="text-link" href="/ideabank/hall-of-fame.php">По коинам</a></div>
                <div class="rating-list rating-list--grid">
                    <?php foreach (array_slice($leaderboard, 0, 5) as $entry): ?>
                        <?php $entryIdeaStats = is_array($entry['IDEA_STATS'] ?? null) ? $entry['IDEA_STATS'] : []; ?>
                        <div class="rating-list__item rating-list__item--card">
                            <div class="rating-list__avatar">
                                <?php if (trim((string)($entry['PHOTO_SRC'] ?? '')) !== ''): ?>
                                    <img src="<?= udsIbE($entry['PHOTO_SRC']) ?>" alt="">
                                <?php else: ?>
                                    <span><?= udsIbText(mb_substr((string)($entry['USER_LABEL'] ?? 'У'), 0, 1)) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="rating-list__meta">
                                <strong>#<?= (int)($entry['RANK'] ?? 0) ?> · <?= udsIbText($entry['USER_LABEL'] ?? ('Пользователь ' . (int)($entry['USER_ID'] ?? 0))) ?></strong>
                                <span><?= udsIbText($entry['ROLE_LABEL'] ?? 'Участник банка идей') ?></span>
                                <div class="rating-list__stats" aria-label="Статистика идей пользователя">
                                    <span><strong><?= (int)($entryIdeaStats['total'] ?? 0) ?></strong> идей</span>
                                    <span><strong><?= (int)($entryIdeaStats['discussion'] ?? 0) ?></strong> обсуждаются</span>
                                    <span><strong><?= (int)($entryIdeaStats['accepted'] ?? 0) ?></strong> в работе</span>
                                    <span><strong><?= (int)($entryIdeaStats['implemented'] ?? 0) ?></strong> внедрено</span>
                                </div>
                            </div>
                            <div class="rating-list__value"><?= udsIbCoins($entry['COINS'] ?? 0) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php udsIbShellEnd(); ?>
