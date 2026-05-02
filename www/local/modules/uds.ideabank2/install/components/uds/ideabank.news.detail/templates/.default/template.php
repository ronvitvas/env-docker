<?php
defined('B_PROLOG_INCLUDED') || die();
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/ideabank2_public_helpers.php';

$page = is_array($arResult['page'] ?? null) ? $arResult['page'] : [];
$widgets = is_array($arResult['widgets'] ?? null) ? $arResult['widgets'] : [];
$meta = is_array($arResult['meta'] ?? null) ? $arResult['meta'] : [];

$item = is_array($page['item'] ?? null)
    ? $page['item']
    : (is_array($arResult['item'] ?? null) ? $arResult['item'] : null);

$title = (string)($page['title'] ?? ($item ? (string)($item['TITLE'] ?? '') : 'Новость не найдена'));
$subtitle = (string)($page['subtitle'] ?? ($item ? (($item['CATEGORY'] ?? 'Новости') . ' · ' . udsIbDate($item['DATE'] ?? null)) : ''));
$backUrl = (string)($page['backUrl'] ?? '/ideabank/news.php');
$info = is_array($widgets['info'] ?? null) ? $widgets['info'] : [];

udsIbShellStart(
    is_array($arResult['shell'] ?? null) ? $arResult['shell'] : [],
    $title,
    $subtitle
);
?>
<?php if (!$item): ?>
    <div class="uds-ib-empty"><?= udsIbText($meta['notFoundMessage'] ?? 'Новость не найдена.') ?></div>
<?php else: ?>
    <section class="detail-layout">
        <article class="panel detail-article"><div class="coin-panel__eyebrow"><?= udsIbText($item['CATEGORY'] ?? 'Новости') ?></div><h2><?= udsIbText($item['TITLE'] ?? '') ?></h2><p class="page-subtitle"><?= udsIbText($item['EXCERPT'] ?? '', 320) ?></p><div><?= nl2br(udsIbE($item['BODY'] ?? '')) ?></div><?php if (!empty($item['QUOTE'])): ?><blockquote class="panel panel--accent quote-card" style="margin-top:16px"><?= udsIbText($item['QUOTE']) ?></blockquote><?php endif; ?></article>
        <aside class="panel related-panel"><div class="panel__title">Информация</div><div class="summary-row"><div class="summary-row__label">Дата</div><div><?= udsIbDate($info['date'] ?? ($item['DATE'] ?? null)) ?></div></div><div class="summary-row"><div class="summary-row__label">Категория</div><div><?= udsIbText($info['category'] ?? ($item['CATEGORY'] ?? '')) ?></div></div><a class="text-link" href="<?= udsIbE($backUrl) ?>">Вернуться к новостям</a></aside>
    </section>
<?php endif; ?>
<?php udsIbShellEnd(); ?>
