<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
$APPLICATION->SetPageProperty('topMenuSectionDir', '/ideabank/');
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_after.php');
$APPLICATION->SetTitle('Список идей');
$APPLICATION->IncludeComponent('uds:ideabank.idea.list', '', []);
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php');
