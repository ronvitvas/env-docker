<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
$APPLICATION->SetPageProperty('topMenuSectionDir', '/ideabank/');
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_after.php');
$APPLICATION->SetTitle('Карточка идеи');
$APPLICATION->IncludeComponent('uds:ideabank.idea.detail', '', []);
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php');
