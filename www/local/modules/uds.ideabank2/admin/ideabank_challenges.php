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
use Uds\Ideabank2\Domain\ChallengeService;
$title = "Челленджи"; $APPLICATION->SetTitle($title);
$rs = ChallengeService::getList(); $arRows = []; while ($r = $rs->fetch()) $arRows[] = $r;
$challengeStats = ChallengeService::getStatsForChallenges(array_map(static fn(array $row): int => (int)$row['ID'], $arRows));
if (check_bitrix_sessid() && isset($_POST['action'])) {
    if ($_POST['action']==='add') ChallengeService::create($_POST);
    elseif ($_POST['action']==='edit') ChallengeService::update((int)$_POST['ID'], $_POST);
    elseif ($_POST['action']==='delete') ChallengeService::delete((int)$_POST['ID']);
    header('Location: /local/admin/uds_ideabank2_challenges.php'); exit;
}
$e = isset($_GET['edit']) ? ChallengeService::getOne((int)$_GET['edit']) : null;
$editStats = $e ? (ChallengeService::getStatsForChallenges([(int)$e['ID']])[(int)$e['ID']] ?? null) : null;
if ($e): ?>
<table class="data-grid" style="max-width:600px"><tr><td><b>Редактировать челлендж</b></td></tr>
<form method="post"><?php bitrix_sessid_post(); ?><input type="hidden" name="action" value="edit"><input type="hidden" name="ID" value="<?=$e['ID']?>">
<tr><td>Название<br><input name="TITLE" value="<?=htmlspecialcharsex($e['TITLE'])?>" size="40" required></td></tr>
<tr><td>Период<br><input name="PERIOD" value="<?=htmlspecialcharsex($e['PERIOD'])?>" size="30"></td></tr>
<tr><td>Бизнес-направление<br><input name="BUSINESS_DIRECTION" value="<?=htmlspecialcharsex($e['BUSINESS_DIRECTION'])?>" size="40"></td></tr>
<tr><td>Целевое направление<br><textarea name="TARGET" rows="3" style="width:100%"><?=htmlspecialcharsex($e['TARGET'])?></textarea></td></tr>
<tr><td>Бонус<br><input name="REWARD_BONUS" type="number" value="<?=$e['REWARD_BONUS']?>" size="5"></td></tr>
<tr><td>Советы<br><input name="TIPS" value="<?=htmlspecialcharsex($e['TIPS'])?>" size="40"></td></tr>
<?php if (is_array($editStats)): ?>
<tr><td><b>Статистика привязанных идей:</b> всего <?= (int)$editStats['total'] ?><?php foreach ((array)$editStats['items'] as $stat): ?>, <?=htmlspecialcharsex($stat['label'])?> <?= (int)$stat['value'] ?><?php endforeach; ?></td></tr>
<?php endif; ?>
<tr><td><input type="submit" value="Сохранить" class="adm-btn-save">&nbsp;<a href="/local/admin/uds_ideabank2_challenges.php">Отмена</a></td></tr>
</form></table>
<?php else: ?>
<div style="margin-bottom:10px"><a href="?add=Y">+ Добавить челлендж</a> | <a href="/local/admin/uds_ideabank2.php">← Назад</a></div>
<?php if (isset($_GET['add'])): ?>
<table class="data-grid" style="max-width:600px"><tr><td><b>Добавить челлендж</b></td></tr>
<form method="post"><?php bitrix_sessid_post(); ?><input type="hidden" name="action" value="add">
<tr><td>Название<br><input name="TITLE" size="40" required></td></tr>
<tr><td>Период<br><input name="PERIOD" size="30"></td></tr>
<tr><td>Бизнес-направление<br><input name="BUSINESS_DIRECTION" size="40"></td></tr>
<tr><td>Целевое направление<br><textarea name="TARGET" rows="3" style="width:100%"></textarea></td></tr>
<tr><td>Бонус<br><input name="REWARD_BONUS" type="number" value="0" size="5"></td></tr>
<tr><td>Советы<br><input name="TIPS" size="40"></td></tr>
<tr><td><input type="submit" value="Добавить" class="adm-btn-save">&nbsp;<a href="/local/admin/uds_ideabank2_challenges.php">Отмена</a></td></tr>
</form></table>
<?php else: ?>
<table class="data-grid" style="width:100%"><tr><th>ID</th><th>Название</th><th>Период</th><th>БН</th><th>Бонус</th><th>Идеи</th><th>Действия</th></tr>
<?php foreach ($arRows as $row): ?>
<?php $stats = $challengeStats[(int)$row['ID']] ?? ['total' => 0, 'items' => []]; ?>
<tr><td><?=$row['ID']?></td><td><b><?=$row['TITLE']?></b></td><td><?=$row['PERIOD']?></td><td><?=htmlspecialcharsex($row['BUSINESS_DIRECTION'])?></td><td><?=$row['REWARD_BONUS']?></td>
<td>Всего <?= (int)$stats['total'] ?><?php foreach ((array)$stats['items'] as $stat): ?><br><?=htmlspecialcharsex($stat['label'])?>: <?= (int)$stat['value'] ?><?php endforeach; ?></td>
<td><a href="?edit=<?=$row['ID']?>">Изменить</a> | <form method="post" style="display:inline" onsubmit="return confirm('Удалить?')"><?php bitrix_sessid_post(); ?><input type="hidden" name="action" value="delete"><input type="hidden" name="ID" value="<?=$row['ID']?>"><input type="submit" value="Удалить" class="adm-btn"></form></td></tr>
<?php endforeach; ?></table>
<?php endif; endif; ?>
<?php require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin_after.php'); ?>
