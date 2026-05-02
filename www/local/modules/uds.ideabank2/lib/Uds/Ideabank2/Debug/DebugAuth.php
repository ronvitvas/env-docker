<?php

declare(strict_types=1);

namespace Uds\Ideabank2\Debug;

use Bitrix\Main\Application;
use Bitrix\Main\Context;
use Uds\Ideabank2\Config\ModuleOptions;

final class DebugAuth
{
    private const DEFAULT_REDIRECT = '/ideabank/';
    private const ALLOWED_HOSTS = [
        'localhost',
        '127.0.0.1',
        'dev.bx',
    ];

    public static function isEnabled(): bool
    {
        return ModuleOptions::getBool('debug_auth_enabled', 'N');
    }

    public static function isDevEnvironment(): bool
    {
        $request = Context::getCurrent()->getRequest();
        $host = mb_strtolower((string)$request->getHttpHost());
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        if (in_array($host, self::ALLOWED_HOSTS, true)) {
            return true;
        }

        return (string)getenv('UDS_IDEABANK2_DEBUG_AUTH') === 'Y';
    }

    public static function getAllowedUserIds(): array
    {
        return ModuleOptions::getAllowedDebugUserIds();
    }

    public static function canAuthorizeUser(int $userId): bool
    {
        return $userId > 0
            && self::isEnabled()
            && self::isDevEnvironment()
            && in_array($userId, self::getAllowedUserIds(), true);
    }

    public static function getSafeRedirect(string $requestedRedirect): string
    {
        $redirect = trim($requestedRedirect);
        if ($redirect === '') {
            return self::DEFAULT_REDIRECT;
        }

        if (preg_match('#^https?://#i', $redirect) || str_starts_with($redirect, '//')) {
            return self::DEFAULT_REDIRECT;
        }

        if (!str_starts_with($redirect, '/ideabank/')) {
            return self::DEFAULT_REDIRECT;
        }

        return $redirect;
    }

    public static function getStatusMessage(): string
    {
        if (!self::isEnabled()) {
            return 'Debug auth выключен настройкой debug_auth_enabled.';
        }

        if (!self::isDevEnvironment()) {
            return 'Debug auth доступен только на локальных dev/test hostnames или при UDS_IDEABANK2_DEBUG_AUTH=Y.';
        }

        if (self::getAllowedUserIds() === []) {
            return 'Debug auth включён, но whitelist debug_auth_allowed_user_ids пуст.';
        }

        return 'Debug auth включён для whitelist пользователей.';
    }
}