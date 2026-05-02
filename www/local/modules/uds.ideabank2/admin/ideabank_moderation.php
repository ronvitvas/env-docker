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
use Uds\Ideabank2\Domain\IdeaService;
use Uds\Ideabank2\Domain\NotificationService;

$title = "Модерация идей"; $APPLICATION->SetTitle($title);
$connection = \Bitrix\Main\Application::getConnection();

// Actions
$message = '';
if (check_bitrix_sessid() && isset($_POST['action'])) {
    if ($_POST['action'] === 'change_status') {
        IdeaService::changeStatus((int)$_POST['ID'], (int)$_POST['STATUS_ID']);
        NotificationService::notifyAuthorOnStatusChange((int)$_POST['ID'], (int)$_POST['STATUS_ID']);
        $message = 'Статус изменён';
    }
    elseif ($_POST['action'] === 'batch_status') {
        foreach ((array)$_POST['IDEA_IDS'] as $id) {
            IdeaService::changeStatus((int)$id, (int)$_POST['BATCH_STATUS_ID'], 0);
        }
        $message = 'Статус изменён для ' . count($_POST['IDEA_IDS']) . ' идей';
    }
    elseif ($_POST['action'] === 'delete') {
        IdeaService::delete((int)$_POST['ID']);
        $message = 'Идея удалена';
    }
}

// Filters
$filterStatus = $_GET['STATUS_ID'] ?? '';
$filterDraft = $_GET['IS_DRAFT'] ?? '';
$search = $_GET['search'] ?? '';

// Load statuses
$statuses = [];
$rs = $connection->query("SELECT * FROM b_uds_ideabank_idea_status ORDER BY SORT ASC");
while ($r = $rs->fetch()) $statuses[$r['ID']] = $r;

// Load ideas with filter — using ORM instead of raw SQL
$ormFilter = [];
if ($filterStatus) $ormFilter['=STATUS_ID'] = (int)$filterStatus;
if ($filterDraft) $ormFilter['=IS_DRAFT'] = $filterDraft === 'Y' ? 'Y' : 'N';
if ($search) $ormFilter['%=TITLE'] = '%' . $search . '%';

$ideas = [];
$result = \Uds\Ideabank2\Table\IdeaTable::getList([
    'select' => ['ID', 'TITLE', 'DESCRIPTION', 'STATUS_ID', 'CATEGORY_ID', 'OWNER_USER_ID', 'CREATED_AT', 'IS_DRAFT'],
    'filter' => $ormFilter,
    'order' => ['CREATED_AT' => 'DESC'],
    'limit' => 50,
]);
while ($idea = $result->fetch()) {
    $ideas[] = $idea;
}

// Counts for tabs
$countDraft = $connection->query("SELECT COUNT(*) c FROM b_uds_ideabank_idea WHERE IS_DRAFT='Y'")->fetch()['c'];
$countModeration = $connection->query("SELECT COUNT(*) c FROM b_uds_ideabank_idea WHERE STATUS_ID=1")->fetch()['c'];
$countAll = $connection->query("SELECT COUNT(*) c FROM b_uds_ideabank_idea")->fetch()['c'];
?>
<div style="margin-bottom:10px"><a href="/local/admin/uds_ideabank2.php">← Назад</a></div>
<?php if ($message): ?><div style="padding:10px;background:#dff0d8;margin-bottom:10px;border:1px solid #d6e9c6"><?=$message?></div><?php endif; ?>

<div style="display:flex;gap:10px;margin-bottom:15px">
<a class="adm-btn" href="?">Все (<?=$countAll?>)</a>
<a class="adm-btn" href="?IS_DRAFT=Y">Черновики (<?=$countDraft?>)</a>
<a class="adm-btn" href="?STATUS_ID=1">На модерации (<?=$countModeration?>)</a>
<?php foreach ($statuses as $st): ?>
<a class="adm-btn" href="?STATUS_ID=<?=$st['ID']?>"><?=$st['NAME']?></a>
<?php endforeach; ?>
</div>

<form method="get">
<input name="search" value="<?=htmlspecialcharsex($search)?>" placeholder="Поиск по названию..." size="40">
<input type="submit" value="Найти" class="adm-btn">
</form>

<table class="data-grid" style="width:100%;margin-top:15px">
<tr>
<th>ID</th><th>Название</th><th>Статус</th><th>Категория</th><th>Автор</th><th>Дата</th><th>Действия</th>
</tr>
<?php foreach ($ideas as $idea):
    $st = $statuses[$idea['STATUS_ID']] ?? ['NAME'=>'—','COLOR'=>'#999'];
?>
<tr>
<td><?=$idea['ID']?></td>
<td><b><?=$idea['TITLE']?></b><?php if($idea['IS_DRAFT']=='Y') echo ' <i style="color:#999">(черновик)</i>' ?></td>
<td><span style="display:inline-block;padding:2px 8px;background:<?=$st['COLOR']?>;color:#fff;border-radius:3px"><?=$st['NAME']?></span></td>
<td><?=$idea['CATEGORY_ID'] ?? '—'?></td>
<td><?=$idea['OWNER_USER_ID']?></td>
<td><?=$idea['CREATED_AT']?></td>
<td style="white-space:nowrap">
<form method="post" style="display:inline"><?php bitrix_sessid_post(); ?>
<input type="hidden" name="ID" value="<?=$idea['ID']?>">
<select name="STATUS_ID" onchange="this.form.submit()" style="font-size:11px">
<?php foreach ($statuses as $sid => $sn): ?>
<option value="<?=$sid?>"<?=($idea['STATUS_ID']==$sid)?' selected':''?>><?=$sn['NAME']?></option>
<?php endforeach; ?>
</select>
<input type="hidden" name="action" value="change_status">
</form>
| <form method="post" style="display:inline" onsubmit="return confirm('Удалить?')"><?php bitrix_sessid_post(); ?>
<input type="hidden" name="action" value="delete"><input type="hidden" name="ID" value="<?=$idea['ID']?>">
<input type="submit" value="🗑" class="adm-btn" title="Удалить">
</form>
</td>
</tr>
<?php endforeach; ?>
</table>
<?php require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'); ?>
