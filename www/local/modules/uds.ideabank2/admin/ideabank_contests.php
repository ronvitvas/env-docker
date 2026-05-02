<?php
declare(strict_types=1);
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');
if (!\Bitrix\Main\Loader::includeModule('uds.ideabank2')) {
    $APPLICATION->ThrowException('Модуль не установлен');
    require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin_after.php'); exit;
}
if ($APPLICATION->GetUserRight('uds.ideabank2') < 'W') {
    $APPLICATION->AuthForm('Доступ запрещён');
    return;
}
use Uds\Ideabank2\Domain\ContestService;
$title = "Конкурсы идей"; $APPLICATION->SetTitle($title);
$rs = ContestService::getList(); $arRows = []; while ($r = $rs->fetch()) $arRows[] = $r;
if (check_bitrix_sessid() && isset($_POST['action'])) {
    if ($_POST['action']==='add') ContestService::create($_POST);
    elseif ($_POST['action']==='edit') ContestService::update((int)$_POST['ID'], $_POST);
    elseif ($_POST['action']==='delete') ContestService::delete((int)$_POST['ID']);
    header('Location: /local/admin/uds_ideabank2_contests.php'); exit;
}
$e = isset($_GET['edit']) ? ContestService::getOne((int)$_GET['edit']) : null;
if ($e): ?>
<table class="data-grid" style="max-width:600px"><tr><td><b>Редактировать конкурс</b></td></tr>
<form method="post"><?php bitrix_sessid_post(); ?><input type="hidden" name="action" value="edit"><input type="hidden" name="ID" value="<?=$e['ID']?>">
<tr><td>Название<br><input name="TITLE" value="<?=htmlspecialcharsex($e['TITLE'])?>" size="40" required></td></tr>
<tr><td>Описание<br><textarea name="DESCRIPTION" rows="3" style="width:100%"><?=htmlspecialcharsex($e['DESCRIPTION'])?></textarea></td></tr>
<tr><td>Период<br><input name="DATE_LABEL" value="<?=htmlspecialcharsex($e['DATE_LABEL'])?>" size="30"></td></tr>
<tr><td>Дедлайн<br><input name="DEADLINE" type="date" value="<?=htmlspecialcharsex($e['DEADLINE'])?>"></td></tr>
<tr><td>Требования<br><textarea name="REQUIREMENTS" rows="3" style="width:100%"><?=htmlspecialcharsex($e['REQUIREMENTS'])?></textarea></td></tr>
<tr><td><input type="submit" value="Сохранить" class="adm-btn-save">&nbsp;<a href="/local/admin/uds_ideabank2_contests.php">Отмена</a></td></tr>
</form></table>
<?php else: ?>
<div style="margin-bottom:10px"><a href="?add=Y">+ Добавить конкурс</a> | <a href="/local/admin/uds_ideabank2.php">← Назад</a></div>
<?php if (isset($_GET['add'])): ?>
<table class="data-grid" style="max-width:600px"><tr><td><b>Добавить конкурс</b></td></tr>
<form method="post"><?php bitrix_sessid_post(); ?><input type="hidden" name="action" value="add">
<tr><td>Название<br><input name="TITLE" size="40" required></td></tr>
<tr><td>Описание<br><textarea name="DESCRIPTION" rows="3" style="width:100%"></textarea></td></tr>
<tr><td>Период<br><input name="DATE_LABEL" size="30"></td></tr>
<tr><td>Дедлайн<br><input name="DEADLINE" type="date"></td></tr>
<tr><td>Требования<br><textarea name="REQUIREMENTS" rows="3" style="width:100%"></textarea></td></tr>
<tr><td><input type="submit" value="Добавить" class="adm-btn-save">&nbsp;<a href="/local/admin/uds_ideabank2_contests.php">Отмена</a></td></tr>
</form></table>
<?php else: ?>
<table class="data-grid" style="width:100%"><tr><th>ID</th><th>Название</th><th>Период</th><th>Дедлайн</th><th>Действия</th></tr>
<?php foreach ($arRows as $row): ?>
<tr><td><?=$row['ID']?></td><td><b><?=$row['TITLE']?></b></td><td><?=$row['DATE_LABEL']?></td><td><?=$row['DEADLINE']?></td>
<td><a href="?edit=<?=$row['ID']?>">Изменить</a> | <form method="post" style="display:inline" onsubmit="return confirm('Удалить?')"><?php bitrix_sessid_post(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="ID" value="<?=$row['ID']?>"><input type="submit" value="Удалить" class="adm-btn"></form></td></tr>
<?php endforeach; ?></table>
<?php endif; endif; ?>
<?php require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin_after.php'); ?>
