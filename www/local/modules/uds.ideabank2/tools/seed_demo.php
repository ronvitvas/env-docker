<?php

$_SERVER['DOCUMENT_ROOT'] = $_SERVER['DOCUMENT_ROOT'] ?: dirname(__DIR__, 4);
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_CRONTAB', true);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

if (!\Bitrix\Main\Loader::includeModule('uds.ideabank2')) {
    echo "Module uds.ideabank2 is not installed\n";
    return;
}

$result = \Uds\Ideabank2\Seed\DemoDataSeeder::run();
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
