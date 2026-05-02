<?php
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
$APPLICATION->SetPageProperty('topMenuSectionDir', '/ideabank/');
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_after.php');
$APPLICATION->SetTitle('Конкурс идей');
$APPLICATION->IncludeComponent('uds:ideabank.contest.detail', '', []);
require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php');
