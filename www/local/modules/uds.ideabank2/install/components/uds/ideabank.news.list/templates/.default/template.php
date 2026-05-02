<?php
defined('B_PROLOG_INCLUDED') || die();
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/ideabank2_public_helpers.php';

$page = is_array($arResult['page'] ?? null) ? $arResult['page'] : [];
$meta = is_array($arResult['meta'] ?? null) ? $arResult['meta'] : [];
$items = is_array($page['items'] ?? null)
    ? $page['items']
    : (is_array($arResult['items'] ?? null) ? $arResult['items'] : []);

udsIbShellStart(
    is_array($arResult['shell'] ?? null) ? $arResult['shell'] : [],
    (string)($page['title'] ?? 'Новости банка идей'),
    (string)($page['subtitle'] ?? 'Истории реализованных инициатив, лучшие практики и результаты команд.')
);
?>
<section class="news-list">
    <?php foreach ($items as $item): ?>
        <article class="panel news-card">
            <div>
                <div class="coin-panel__eyebrow"><?= udsIbText($item['CATEGORY'] ?? 'Новости') ?></div>
                <h2 class="news-card__title"><a href="/ideabank/news-detail.php?id=<?= (int)$item['ID'] ?>"><?= udsIbText($item['TITLE'] ?? '') ?></a></h2>
                <p><?= udsIbText($item['EXCERPT'] ?? '', 240) ?></p>
            </div>
            <span class="uds-ib-muted"><?= udsIbDate($item['DATE'] ?? null) ?></span>
        </article>
    <?php endforeach; ?>
    <?php if ($items === []): ?><div class="empty-state"><?= udsIbText($meta['emptyMessage'] ?? 'Новостей пока нет.') ?></div><?php endif; ?>
</section>
<?php udsIbShellEnd(); ?>
