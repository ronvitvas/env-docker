<?php

defined('ADMIN_SECTION') || die();

use Bitrix\Main\Application;

$request = Application::getInstance()->getContext()->getRequest();
$deleteMode = (string)($request->getPost('uds_ideabank2_delete_mode') ?: $request->get('uds_ideabank2_delete_mode') ?: 'keep');
?>
<?php if ($deleteMode === 'full'): ?>
    <p>Модуль удалён. Выполнена полная очистка таблиц БД, настроек, пустых служебных групп и файлов раздела, созданных установщиком.</p>
<?php else: ?>
    <p>Модуль удалён. Таблицы БД, настройки и пользовательские данные сохранены.</p>
<?php endif; ?>

<form action="/bitrix/admin/partner_modules.php" method="get">
    <input type="hidden" name="lang" value="<?= htmlspecialcharsbx(defined('LANG') ? LANG : 'ru') ?>">
    <input type="submit" value="Вернуться к списку модулей">
</form>
