<?php
defined('B_PROLOG_INCLUDED') || die();
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/ideabank2_public_helpers.php';

$page = is_array($arResult['page'] ?? null) ? $arResult['page'] : [];
$widgets = is_array($arResult['widgets'] ?? null) ? $arResult['widgets'] : [];
$meta = is_array($arResult['meta'] ?? null) ? $arResult['meta'] : [];
$items = is_array($page['items'] ?? null)
    ? $page['items']
    : (is_array($arResult['items'] ?? null) ? $arResult['items'] : []);
$summary = is_array($widgets['summary'] ?? null) ? $widgets['summary'] : [];
$pagination = is_array($meta['pagination'] ?? null) ? $meta['pagination'] : [];
$tabs = is_array($meta['tabs'] ?? null) ? $meta['tabs'] : [];
$mode = (string)($meta['mode'] ?? 'author');

udsIbShellStart(
    is_array($arResult['shell'] ?? null) ? $arResult['shell'] : [],
    (string)($page['title'] ?? 'Аллея славы'),
    (string)($page['subtitle'] ?? 'Рейтинг авторов и амбассадоров улучшений по коинам.')
);
?>
<section class="panel panel--accent page-intro-shell hall-shell">
    <div class="page-intro-shell__top">
        <div>
            <div class="page-intro-shell__eyebrow"><?= udsIbText($page['eyebrow'] ?? 'Лидеры банка идей') ?></div>
            <div class="management-shell__title"><?= udsIbText($page['title'] ?? 'Аллея славы') ?></div>
            <div class="management-shell__text"><?= udsIbText($page['description'] ?? '') ?></div>
        </div>
    </div>
    <?php if ($tabs !== []): ?>
        <div class="tabs tabs--surface" style="margin-top:16px">
            <?php foreach ($tabs as $tab): ?>
                <a
                    class="tab <?= ($tab['code'] ?? '') === $mode ? 'is-active' : '' ?>"
                    href="<?= udsIbE(udsIbUrl('hall-of-fame.php', ['mode' => $tab['code'] ?? 'author', 'page' => 1])) ?>"
                >
                    <?= udsIbText($tab['title'] ?? '') ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="trust-metrics-grid" style="margin-top:16px">
        <article class="trust-metric-card"><strong><?= (int)($summary['total'] ?? 0) ?></strong><span>Участников в рейтинге</span></article>
        <article class="trust-metric-card"><strong><?= udsIbCoins($summary['topCoins'] ?? 0) ?></strong><span><?= udsIbText($summary['topLabel'] ?? 'Баланс лидера') ?></span></article>
        <article class="trust-metric-card"><strong><?= (int)($summary['shown'] ?? count($items)) ?></strong><span>Показано на странице</span></article>
    </div>
</section>

<section class="panel ranking-showcase" style="margin-top:16px">
    <div class="panel__header"><div><div class="coin-panel__eyebrow">Лидеры вовлечения</div><div class="panel__title">Рейтинг авторов и амбассадоров идей</div></div></div>
    <div class="rating-list rating-list--grid hall-grid">
        <?php foreach ($items as $item): ?>
            <article class="rating-list__item rating-list__item--card hall-card <?= (int)($item['RANK'] ?? 0) <= 3 ? 'hall-card--top' : '' ?>">
                <div class="hall-card__place">Место в рейтинге: <?= (int)($item['RANK'] ?? 0) ?></div>
                <div class="hall-card__person">
                    <div class="hall-card__avatar"><?= udsIbText($item['USER_INITIALS'] ?? 'У') ?></div>
                    <div class="rating-list__meta hall-card__meta">
                        <strong><?= udsIbText($item['USER_LABEL'] ?? '') ?></strong>
                        <span><?= udsIbText($item['ROLE_LABEL'] ?? 'Участник банка идей') ?></span>
                    </div>
                    <div class="hall-card__score">
                        <strong><?= udsIbCoins($item['SCORE'] ?? $item['COINS'] ?? 0) ?></strong>
                        <span><?= udsIbText($summary['scoreCaption'] ?? 'в рейтинге') ?></span>
                    </div>
                </div>
                <?php if ((int)($item['IDEA_COUNT'] ?? 0) > 0): ?>
                    <div class="uds-ib-muted" style="margin-top:12px">Идей с зафиксированным тиражированием: <?= (int)$item['IDEA_COUNT'] ?></div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
    <?php if ($items === []): ?><div class="empty-state"><?= udsIbText($meta['emptyMessage'] ?? 'Рейтинг пока пуст.') ?></div><?php endif; ?>

    <?php if ((int)($pagination['pages'] ?? 1) > (int)($pagination['page'] ?? 1)): ?>
        <div class="panel__footer panel__footer--center">
            <a
                class="outline-button"
                href="<?= udsIbE(udsIbUrl('hall-of-fame.php', ['mode' => $mode, 'page' => ((int)($pagination['page'] ?? 1) + 1)])) ?>"
            >Показать еще</a>
        </div>
    <?php endif; ?>

    <?php if ((int)($pagination['pages'] ?? 1) > 1): ?>
        <div class="pagination">
            <?php for ($pageNumber = 1, $pages = (int)$pagination['pages']; $pageNumber <= $pages; $pageNumber++): ?>
                <a class="pagination__button <?= $pageNumber === (int)($pagination['page'] ?? 1) ? 'is-active' : '' ?>" href="<?= udsIbE(udsIbUrl('hall-of-fame.php', ['mode' => $mode, 'page' => $pageNumber])) ?>"><?= $pageNumber ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</section>
<?php udsIbShellEnd(); ?>
