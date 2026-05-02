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
    (string)($page['title'] ?? 'Конкурсы идей'),
    (string)($page['subtitle'] ?? 'Участвуйте в программах развития и подавайте идеи по актуальным бизнес-вызовам.')
);
?>
<section class="contest-list">
    <?php foreach ($items as $item): ?>
        <article class="panel contest-card">
            <div>
                <div class="coin-panel__eyebrow">Конкурс идей</div>
                <h2 class="contest-card__title"><a href="/ideabank/contest-detail.php?id=<?= (int)$item['ID'] ?>"><?= udsIbText($item['TITLE'] ?? '') ?></a></h2>
                <p class="contest-card__text"><?= udsIbText($item['DESCRIPTION'] ?? '', 260) ?></p>
                <div class="contest-card__meta"><span><?= udsIbText($item['DATE_LABEL'] ?? '') ?></span><span><?= udsIbText($item['STATUS'] ?? '') ?></span></div>
            </div>
            <div class="uds-ib-actions"><a class="primary-button" href="/ideabank/contest-detail.php?id=<?= (int)$item['ID'] ?>">Открыть конкурс</a><a class="text-link" href="/ideabank/docs.php">Документы</a></div>
        </article>
    <?php endforeach; ?>
    <?php if ($items === []): ?><div class="empty-state"><?= udsIbText($meta['emptyMessage'] ?? 'Конкурсов пока нет.') ?></div><?php endif; ?>
</section>
<?php udsIbShellEnd(); ?>
