<?php

declare(strict_types=1);

defined('BX_COMP_ENGINE_ENABLED') || define('BX_COMP_ENGINE_ENABLED', 0);
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;
use Uds\Ideabank2\AjaxApi;

if (!\Bitrix\Main\Loader::includeModule('uds.ideabank2')) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Module uds.ideabank2 is not installed']);
    die;
}

$request = Application::getInstance()->getContext()->getRequest();

if (!$request->isPost()) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'POST only']);
    die;
}

// CSRF protection
if (!check_bitrix_sessid()) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Session error. Please reload the page.']);
    die;
}

$api = new AjaxApi($request);
$api->handle();
