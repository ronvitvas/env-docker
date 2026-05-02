<?php

declare(strict_types=1);

namespace Uds\Ideabank2\Config;

final class Feature
{
    public static function isEnabled(string $code): bool
    {
        $name = str_starts_with($code, 'feature_') ? $code : 'feature_' . $code;

        return ModuleOptions::getBool($name, 'Y');
    }

    public static function all(): array
    {
        return ModuleOptions::getFeatures();
    }
}