<?php
declare(strict_types=1);
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');
if (!\Bitrix\Main\Loader::includeModule('uds.ideabank2')) {
    $APPLICATION->ThrowException('Модуль uds.ideabank2 не установлен');
    require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin_after.php'); exit;
}
if ($APPLICATION->GetUserRight('uds.ideabank2') < 'W') {
    $APPLICATION->AuthForm('Доступ запрещён');
    return;
}
use Uds\Ideabank2\Table\IdeaCategoryTable;
$title = "Категории идей";
$APPLICATION->SetTitle($title);
$rs = IdeaCategoryTable::getList(['order'=>['SORT'=>'ASC','NAME'=>'ASC']]);
$arRows = []; while ($r = $rs->fetch()) { $arRows[] = $r; }
if (check_bitrix_sessid() && isset($_POST['action'])) {
    if ($_POST['action']==='add') IdeaCategoryTable::add(['NAME'=>$_POST['NAME'],'CODE'=>$_POST['CODE'],'SORT'=>(int)($_POST['SORT']??500)]);
    elseif ($_POST['action']==='edit') IdeaCategoryTable::update((int)$_POST['ID'],['NAME'=>$_POST['NAME'],'CODE'=>$_POST['CODE'],'SORT'=>(int)($_POST['SORT']??500)]);
    elseif ($_POST['action']==='delete') IdeaCategoryTable::delete((int)$_POST['ID']);
    header('Location: /local/admin/uds_ideabank2_categories.php'); exit;
}
$arEdit = isset($_GET['edit']) ? IdeaCategoryTable::getList(['filter'=>['=ID'=>(int)$_GET['edit']],'limit'=>1])->fetch() : null;
if ($arEdit): $e=$arEdit; ?>
<table class="data-grid" style="max-width:500px"><tr><td><b>Редактировать категорию</b></td></tr>
<form method="post"><?php bitrix_sessid_post(); ?>
<input type="hidden" name="action" value="edit"><input type="hidden" name="ID" value="<?=$e['ID']?>">
<tr><td>Название<br><input name="NAME" value="<?=htmlspecialcharsex($e['NAME'])?>" size="30" required></td></tr>
<tr><td>Код<br><input name="CODE" value="<?=htmlspecialcharsex($e['CODE'])?>" size="20" required></td></tr>
<tr><td>Сортировка<br><input name="SORT" type="number" value="<?=$e['SORT']?>" size="5"></td></tr>
<tr><td><input type="submit" value="Сохранить" class="adm-btn-save">&nbsp;<a href="/local/admin/uds_ideabank2_categories.php">Отмена</a></td></tr>
</form></table>
<?php else: ?>
<div style="margin-bottom:10px"><a href="?add=Y">+ Добавить категорию</a> | <a href="/local/admin/uds_ideabank2.php">← Назад</a></div>
<?php if (isset($_GET['add'])): ?>
<table class="data-grid" style="max-width:500px"><tr><td><b>Добавить категорию</b></td></tr>
<form method="post"><?php bitrix_sessid_post(); ?><input type="hidden" name="action" value="add">
<tr><td>Название<br><input name="NAME" size="30" required></td></tr>
<tr><td>Код<br><input name="CODE" size="20" required></td></tr>
<tr><td>Сортировка<br><input name="SORT" type="number" value="500" size="5"></td></tr>
<tr><td><input type="submit" value="Добавить" class="adm-btn-save">&nbsp;<a href="/local/admin/uds_ideabank2_categories.php">Отмена</a></td></tr>
</form></table>
<?php else: ?>
<table class="data-grid" style="width:100%"><tr><th>ID</th><th>Название</th><th>Код</th><th>Сортировка</th><th>Действия</th></tr>
<?php foreach ($arRows as $row): ?>
<tr><td><?=$row['ID']?></td><td><b><?=$row['NAME']?></b></td><td><?=$row['CODE']?></td><td><?=$row['SORT']?></td>
<td><a href="?edit=<?=$row['ID']?>">Изменить</a> | <form method="post" style="display:inline" onsubmit="return confirm('Удалить?')"><?php bitrix_sessid_post(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="ID" value="<?=$row['ID']?>"><input type="submit" value="Удалить" class="adm-btn"></form></td></tr>
<?php endforeach; ?></table>
<?php endif; endif; ?>
<?php require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin_after.php'); ?>
