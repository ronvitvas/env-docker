<?php
defined('B_PROLOG_INCLUDED') || die();
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/ideabank2_public_helpers.php';

$page = is_array($arResult['page'] ?? null) ? $arResult['page'] : [];
$widgets = is_array($arResult['widgets'] ?? null) ? $arResult['widgets'] : [];
$meta = is_array($arResult['meta'] ?? null) ? $arResult['meta'] : [];

$item = is_array($page['item'] ?? null)
    ? $page['item']
    : (is_array($arResult['item'] ?? null) ? $arResult['item'] : null);

$title = (string)($page['title'] ?? ($item ? ('Конкурс "' . (string)($item['TITLE'] ?? '') . '"') : 'Конкурс не найден'));
$subtitle = (string)($page['subtitle'] ?? 'Детали конкурса, требования и быстрый переход к подаче идеи.');
$backUrl = (string)($page['backUrl'] ?? '/ideabank/contests.php');
$info = is_array($widgets['info'] ?? null) ? $widgets['info'] : [];

udsIbShellStart(
    is_array($arResult['shell'] ?? null) ? $arResult['shell'] : [],
    $title,
    $subtitle
);
?>
<?php if (!$item): ?>
    <div class="uds-ib-empty"><?= udsIbText($meta['notFoundMessage'] ?? 'Конкурс не найден.') ?></div>
<?php else: ?>
    <section class="detail-layout detail-layout--contest">
        <article class="panel detail-article"><div class="coin-panel__eyebrow">Конкурс идей</div><h2><?= udsIbText($item['TITLE'] ?? '') ?></h2><p class="page-subtitle"><?= udsIbText($item['DESCRIPTION'] ?? '', 360) ?></p><div class="panel__title">Требования</div><p><?= nl2br(udsIbE($item['REQUIREMENTS'] ?? '')) ?></p></article>
        <aside class="panel contest-side"><div class="status"><?= udsIbText($info['dateLabel'] ?? ($item['DATE_LABEL'] ?? '')) ?></div><div class="summary-row"><div class="summary-row__label">Дедлайн</div><div><?= udsIbDate($info['deadline'] ?? ($item['DEADLINE'] ?? null)) ?></div></div><div class="summary-row"><div class="summary-row__label">Статус</div><div><?= udsIbText($info['status'] ?? ($item['STATUS'] ?? '')) ?></div></div><a class="primary-button" href="/ideabank/ppu-form.php?contest_id=<?= (int)$item['ID'] ?>">Подать идею на конкурс</a><a class="text-link" href="<?= udsIbE($backUrl) ?>">Вернуться к конкурсам</a><p class="uds-ib-muted">Заявки принимаются до указанного срока включительно.</p></aside>
    </section>
<?php endif; ?>
<?php udsIbShellEnd(); ?>
