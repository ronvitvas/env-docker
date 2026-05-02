<?php

declare(strict_types=1);

define('NOT_CHECK_PERMISSIONS', true);
define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Uds\Ideabank2\Debug\DebugAuth;

$request = Application::getInstance()->getContext()->getRequest();

if (!Loader::includeModule('uds.ideabank2')) {
    http_response_code(503);
    echo 'Module uds.ideabank2 is not available';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
    return;
}

$error = '';
$requestedRedirect = DebugAuth::getSafeRedirect((string)$request->get('redirect'));

if ($request->isPost()) {
    $userId = (int)$request->getPost('user_id');
    $requestedRedirect = DebugAuth::getSafeRedirect((string)$request->getPost('redirect'));

    if (!check_bitrix_sessid()) {
        $error = 'Некорректная CSRF-сессия.';
    } elseif (!DebugAuth::canAuthorizeUser($userId)) {
        $error = 'Пользователь не разрешён для debug_auth или инструмент выключен.';
    } else {
        global $USER;
        $USER->Authorize($userId);
        LocalRedirect($requestedRedirect);
    }
}

$allowedUserIds = DebugAuth::getAllowedUserIds();
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ideabank debug auth</title>
</head>
<body style="margin:0; font-family: Arial, sans-serif; background:#f3f6fb; color:#172033;">
<div class="ideabank-debug-auth" style="max-width:760px; margin:24px auto; padding:20px; border:1px solid #d7d7d7; border-radius:8px; background:#fff;">
    <h1 style="margin-top:0;">Ideabank debug auth</h1>
    <p><strong>Статус:</strong> <?= htmlspecialcharsbx(DebugAuth::getStatusMessage()) ?></p>
    <p><strong>Whitelist user IDs:</strong> <?= htmlspecialcharsbx($allowedUserIds === [] ? 'пусто' : implode(', ', $allowedUserIds)) ?></p>

    <?php if ($error !== ''): ?>
        <div style="margin: 12px 0; padding: 12px; color: #8a1f11; background: #fde9e7; border: 1px solid #f5b7b1;">
            <?= htmlspecialcharsbx($error) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPageParam('', ['logout', 'login', 'USER_LOGIN'])) ?>">
        <?= bitrix_sessid_post() ?>
        <input type="hidden" name="redirect" value="<?= htmlspecialcharsbx($requestedRedirect) ?>">
        <label for="debug-auth-user-id">User ID из whitelist</label><br>
        <input id="debug-auth-user-id" type="number" name="user_id" min="1" value="<?= (int)($allowedUserIds[0] ?? 0) ?>" style="width: 180px; margin: 8px 0;">
        <br>
        <button type="submit" <?= DebugAuth::isEnabled() && DebugAuth::isDevEnvironment() ? '' : 'disabled' ?>>Авторизоваться</button>
    </form>

    <p style="margin-top: 16px; color: #666;">
        Инструмент предназначен только для локальных dev/test E2E-проверок. Он не принимает пароль,
        не авторизует пользователей вне whitelist и не выполняет redirect за пределы <code>/ideabank/</code>.
    </p>
</div>
</body>
</html>
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
