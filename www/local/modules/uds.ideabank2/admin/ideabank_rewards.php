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
use Uds\Ideabank2\Table\IdeaRewardRuleTable;
$title = "Правила наград (коины)";
$APPLICATION->SetTitle($title);
$rs = IdeaRewardRuleTable::getList(['order'=>['ID'=>'ASC']]);
$arRows = []; while ($r = $rs->fetch()) { $arRows[] = $r; }
if (check_bitrix_sessid() && isset($_POST['action'])) {
    if ($_POST['action']==='add') IdeaRewardRuleTable::add(['EVENT'=>$_POST['EVENT'],'LABEL'=>$_POST['LABEL'],'COINS'=>(int)$_POST['COINS'],'DESCRIPTION'=>$_POST['DESCRIPTION']]);
    elseif ($_POST['action']==='edit') IdeaRewardRuleTable::update((int)$_POST['ID'],['EVENT'=>$_POST['EVENT'],'LABEL'=>$_POST['LABEL'],'COINS'=>(int)$_POST['COINS'],'DESCRIPTION'=>$_POST['DESCRIPTION']]);
    elseif ($_POST['action']==='delete') IdeaRewardRuleTable::delete((int)$_POST['ID']);
    header('Location: /local/admin/uds_ideabank2_rewards.php'); exit;
}
$arEdit = isset($_GET['edit']) ? IdeaRewardRuleTable::getList(['filter'=>['=ID'=>(int)$_GET['edit']],'limit'=>1])->fetch() : null;
if ($arEdit): $e=$arEdit; ?>
<table class="data-grid" style="max-width:600px"><tr><td><b>Редактировать правило награды</b></td></tr>
<form method="post"><?php bitrix_sessid_post(); ?>
<input type="hidden" name="action" value="edit"><input type="hidden" name="ID" value="<?=$e['ID']?>">
<tr><td>Событие (event)<br><input name="EVENT" value="<?=htmlspecialcharsex($e['EVENT'])?>" size="20" required></td></tr>
<tr><td>Название<br><input name="LABEL" value="<?=htmlspecialcharsex($e['LABEL'])?>" size="40" required></td></tr>
<tr><td>Коинов<br><input name="COINS" type="number" value="<?=$e['COINS']?>" size="5" required></td></tr>
<tr><td>Описание<br><textarea name="DESCRIPTION" rows="3" style="width:100%"><?=htmlspecialcharsex($e['DESCRIPTION'])?></textarea></td></tr>
<tr><td><input type="submit" value="Сохранить" class="adm-btn-save">&nbsp;<a href="/local/admin/uds_ideabank2_rewards.php">Отмена</a></td></tr>
</form></table>
<?php else: ?>
<div style="margin-bottom:10px"><a href="?add=Y">+ Добавить правило</a> | <a href="/local/admin/uds_ideabank2.php">← Назад</a></div>
<?php if (isset($_GET['add'])): ?>
<table class="data-grid" style="max-width:600px"><tr><td><b>Добавить правило награды</b></td></tr>
<form method="post"><?php bitrix_sessid_post(); ?><input type="hidden" name="action" value="add">
<tr><td>Событие (event)<br><input name="EVENT" size="20" required placeholder="submitted, accepted..."></td></tr>
<tr><td>Название<br><input name="LABEL" size="40" required></td></tr>
<tr><td>Коинов<br><input name="COINS" type="number" value="0" size="5" required></td></tr>
<tr><td>Описание<br><textarea name="DESCRIPTION" rows="3" style="width:100%"></textarea></td></tr>
<tr><td><input type="submit" value="Добавить" class="adm-btn-save">&nbsp;<a href="/local/admin/uds_ideabank2_rewards.php">Отмена</a></td></tr>
</form></table>
<?php else: ?>
<table class="data-grid" style="width:100%"><tr><th>ID</th><th>Событие</th><th>Название</th><th>Коинов</th><th>Описание</th><th>Действия</th></tr>
<?php foreach ($arRows as $row): ?>
<tr><td><?=$row['ID']?></td><td><b><?=$row['EVENT']?></b></td><td><?=$row['LABEL']?></td><td><b><?=$row['COINS']?></b></td><td><?=mb_substr(htmlspecialcharsex($row['DESCRIPTION']),0,80)?></td>
<td><a href="?edit=<?=$row['ID']?>">Изменить</a> | <form method="post" style="display:inline" onsubmit="return confirm('Удалить?')"><?php bitrix_sessid_post(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="ID" value="<?=$row['ID']?>"><input type="submit" value="Удалить" class="adm-btn"></form></td></tr>
<?php endforeach; ?></table>
<?php endif; endif; ?>
<?php require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin_after.php'); ?>
