<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;
use Uds\Ideabank2\Config\ModuleOptions;
use Uds\Ideabank2\Config\SelfCheck;

defined('B_PROLOG_INCLUDED') || die();

Loc::loadMessages(__FILE__);

$moduleId = 'uds.ideabank2';

// Проверка прав
global $APPLICATION;
if ($APPLICATION->GetGroupRight('main') < 'W') {
    $APPLICATION->AuthForm(Loc::getMessage('UDS_IB_ACCESS_DENIED'));
    return;
}

$request = Application::getInstance()->getContext()->getRequest();

// Обработка POST
if ($request->isPost() && check_bitrix_sessid()) {
    foreach (['moderation_auto_approve', 'enable_anonymous', 'enable_expert_review', 'enable_committee'] as $name) {
        ModuleOptions::setBool($name, $request->getPost($name) === 'Y');
    }

    foreach (array_keys(ModuleOptions::getFeatures()) as $name) {
        ModuleOptions::setBool($name, $request->getPost($name) === 'Y');
    }

    foreach (['coin_submission', 'coin_accepted', 'coin_implemented', 'max_leaderboard', 'shop_monthly_limit', 'shop_min_balance_after_purchase'] as $name) {
        ModuleOptions::setInt($name, (int)$request->getPost($name));
    }

    $allowedDebugIds = preg_replace('/[^0-9,\s]/', '', (string)$request->getPost('debug_auth_allowed_user_ids')) ?? '';
    ModuleOptions::setString('debug_auth_allowed_user_ids', trim($allowedDebugIds));

    $APPLICATION->SetNote(Loc::getMessage('UDS_IB_OPTIONS_SAVED') ?: 'Settings saved');
}

// Чтение текущих значений
$moderationAutoApprove = ModuleOptions::getBool('moderation_auto_approve') ? 'Y' : 'N';
$coinSubmission = ModuleOptions::getInt('coin_submission');
$coinAccepted = ModuleOptions::getInt('coin_accepted');
$coinImplemented = ModuleOptions::getInt('coin_implemented');
$maxLeaderboard = ModuleOptions::getInt('max_leaderboard');
$enableAnonymous = ModuleOptions::getBool('enable_anonymous') ? 'Y' : 'N';
$enableExpertReview = ModuleOptions::getBool('enable_expert_review') ? 'Y' : 'N';
$enableCommittee = ModuleOptions::getBool('enable_committee') ? 'Y' : 'N';
$features = ModuleOptions::getFeatures();
$shopMonthlyLimit = ModuleOptions::getInt('shop_monthly_limit');
$shopMinBalanceAfterPurchase = ModuleOptions::getInt('shop_min_balance_after_purchase');
$debugAuthAllowedUserIds = ModuleOptions::getString('debug_auth_allowed_user_ids', '');
$roleGroupOptions = ModuleOptions::getRoleGroupOptionNames();
$selfCheck = SelfCheck::run();

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?>

<h1><?= Loc::getMessage('UDS_IB_OPTIONS_TITLE') ?: 'Настройки банка идей' ?></h1>

