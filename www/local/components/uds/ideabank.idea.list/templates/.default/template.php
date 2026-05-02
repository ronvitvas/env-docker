<?php
defined('B_PROLOG_INCLUDED') || die();
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/ideabank2_public_helpers.php';

$pageData = is_array($arResult['page'] ?? null) ? $arResult['page'] : [];
$widgets = is_array($arResult['widgets'] ?? null) ? $arResult['widgets'] : [];
$meta = is_array($arResult['meta'] ?? null) ? $arResult['meta'] : [];
$filters = is_array($widgets['filters'] ?? null) ? $widgets['filters'] : [];
$summary = is_array($widgets['summary'] ?? null) ? $widgets['summary'] : [];
$viewCards = is_array($widgets['views'] ?? null) ? $widgets['views'] : [];
$operationCards = is_array($widgets['operations'] ?? null) ? $widgets['operations'] : [];
$items = is_array($pageData['items'] ?? null) ? $pageData['items'] : (is_array($arResult['items'] ?? null) ? $arResult['items'] : []);
$modeTabs = is_array($pageData['modeTabs'] ?? null) ? $pageData['modeTabs'] : [];
$emptyState = is_array($pageData['empty'] ?? null) ? $pageData['empty'] : [];
$query = is_array($meta['query'] ?? null) ? $meta['query'] : (is_array($arResult['query'] ?? null) ? $arResult['query'] : []);
$mode = (string)($meta['mode'] ?? $arResult['mode'] ?? 'all');
$search = (string)($meta['search'] ?? $arResult['search'] ?? '');
$pagination = is_array($meta['pagination'] ?? null) ? $meta['pagination'] : (is_array($arResult['pagination'] ?? null) ? $arResult['pagination'] : []);
$page = (int)($pagination['page'] ?? 1);
$pages = (int)($pagination['pages'] ?? 1);

unset($query['page']);

if (!function_exists('udsIbListBuildQuery')) {
    function udsIbListBuildQuery(array $baseQuery, array $overrides): string
    {
        $merged = array_merge($baseQuery, $overrides);

        return http_build_query($merged);
    }
}

