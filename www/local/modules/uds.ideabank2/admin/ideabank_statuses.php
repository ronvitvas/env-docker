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

use Uds\Ideabank2\Table\IdeaStatusTable;

$title = "Статусы идей";
$APPLICATION->SetTitle($title);

$rs = IdeaStatusTable::getList(['order'=>['SORT'=>'ASC','NAME'=>'ASC']]);
$arRows = [];
while ($r = $rs->fetch()) { $arRows[] = $r; }

// Actions
if (check_bitrix_sessid()) {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            IdeaStatusTable::add([
                'NAME'=>$_POST['NAME'], 'CODE'=>$_POST['CODE'],
                'SORT'=>(int)($_POST['SORT']??500), 'COLOR'=>$_POST['COLOR'],
            ]);
        } elseif ($_POST['action'] === 'edit') {
            IdeaStatusTable::update((int)$_POST['ID'], [
                'NAME'=>$_POST['NAME'], 'CODE'=>$_POST['CODE'],
                'SORT'=>(int)($_POST['SORT']??500), 'COLOR'=>$_POST['COLOR'],
            ]);
        } elseif ($_POST['action'] === 'delete') {
            IdeaStatusTable::delete((int)$_POST['ID']);
        }
        CAdminUtil::Redirect('/local/admin/uds_ideabank2_statuses.php');
    }
}

// Edit mode
$arEdit = null;
if (isset($_GET['edit'])) {
    $arEdit = IdeaStatusTable::getList(['filter'=>['=ID'=>(int)$_GET['edit']],'limit'=>1])->fetch();
}

// Form
if ($arEdit):
    $entity = $arEdit;
    $actionLabel = 'Редактировать';
    $formAction = 'edit';
?>
<table class="data-grid" style="max-width:500px">
<tr><td><b>Редактировать статус</b></td></tr>
<form method="post">
<?php Bitrix\Main\CPage::ShowFormEpilog(); ?>
<input type="hidden" name="action" value="edit">
<input type="hidden" name="ID" value="<?=$entity['ID']?>">
<tr><td>Название<br><input name="NAME" value="<?=htmlspecialcharsex($entity['NAME'])?>" size="30" required></td></tr>
<tr><td>Код<br><input name="CODE" value="<?=htmlspecialcharsex($entity['CODE'])?>" size="20" required></td></tr>
<tr><td>Сортировка<br><input name="SORT" type="number" value="<?=$entity['SORT']?>" size="5"></td></tr>
<tr><td>Цвет<br><input name="COLOR" type="color" value="<?=$entity['COLOR']?>" size="7"></td></tr>
<tr><td><input type="submit" value="<?=$actionLabel?>" class="adm-btn-save">&nbsp;<a href="/local/admin/uds_ideabank2_statuses.php">Отмена</a></td></tr>
</form>
</table>
<?php
else:
?>
<div style="margin-bottom:10px">
<a href="?add=Y">+ Добавить статус</a> | <a href="/local/admin/uds_ideabank2.php">← Назад</a>
</div>
<?php if (isset($_GET['add'])): ?>
<table class="data-grid" style="max-width:500px">
<tr><td><b>Добавить статус</b></td></tr>
<form method="post">
<?php Bitrix\Main\CPage::ShowFormEpilog(); ?>
<input type="hidden" name="action" value="add">
<tr><td>Название<br><input name="NAME" size="30" required></td></tr>
<tr><td>Код<br><input name="CODE" size="20" required></td></tr>
<tr><td>Сортировка<br><input name="SORT" type="number" value="500" size="5"></td></tr>
<tr><td>Цвет<br><input name="COLOR" type="color" value="#999999" size="7"></td></tr>
<tr><td><input type="submit" value="Добавить" class="adm-btn-save">&nbsp;<a href="/local/admin/uds_ideabank2_statuses.php">Отмена</a></td></tr>
</form>
</table>
<?php else: ?>
<table class="data-grid" style="width:100%">
<tr>
<th>ID</th><th>Название</th><th>Код</th><th>Сортировка</th><th>Цвет</th><th>Действия</th>
</tr>
<?php foreach ($arRows as $row): ?>
<tr>
<td><?=$row['ID']?></td>
<td><b><?=$row['NAME']?></b></td>
<td><?=$row['CODE']?></td>
<td><?=$row['SORT']?></td>
<td><span style="display:inline-block;width:16px;height:16px;background:<?=$row['COLOR']?>;border:1px solid #ccc;vertical-align:middle"></span> <?=$row['COLOR']?></td>
<td>
<a href="?edit=<?=$row['ID']?>">Изменить</a> |
<form method="post" style="display:inline" onsubmit="return confirm('Удалить статус?')">
<?php Bitrix\Main\CPage::ShowFormEpilog(); ?>
<input type="hidden" name="action" value="delete">
<input type="hidden" name="ID" value="<?=$row['ID']?>">
<input type="submit" value="Удалить" class="adm-btn">
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; endif; ?>
<?php require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin_after.php'); ?>
