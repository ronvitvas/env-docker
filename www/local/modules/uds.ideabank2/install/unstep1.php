<?php

defined('ADMIN_SECTION') || die();

use Bitrix\Main\Application;

global $APPLICATION;

$request = Application::getInstance()->getContext()->getRequest();
$moduleId = (string)($request->get('id') ?: 'uds.ideabank2');
$lang = defined('LANG') ? LANG : 'ru';
?>
<form action="<?= htmlspecialcharsbx($APPLICATION->GetCurPage()) ?>" method="post">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="lang" value="<?= htmlspecialcharsbx($lang) ?>">
    <input type="hidden" name="id" value="<?= htmlspecialcharsbx($moduleId) ?>">
    <input type="hidden" name="action" value="uninstall">
    <input type="hidden" name="uninstall" value="Y">
    <input type="hidden" name="step" value="2">

    <table class="adm-detail-content-table edit-table">
        <tr>
            <td width="40%" class="adm-detail-content-cell-l">
                Режим удаления:
            </td>
            <td width="60%" class="adm-detail-content-cell-r">
                <label>
                    <input type="radio" name="uds_ideabank2_delete_mode" value="keep" checked>
                    Удалить модуль без удаления таблиц БД, настроек и пользовательских данных
                </label>
                <br>
                <label>
                    <input type="radio" name="uds_ideabank2_delete_mode" value="full">
                    Полная очистка: удалить таблицы БД, настройки, пустые служебные группы и файлы раздела, созданные установщиком
                </label>
            </td>
        </tr>
    </table>

    <p>
        <input type="submit" class="adm-btn-save" value="Удалить модуль">
    </p>
</form>
