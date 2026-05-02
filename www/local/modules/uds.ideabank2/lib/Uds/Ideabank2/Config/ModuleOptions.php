<?php

declare(strict_types=1);

namespace Uds\Ideabank2\Config;

use Bitrix\Main\Config\Option;

final class ModuleOptions
{
    public const MODULE_ID = 'uds.ideabank2';

    private const BOOLEAN_OPTIONS = [
        'moderation_auto_approve',
        'enable_anonymous',
        'enable_expert_review',
        'enable_committee',
        'feature_public_home',
        'feature_public_news',
        'feature_public_contests',
        'feature_public_docs',
        'feature_public_stats',
        'feature_public_hall',
        'feature_public_quotes',
        'feature_public_idea_detail',
        'feature_public_idea_form',
        'feature_moderation',
        'feature_expertise',
        'feature_committee',
        'feature_drafts',
        'feature_edit_after_submit',
        'feature_comments',
        'feature_reactions',
        'feature_voting',
        'feature_coins',
        'feature_auto_coin_accrual',
        'feature_manual_coin_accrual',
        'feature_rewards',
        'feature_leaderboard',
        'feature_shop',
        'feature_shop_orders',
        'feature_shop_order_moderation',
        'feature_shop_cancel_order',
        'feature_demo_data',
        'debug_auth_enabled',
    ];

    private const INTEGER_OPTIONS = [
        'coin_submission' => ['default' => 30, 'min' => 0, 'max' => 100000],
        'coin_accepted' => ['default' => 45, 'min' => 0, 'max' => 100000],
        'coin_implemented' => ['default' => 120, 'min' => 0, 'max' => 100000],
        'max_leaderboard' => ['default' => 50, 'min' => 1, 'max' => 200],
        'shop_monthly_limit' => ['default' => 0, 'min' => 0, 'max' => 1000000],
        'shop_min_balance_after_purchase' => ['default' => 0, 'min' => 0, 'max' => 1000000],
    ];

    private const ROLE_GROUP_OPTIONS = [
        'participants' => 'group_participants_id',
        'moderators' => 'group_moderators_id',
        'experts' => 'group_experts_id',
        'committee' => 'group_committee_id',
        'admins' => 'group_admins_id',
    ];

    public static function getString(string $name, string $default = ''): string
    {
        return (string)Option::get(self::MODULE_ID, $name, $default);
    }

    public static function setString(string $name, string $value): void
    {
        Option::set(self::MODULE_ID, $name, $value);
    }

    public static function getBool(string $name, string $default = 'N'): bool
    {
        return self::getString($name, $default) === 'Y';
    }

    public static function setBool(string $name, bool $value): void
    {
        Option::set(self::MODULE_ID, $name, $value ? 'Y' : 'N');
    }

    public static function getInt(string $name): int
    {
        $rule = self::INTEGER_OPTIONS[$name] ?? ['default' => 0, 'min' => 0, 'max' => PHP_INT_MAX];

        return max(
            (int)$rule['min'],
            min((int)$rule['max'], (int)Option::get(self::MODULE_ID, $name, (string)$rule['default']))
        );
    }

    public static function setInt(string $name, int $value): void
    {
        $rule = self::INTEGER_OPTIONS[$name] ?? ['min' => 0, 'max' => PHP_INT_MAX];
        $value = max((int)$rule['min'], min((int)$rule['max'], $value));
        Option::set(self::MODULE_ID, $name, (string)$value);
    }

    public static function getFeatures(): array
    {
        $features = [];
        foreach (self::BOOLEAN_OPTIONS as $name) {
            if (str_starts_with($name, 'feature_')) {
                $features[$name] = self::getBool($name, 'Y');
            }
        }

        return $features;
    }

    public static function getBooleanOptionNames(): array
    {
        return self::BOOLEAN_OPTIONS;
    }

    public static function getIntegerOptionRules(): array
    {
        return self::INTEGER_OPTIONS;
    }

    public static function getAllowedDebugUserIds(): array
    {
        $raw = self::getString('debug_auth_allowed_user_ids', '');
        if ($raw === '') {
            return [];
        }

        $ids = preg_split('/[^0-9]+/', $raw) ?: [];

        return array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));
    }

    public static function getRoleGroupOptionNames(): array
    {
        return self::ROLE_GROUP_OPTIONS;
    }
}
