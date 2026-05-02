<?php

declare(strict_types=1);

namespace Uds\Ideabank2\Config;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;

final class SelfCheck
{
    private const EXPECTED_ROLE_GROUPS = [
        'participants' => ['option' => 'group_participants_id', 'string_id' => 'UDS_IDEABANK2_PARTICIPANTS'],
        'moderators' => ['option' => 'group_moderators_id', 'string_id' => 'UDS_IDEABANK2_MODERATORS'],
        'experts' => ['option' => 'group_experts_id', 'string_id' => 'UDS_IDEABANK2_EXPERTS'],
        'committee' => ['option' => 'group_committee_id', 'string_id' => 'UDS_IDEABANK2_COMMITTEE'],
        'admins' => ['option' => 'group_admins_id', 'string_id' => 'UDS_IDEABANK2_ADMINS'],
    ];

    public static function run(): array
    {
        $items = array_merge(
            self::checkFeatureFlags(),
            self::checkRoleGroups(),
            self::checkDebugAuth()
        );

        $hasErrors = false;
        foreach ($items as $item) {
            if (($item['status'] ?? '') === 'error') {
                $hasErrors = true;
                break;
            }
        }

        return [
            'ok' => !$hasErrors,
            'items' => $items,
        ];
    }

    private static function checkFeatureFlags(): array
    {
        $items = [];
        foreach (ModuleOptions::getFeatures() as $name => $enabled) {
            $raw = Option::get(ModuleOptions::MODULE_ID, $name, null);
            $items[] = [
                'code' => $name,
                'status' => in_array($raw, ['Y', 'N'], true) ? 'ok' : 'warning',
                'message' => in_array($raw, ['Y', 'N'], true)
                    ? sprintf('Feature flag задан: %s', $enabled ? 'Y' : 'N')
                    : 'Feature flag отсутствует в Option, используется значение по умолчанию',
            ];
        }

        return $items;
    }

    private static function checkRoleGroups(): array
    {
        $items = [];
        foreach (self::EXPECTED_ROLE_GROUPS as $roleCode => $group) {
            $optionName = $group['option'];
            $expectedStringId = $group['string_id'];
            $groupId = (int)ModuleOptions::getString($optionName, '0');

            if ($groupId <= 0) {
                $items[] = [
                    'code' => 'group_' . $roleCode,
                    'status' => 'error',
                    'message' => sprintf('В Option %s не сохранён ID группы', $optionName),
                ];
                continue;
            }

            $row = self::findGroupRow($groupId);
            if ($row === null) {
                $items[] = [
                    'code' => 'group_' . $roleCode,
                    'status' => 'error',
                    'message' => sprintf('Группа ID %d из Option %s не найдена', $groupId, $optionName),
                ];
                continue;
            }

            $actualStringId = (string)($row['STRING_ID'] ?? '');
            $items[] = [
                'code' => 'group_' . $roleCode,
                'status' => $actualStringId === $expectedStringId ? 'ok' : 'warning',
                'message' => $actualStringId === $expectedStringId
                    ? sprintf('Группа найдена: ID %d, STRING_ID %s', $groupId, $expectedStringId)
                    : sprintf('Группа ID %d найдена, но STRING_ID=%s вместо %s', $groupId, $actualStringId, $expectedStringId),
            ];
        }

        return $items;
    }

    private static function checkDebugAuth(): array
    {
        if (!ModuleOptions::getBool('debug_auth_enabled', 'N')) {
            return [[
                'code' => 'debug_auth_enabled',
                'status' => 'ok',
                'message' => 'Debug auth выключен',
            ]];
        }

        $allowedIds = ModuleOptions::getAllowedDebugUserIds();

        return [[
            'code' => 'debug_auth_enabled',
            'status' => $allowedIds === [] ? 'warning' : 'ok',
            'message' => $allowedIds === []
                ? 'Debug auth включён, но whitelist пользователей пуст'
                : 'Debug auth включён только для whitelist пользователей',
        ]];
    }

    private static function findGroupRow(int $groupId): ?array
    {
        $connection = Application::getConnection();
        $row = $connection->query(
            'SELECT ID, STRING_ID, NAME FROM b_group WHERE ID = ' . $groupId . ' LIMIT 1'
        )->fetch();

        return is_array($row) ? $row : null;
    }
}