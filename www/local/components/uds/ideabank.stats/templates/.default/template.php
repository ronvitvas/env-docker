<?php
defined('B_PROLOG_INCLUDED') || die();
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/ideabank2_public_helpers.php';

$page = is_array($arResult['page'] ?? null) ? $arResult['page'] : [];
$widgets = is_array($arResult['widgets'] ?? null) ? $arResult['widgets'] : [];
$meta = is_array($arResult['meta'] ?? null) ? $arResult['meta'] : [];
$charts = is_array($widgets['charts'] ?? null) ? $widgets['charts'] : [];
$metrics = is_array($page['metrics'] ?? null) ? $page['metrics'] : [];
$secondaryMetrics = is_array($page['secondaryMetrics'] ?? null) ? $page['secondaryMetrics'] : [];
$filterSummary = is_array($widgets['filters'] ?? null) ? $widgets['filters'] : [];
$stats = is_array($arResult['stats'] ?? null) ? $arResult['stats'] : [];
$statuses = is_array($charts['statuses'] ?? null)
    ? $charts['statuses']
    : (is_array($arResult['statuses'] ?? null) ? $arResult['statuses'] : []);
$categories = is_array($charts['categories'] ?? null)
    ? $charts['categories']
    : (is_array($arResult['categories'] ?? null) ? $arResult['categories'] : []);
$funnel = is_array($charts['funnel'] ?? null) ? $charts['funnel'] : [];
$ratio = is_array($charts['ratio'] ?? null) ? $charts['ratio'] : [];
$trends = is_array($charts['trends'] ?? null) ? $charts['trends'] : [];
$tabs = is_array($meta['tabs'] ?? null) ? $meta['tabs'] : [];
$scope = (string)($meta['scope'] ?? 'general');
$selectedCategoryId = (int)($meta['selectedCategoryId'] ?? 0);
$categoryOptions = is_array($meta['categoryOptions'] ?? null) ? $meta['categoryOptions'] : [];

udsIbShellStart(
    is_array($arResult['shell'] ?? null) ? $arResult['shell'] : [],
    (string)($page['title'] ?? 'Статистика'),
    (string)($page['subtitle'] ?? 'Воронка идей, распределение по статусам и категориям.')
);
?>
<section class="panel panel--accent stats-shell">
    <div class="stats-shell__top">
        <div>
            <div class="stats-shell__eyebrow"><?= udsIbText($page['eyebrow'] ?? 'Аналитика портала') ?></div>
            <div class="panel__title">Общая воронка</div>
            <div class="stats-shell__description"><?= udsIbText($page['description'] ?? 'Ключевые показатели банка идей по текущим данным модуля.') ?></div>
        </div>
        <?php if ($tabs !== []): ?>
            <div class="tabs tabs--surface">
                <?php foreach ($tabs as $tab): ?>
                    <a
                        class="tab <?= ($tab['code'] ?? '') === $scope ? 'is-active' : '' ?>"
                        href="<?= udsIbE(udsIbUrl('stats.php', ['scope' => $tab['code'] ?? 'general', 'category_id' => $selectedCategoryId > 0 ? $selectedCategoryId : null])) ?>"
                    >
                        <?= udsIbText($tab['title'] ?? '') ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <form class="filters-grid filters-grid--stats" method="get" action="<?= udsIbE(udsIbUrl('stats.php')) ?>" style="margin-top:16px">
        <input type="hidden" name="scope" value="<?= udsIbE($scope) ?>">
        <label class="field">
            <span class="field__label">Категория</span>
            <select name="category_id">
                <option value="">Все категории</option>
                <?php foreach ($categoryOptions as $option): ?>
                    <option value="<?= (int)($option['id'] ?? 0) ?>" <?= (int)($option['id'] ?? 0) === $selectedCategoryId ? 'selected' : '' ?>>
                        <?= udsIbText($option['title'] ?? '') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="stats-filter-card"><span>Активный срез</span><strong><?= udsIbText($filterSummary['activeSlice'] ?? ($scope === 'mine' ? 'Мои инициативы' : 'Вся база идей')) ?></strong></div>
        <div class="stats-filter-card"><span>В работе и в истории</span><strong><?= (int)($filterSummary['totalIdeas'] ?? ($stats['total'] ?? 0)) ?></strong></div>
        <div class="stats-filter-card"><span>Потенциальный эффект</span><strong><?= udsIbMoney($filterSummary['potentialEffect'] ?? 0) ?></strong></div>
        <div style="align-self:end"><button class="primary-button" type="submit">Применить</button></div>
    </form>
    <div class="trust-metrics-grid stats-grid--metrics" style="margin-top:16px">
        <?php foreach ($metrics as $metric): ?>
            <article class="trust-metric-card"><strong><?= udsIbText($metric['value'] ?? 0) ?></strong><span><?= udsIbText($metric['label'] ?? '') ?></span><p><?= udsIbText($metric['caption'] ?? '') ?></p></article>
        <?php endforeach; ?>
        <?php if ($metrics === []): ?>
            <article class="trust-metric-card"><strong><?= (int)($stats['total'] ?? 0) ?></strong><span>Всего</span></article>
            <article class="trust-metric-card"><strong><?= (int)($stats['published'] ?? 0) ?></strong><span>Опубликовано</span></article>
            <article class="trust-metric-card"><strong><?= (int)($stats['implemented'] ?? 0) ?></strong><span>Реализовано</span></article>
        <?php endif; ?>
    </div>
