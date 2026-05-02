<?php
declare(strict_types=1);
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

if (!\Bitrix\Main\Loader::includeModule('uds.ideabank2')) {
    $APPLICATION->ThrowException('Модуль uds.ideabank2 не установлен');
    require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin_after.php');
    exit;
}

if ($APPLICATION->GetUserRight('uds.ideabank2') < 'W') {
    $APPLICATION->AuthForm('Доступ запрещён');
    return;
}

$title = "Банк идей — управление";
$APPLICATION->SetTitle($title);

// Stats
$connection = \Bitrix\Main\Application::getConnection();

$stats = [
    ['label' => 'Всего идей', 'value' => $connection->query("SELECT COUNT(*) as c FROM b_uds_ideabank_idea")->fetch()['c']],
    ['label' => 'Черновики', 'value' => $connection->query("SELECT COUNT(*) as c FROM b_uds_ideabank_idea WHERE IS_DRAFT='Y'")->fetch()['c']],
    ['label' => 'Опубликовано', 'value' => $connection->query("SELECT COUNT(*) as c FROM b_uds_ideabank_idea WHERE STATUS_ID=2")->fetch()['c']],
    ['label' => 'Реализовано', 'value' => $connection->query("SELECT COUNT(*) as c FROM b_uds_ideabank_idea WHERE STATUS_ID=8")->fetch()['c']],
    ['label' => 'Коинов начислено', 'value' => $connection->query("SELECT COALESCE(SUM(COINS),0) as c FROM b_uds_ideabank_idea_coin")->fetch()['c']],
];

?>
<div style="padding:20px 0">
<h2>Банк идей — управление</h2>

<table class="data-grid" style="margin:20px 0;max-width:600px">
<?php foreach ($stats as $s): ?>
<tr><td style="width:40%"><b><?=$s['label']?></b></td><td><?=$s['value']?></td></tr>
<?php endforeach; ?>
</table>

<h3>Управление справочниками</h3>
<table class="data-grid" style="max-width:600px">
<tr><td><a href="/local/admin/uds_ideabank2_statuses.php">Статусы</a></td><td>Управление статусами идей</td></tr>
<tr><td><a href="/local/admin/uds_ideabank2_categories.php">Категории</a></td><td>Управление категориями</td></tr>
<tr><td><a href="/local/admin/uds_ideabank2_rewards.php">Правила наград</a></td><td>Настройка начисления коинов</td></tr>
</table>

<h3>Справочники</h3>
<table class="data-grid" style="max-width:600px">
<tr><td><a href="/local/admin/uds_ideabank2_contests.php">Конкурсы</a></td><td>Конкурсы идей</td></tr>
<tr><td><a href="/local/admin/uds_ideabank2_challenges.php">Челленджи</a></td><td>Челленджи</td></tr>
<tr><td><a href="/local/admin/uds_ideabank2.php">Новости</a></td><td>Новости банка идей</td></tr>
</table>
</div>
<?php require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'); ?>
