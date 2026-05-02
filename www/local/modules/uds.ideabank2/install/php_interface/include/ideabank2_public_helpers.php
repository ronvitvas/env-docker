<?php

declare(strict_types=1);

use Bitrix\Main\Page\Asset;

if (!function_exists('udsIbE')) {
    function udsIbE(mixed $value): string
    {
        return htmlspecialcharsbx((string)($value ?? ''));
    }
}

if (!function_exists('udsIbDate')) {
    function udsIbDate(mixed $value): string
    {
        if ($value instanceof \Bitrix\Main\Type\Date || $value instanceof \Bitrix\Main\Type\DateTime) {
            return udsIbE($value->toString());
        }

        return $value ? udsIbE((string)$value) : '—';
    }
}

if (!function_exists('udsIbMoney')) {
    function udsIbMoney(mixed $value): string
    {
        return number_format((float)$value, 0, ',', ' ') . ' руб';
    }
}

if (!function_exists('udsIbCoins')) {
    function udsIbCoins(mixed $value): string
    {
        return number_format((int)$value, 0, ',', ' ') . ' коинов';
    }
}

if (!function_exists('udsIbUrl')) {
    function udsIbUrl(string $path, array $query = []): string
    {
        $url = '/ideabank/' . ltrim($path, '/');
        $query = array_filter($query, static fn($value): bool => $value !== null && $value !== '');

        return $query === [] ? $url : $url . '?' . http_build_query($query);
    }
}

if (!function_exists('udsIbText')) {
    function udsIbText(mixed $value, int $limit = 0): string
    {
        $text = trim((string)($value ?? ''));
        if ($limit > 0 && mb_strlen($text) > $limit) {
            $text = mb_substr($text, 0, $limit - 1) . '…';
        }

        return udsIbE($text !== '' ? $text : '—');
    }
}

if (!function_exists('udsIbStatusClass')) {
    function udsIbStatusClass(mixed $code): string
    {
        $code = preg_replace('/[^a-z0-9_-]+/i', '-', mb_strtolower((string)$code));

        return $code ? ' status--' . $code : '';
    }
}

if (!function_exists('udsIbInitAssets')) {
    function udsIbInitAssets(): void
    {
        $cssPath = '/local/js/uds/ideabank2/public.css';
        $jsPath = '/local/js/uds/ideabank2/public.js';
        $cssVersion = is_file($_SERVER['DOCUMENT_ROOT'] . $cssPath) ? (string)filemtime($_SERVER['DOCUMENT_ROOT'] . $cssPath) : '1';
        $jsVersion = is_file($_SERVER['DOCUMENT_ROOT'] . $jsPath) ? (string)filemtime($_SERVER['DOCUMENT_ROOT'] . $jsPath) : '1';

        Asset::getInstance()->addCss($cssPath . '?v=' . $cssVersion);
        Asset::getInstance()->addJs($jsPath . '?v=' . $jsVersion);
    }
}

if (!function_exists('udsIbShellStart')) {
    function udsIbShellStart(array $shell, string $title, string $subtitle = ''): void
    {
        udsIbInitAssets();
        ?>
        <div class="uds-ib-public">
            <main class="app-main">
        <?php
    }
}

if (!function_exists('udsIbShellEnd')) {
    function udsIbShellEnd(): void
    {
        ?>
            </main>
        </div>
        <?php
    }
}