</section>
<?php if ($secondaryMetrics !== []): ?>
    <section class="stats-grid stats-grid--metrics stats-grid--metrics-secondary" style="margin-top:16px">
        <?php foreach ($secondaryMetrics as $metric): ?>
            <article class="trust-metric-card"><strong><?= udsIbText($metric['value'] ?? 0) ?></strong><span><?= udsIbText($metric['label'] ?? '') ?></span><p><?= udsIbText($metric['caption'] ?? '') ?></p></article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
<section class="stats-grid" style="margin-top:16px">
    <article class="panel chart-panel chart-panel--wide uds-ib-card--span-6"><div class="panel__title">По статусам</div><?php foreach ($statuses as $row): ?><div class="uds-ib-chart-row"><b><?= udsIbText($row['label'] ?? '') ?></b><span style="width:<?= (int)($row['percent'] ?? max(5, (int)($row['value'] ?? 0) * 20)) ?>%"></span><em><?= (int)($row['value'] ?? 0) ?></em></div><?php endforeach; ?><?php if ($statuses === []): ?><div class="empty-state empty-state--compact">Нет данных по статусам.</div><?php endif; ?></article>
    <article class="panel chart-panel chart-panel--wide uds-ib-card--span-6"><div class="panel__title">По категориям</div><?php foreach ($categories as $row): ?><div class="uds-ib-chart-row"><b><?= udsIbText($row['label'] ?? '') ?></b><span style="width:<?= (int)($row['percent'] ?? max(5, (int)($row['value'] ?? 0) * 20)) ?>%"></span><em><?= (int)($row['value'] ?? 0) ?></em></div><?php endforeach; ?><?php if ($categories === []): ?><div class="empty-state empty-state--compact">Нет данных по категориям.</div><?php endif; ?></article>
    <article class="panel chart-panel chart-panel--wide uds-ib-card--span-6"><div class="panel__title">Воронка идей по этапам</div><?php foreach ($funnel as $row): ?><div class="uds-ib-chart-row"><b><?= udsIbText($row['label'] ?? '') ?></b><span style="width:<?= (int)($row['percent'] ?? 0) ?>%"></span><em><?= (int)($row['value'] ?? 0) ?></em></div><?php endforeach; ?><?php if ($funnel === []): ?><div class="empty-state empty-state--compact">Нет данных по этапам.</div><?php endif; ?></article>
    <article class="panel chart-panel chart-panel--wide uds-ib-card--span-6"><div class="panel__title">Процент реализации</div><?php foreach ($ratio as $row): ?><div class="uds-ib-chart-row"><b><?= udsIbText($row['label'] ?? '') ?></b><span style="width:<?= (int)($row['percent'] ?? 0) ?>%"></span><em><?= (int)($row['value'] ?? 0) ?></em></div><?php endforeach; ?><?php if ($ratio === []): ?><div class="empty-state empty-state--compact">Нет данных по реализации.</div><?php endif; ?></article>
</section>
<section class="stats-grid stats-grid--wide" style="margin-top:16px">
    <article class="panel chart-panel chart-panel--wide">
        <div class="panel__title">Динамика заявок по месяцам</div>
        <?php foreach ($trends as $row): ?>
            <div class="uds-ib-chart-row"><b><?= udsIbText($row['label'] ?? '') ?></b><span style="width:<?= (int)($row['percent'] ?? 0) ?>%"></span><em><?= (int)($row['submitted'] ?? 0) ?></em></div>
        <?php endforeach; ?>
        <?php if ($trends === []): ?><div class="empty-state empty-state--compact">Нет данных по динамике заявок.</div><?php endif; ?>
    </article>
    <article class="panel chart-panel chart-panel--wide">
        <div class="panel__title">Экономический эффект по месяцам</div>
        <?php foreach ($trends as $row): ?>
            <div class="rating-list__item rating-list__item--card" style="margin-bottom:12px">
                <div class="rating-list__meta">
                    <strong><?= udsIbText($row['label'] ?? '') ?></strong>
                    <span>Подтверждено: <?= udsIbMoney($row['confirmedEffect'] ?? 0) ?></span>
                </div>
                <div class="rating-list__value"><?= udsIbMoney($row['potentialEffect'] ?? 0) ?></div>
            </div>
        <?php endforeach; ?>
        <?php if ($trends === []): ?><div class="empty-state empty-state--compact">Нет данных по эффекту.</div><?php endif; ?>
    </article>
</section>
<?php if (!($meta['hasData'] ?? true)): ?><div class="empty-state"><?= udsIbText($meta['emptyMessage'] ?? 'Статистика пока недоступна.') ?></div><?php endif; ?>
<?php udsIbShellEnd(); ?>