udsIbShellStart(
    is_array($arResult['shell'] ?? null) ? $arResult['shell'] : [],
    (string)($pageData['title'] ?? 'Список идей'),
    (string)($pageData['subtitle'] ?? 'Рабочий реестр инициатив, черновиков, лучших практик и очередей рассмотрения.')
);
?>
<section class="panel management-shell">
    <div class="management-shell__head">
        <div>
            <div class="uds-ib-eyebrow"><?= udsIbText($pageData['eyebrow'] ?? 'Операционный контур') ?></div>
            <div class="management-shell__title"><?= udsIbText($pageData['title'] ?? 'Реестр инициатив') ?></div>
            <div class="management-shell__text"><?= udsIbText($pageData['description'] ?? 'Фильтруйте идеи по роли, статусу, категории и тексту. Открывайте карточку для обсуждения и маршрута.') ?></div>
        </div>
        <div class="page-header__actions">
            <?php foreach ((array)($pageData['actions'] ?? []) as $action): ?>
                <a class="<?= !empty($action['primary']) ? 'primary-button' : 'outline-button' ?>" href="<?= udsIbE($action['url'] ?? '#') ?>"><?= udsIbText($action['title'] ?? '') ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="tabs--management">
        <?php foreach ($modeTabs as $tab): ?>
            <a
                class="tab<?= !empty($tab['active']) ? ' is-active' : '' ?>"
                href="<?= udsIbE($tab['url'] ?? ('?' . udsIbListBuildQuery($query, ['mode' => $tab['code'] ?? $mode, 'page' => 1]))) ?>"
            >
                <?= udsIbText($tab['title'] ?? '') ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if ($viewCards !== []): ?>
        <div class="home-side-links home-side-links--inline">
            <?php foreach ($viewCards as $card): ?>
                <a class="quick-link-card <?= !empty($card['active']) ? 'is-active' : '' ?>" href="<?= udsIbE($card['url'] ?? '#') ?>">
                    <strong><?= udsIbText($card['title'] ?? '') ?><?= ($card['badge'] ?? '') !== '' ? ' · ' . udsIbText($card['badge']) : '' ?></strong>
                    <span><?= udsIbText($card['text'] ?? '') ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form class="management-filters" method="get">
        <input type="hidden" name="mode" value="<?= udsIbE($mode) ?>">
        <label class="search-field">
            <input type="search" name="q" value="<?= udsIbE($search) ?>" placeholder="Код, название, проблема, предложение">
        </label>
        <select name="status_id">
            <option value="">Все статусы</option>
            <?php foreach ((array)($filters['statuses'] ?? $arResult['statuses'] ?? []) as $s): ?>
                <option value="<?= (int)$s['ID'] ?>" <?= (int)($filters['statusId'] ?? $query['status_id'] ?? 0) === (int)$s['ID'] ? 'selected' : '' ?>>
                    <?= udsIbE($s['NAME']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="category_id">
            <option value="">Все категории</option>
            <?php foreach ((array)($filters['categories'] ?? $arResult['categories'] ?? []) as $c): ?>
                <option value="<?= (int)$c['ID'] ?>" <?= (int)($filters['categoryId'] ?? $query['category_id'] ?? 0) === (int)$c['ID'] ? 'selected' : '' ?>>
                    <?= udsIbE($c['NAME']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <button class="uds-ib-button" type="submit">Фильтровать</button>
    </form>

    <div class="management-result-bar">
        <span>Найдено: <strong><?= (int)($summary['total'] ?? $pagination['total'] ?? count($items)) ?></strong></span>
        <a class="text-link" href="<?= udsIbE($filters['resetUrl'] ?? ('?' . udsIbListBuildQuery($query, ['q' => '', 'status_id' => '', 'category_id' => '', 'page' => 1]))) ?>">Сбросить фильтры</a>
    </div>

    <?php if ($operationCards !== []): ?>
        <div class="work-metrics-grid">
            <?php foreach ($operationCards as $card): ?>
                <article class="work-metric-card"><span><?= udsIbText($card['label'] ?? '') ?></span><strong><?= udsIbText($card['value'] ?? 0) ?></strong><p><?= udsIbText($card['caption'] ?? '') ?></p></article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="idea-list">
    <?php foreach ($items as $item): ?>
        <article class="panel idea-card">
            <div class="idea-card__top">
                <a class="idea-card__title" href="<?= udsIbE($item['DETAIL_URL'] ?? ('/ideabank/ppu-detail.php?id=' . (int)$item['ID'])) ?>"><?= udsIbText($item['TITLE'] ?? '') ?></a>
                <div class="idea-card__badges">
                    <?php if ((string)($item['IS_HIDDEN'] ?? 'N') === 'Y'): ?><span class="status status--hidden">Скрытая</span><?php endif; ?>
                    <span class="status<?= udsIbStatusClass($item['STATUS']['CODE'] ?? '') ?>"><?= udsIbText($item['STATUS']['NAME'] ?? '') ?></span>
                </div>
            </div>
            <div class="idea-card__meta">
                <span><?= udsIbText($item['CODE'] ?? ('#' . (int)$item['ID'])) ?></span>
                <span><?= udsIbText($item['CATEGORY']['NAME'] ?? '') ?></span>
                <span><?= udsIbText($item['ROUTE_LABEL'] ?? '') ?></span>
                <span><?= udsIbText($item['OWNER_LABEL'] ?? '') ?></span>
                <span><?= udsIbMoney($item['ECONOMIC_EFFECT'] ?? 0) ?></span>
            </div>
            <div class="idea-card__text"><?= udsIbText($item['EXCERPT'] ?: ($item['DESCRIPTION'] ?: ($item['PROBLEM'] ?? '')), 220) ?></div>
            <div class="uds-ib-actions">
                <?php foreach ((array)($item['ACTIONS'] ?? []) as $action): ?>
                    <a class="text-link" href="<?= udsIbE($action['url'] ?? '#') ?>"><?= udsIbText($action['title'] ?? '') ?></a>
                <?php endforeach; ?>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<?php if ($items === []): ?>
    <div class="uds-ib-empty">
        <strong><?= udsIbText($emptyState['title'] ?? 'Идей пока нет.') ?></strong>
        <div><?= udsIbText($emptyState['text'] ?? 'Идей пока нет.') ?></div>
        <?php if (!empty($emptyState['action'])): ?>
            <a class="primary-button" href="<?= udsIbE($emptyState['action']['url'] ?? '/ideabank/ppu-form.php') ?>"><?= udsIbText($emptyState['action']['title'] ?? 'Создать идею') ?></a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($pages > 1): ?>
    <div class="uds-ib-actions" style="margin-top:16px;">
        <?php for ($p = 1; $p <= $pages; $p++): ?>
            <a
                class="uds-ib-secondary<?= $p === $page ? ' is-active' : '' ?>"
                href="?<?= udsIbE(udsIbListBuildQuery($query, ['page' => $p])) ?>"
            >
                <?= (int)$p ?>
            </a>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php udsIbShellEnd(); ?>