<form method="post" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPageParam()) ?>">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="mid" value="<?= htmlspecialcharsbx($moduleId) ?>">
    <table class="adm-detail-content-table edit-table">
        <thead>
            <tr>
                <th><?= Loc::getMessage('UDS_IB_OPTIONS_PARAM') ?: 'Параметр' ?></th>
                <th><?= Loc::getMessage('UDS_IB_OPTIONS_VALUE') ?: 'Значение' ?></th>
                <th><?= Loc::getMessage('UDS_IB_OPTIONS_DESC') ?: 'Описание' ?></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Автоодобрение модерации</td>
                <td>
                    <input type="checkbox" name="moderation_auto_approve" value="Y"
                        <?= $moderationAutoApprove === 'Y' ? 'checked' : '' ?>>
                </td>
                <td>Автоматически одобрять идеи при публикации</td>
            </tr>
            <tr>
                <td>Коины за отправку</td>
                <td>
                    <input type="number" name="coin_submission" value="<?= (int)$coinSubmission ?>" min="0">
                </td>
                <td>Базовый бонус за подачу идеи</td>
            </tr>
            <tr>
                <td>Коины за принятие</td>
                <td>
                    <input type="number" name="coin_accepted" value="<?= (int)$coinAccepted ?>" min="0">
                </td>
                <td>Бонус за принятие идеи в работу</td>
            </tr>
            <tr>
                <td>Коины за реализацию</td>
                <td>
                    <input type="number" name="coin_implemented" value="<?= (int)$coinImplemented ?>" min="0">
                </td>
                <td>Бонус за реализованную идею</td>
            </tr>
            <tr>
                <td>Максимум лидерборда</td>
                <td>
                    <input type="number" name="max_leaderboard" value="<?= (int)$maxLeaderboard ?>" min="1" max="200">
                </td>
                <td>Максимальное количество участников в таблице лидеров</td>
            </tr>
            <tr>
                <td>Анонимные идеи</td>
                <td>
                    <input type="checkbox" name="enable_anonymous" value="Y"
                        <?= $enableAnonymous === 'Y' ? 'checked' : '' ?>>
                </td>
                <td>Разрешить создавать анонимные идеи</td>
            </tr>
            <tr>
                <td>Экспертная оценка</td>
                <td>
                    <input type="checkbox" name="enable_expert_review" value="Y"
                        <?= $enableExpertReview === 'Y' ? 'checked' : '' ?>>
                </td>
                <td>Включить этап экспертной оценки идей</td>
            </tr>
            <tr>
                <td>Комитет</td>
                <td>
                    <input type="checkbox" name="enable_committee" value="Y"
                        <?= $enableCommittee === 'Y' ? 'checked' : '' ?>>
                </td>
                <td>Включить решение комитетом по идеям</td>
            </tr>
            <tr><th colspan="3">Feature flags публичного контура и workflow</th></tr>
            <?php foreach ($features as $featureName => $enabled): ?>
                <tr>
                    <td><?= htmlspecialcharsbx($featureName) ?></td>
                    <td><input type="checkbox" name="<?= htmlspecialcharsbx($featureName) ?>" value="Y" <?= $enabled ? 'checked' : '' ?>></td>
                    <td>Централизованный флаг возможности модуля</td>
                </tr>
            <?php endforeach; ?>
            <tr><th colspan="3">Корпоративный магазин</th></tr>
            <tr>
                <td>Месячный лимит покупок, коинов</td>
                <td><input type="number" name="shop_monthly_limit" value="<?= (int)$shopMonthlyLimit ?>" min="0"></td>
                <td>0 — без отдельного лимита MVP</td>
            </tr>
            <tr>
                <td>Минимальный остаток после покупки</td>
                <td><input type="number" name="shop_min_balance_after_purchase" value="<?= (int)$shopMinBalanceAfterPurchase ?>" min="0"></td>
                <td>Защита от списания ниже заданного баланса</td>
            </tr>
            <tr><th colspan="3">Demo/debug</th></tr>
            <tr>
                <td>Whitelist debug_auth user IDs</td>
                <td><input type="text" name="debug_auth_allowed_user_ids" value="<?= htmlspecialcharsbx($debugAuthAllowedUserIds) ?>" placeholder="1, 2, 3"></td>
                <td>Используется только если включён debug_auth_enabled и dev-only маршрут</td>
            </tr>
            <tr><th colspan="3">Группы ролей</th></tr>
            <?php foreach ($roleGroupOptions as $roleCode => $optionName): ?>
                <tr>
                    <td><?= htmlspecialcharsbx($roleCode) ?></td>
                    <td><?= (int)ModuleOptions::getString($optionName, '0') ?></td>
                    <td>Заполняется установщиком модуля, группы при удалении по умолчанию сохраняются</td>
                </tr>
            <?php endforeach; ?>
            <tr><th colspan="3">Self-check install/options</th></tr>
            <tr>
                <td>Общий статус</td>
                <td><?= $selfCheck['ok'] ? 'OK' : 'ERROR' ?></td>
                <td>Проверка групп ролей, feature flags и debug_auth</td>
            </tr>
            <?php foreach ($selfCheck['items'] as $item): ?>
                <?php
                $status = (string)($item['status'] ?? 'warning');
                $color = $status === 'ok' ? '#148f2b' : ($status === 'error' ? '#d0021b' : '#b36b00');
                ?>
                <tr>
                    <td><?= htmlspecialcharsbx((string)($item['code'] ?? 'check')) ?></td>
                    <td><span style="color: <?= htmlspecialcharsbx($color) ?>; font-weight: 600;"><?= htmlspecialcharsbx(mb_strtoupper($status)) ?></span></td>
                    <td><?= htmlspecialcharsbx((string)($item['message'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <br>
    <input type="submit" name="save" value="<?= Loc::getMessage('MAIN_SAVE') ?: 'Сохранить' ?>" class="adm-btn-save">
</form>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';