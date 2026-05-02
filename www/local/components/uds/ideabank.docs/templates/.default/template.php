<?php
defined('B_PROLOG_INCLUDED') || die();
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/ideabank2_public_helpers.php';

$page = is_array($arResult['page'] ?? null) ? $arResult['page'] : [];
$widgets = is_array($arResult['widgets'] ?? null) ? $arResult['widgets'] : [];
$meta = is_array($arResult['meta'] ?? null) ? $arResult['meta'] : [];

$items = is_array($page['items'] ?? null)
    ? $page['items']
    : (is_array($arResult['items'] ?? null) ? $arResult['items'] : []);

$supportCategories = is_array($widgets['supportCategories'] ?? null)
    ? $widgets['supportCategories']
    : (is_array($arResult['supportCategories'] ?? null) ? $arResult['supportCategories'] : []);

udsIbShellStart(
    is_array($arResult['shell'] ?? null) ? $arResult['shell'] : [],
    (string)($page['title'] ?? 'Документация'),
    (string)($page['subtitle'] ?? 'Регламенты, шаблоны и материалы для подачи сильных инициатив.')
);
?>
<section class="docs-grid">
    <article class="panel">
        <div class="panel__header"><div class="panel__title">Материалы</div></div>
        <div class="uds-ib-list">
            <?php foreach ($items as $item): ?>
                <?php $docCode = preg_replace('/[^a-z0-9_-]/i', '', (string)($item['code'] ?? md5((string)($item['title'] ?? 'doc')))); ?>
                <a class="doc-card" id="doc-<?= udsIbE($docCode) ?>" href="#doc-<?= udsIbE($docCode) ?>">
                    <div>
                        <span class="coin-panel__eyebrow"><?= udsIbText($item['type'] ?? 'Документ') ?></span>
                        <strong><?= udsIbText($item['title'] ?? '') ?></strong>
                        <p class="uds-ib-muted"><?= udsIbText($item['description'] ?? '', 220) ?></p>
                    </div>
                    <span class="outline-button">Открыть</span>
                </a>
            <?php endforeach; ?>
            <?php if ($items === []): ?><div class="empty-state empty-state--compact"><?= udsIbText($meta['emptyMessage'] ?? 'Материалы пока не добавлены.') ?></div><?php endif; ?>
        </div>
    </article>
    <aside class="panel"><div class="panel__title">Поддержка</div><p class="uds-ib-muted">Выберите тип проблемы при обращении к куратору.</p><div class="uds-ib-list"><?php foreach ($supportCategories as $item): ?><div class="uds-ib-list-item"><?= udsIbText($item) ?></div><?php endforeach; ?></div></aside>
</section>
<?php udsIbShellEnd(); ?>
