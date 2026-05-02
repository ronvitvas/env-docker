<?php

declare(strict_types=1);

use Bitrix\Main\Loader;
use Uds\Ideabank2\Domain\PublicDataService;

final class UdsIdeabankDocsComponent extends CBitrixComponent
{
    public function executeComponent(): void
    {
        if (!Loader::includeModule('uds.ideabank2')) {
            ShowError('Модуль uds.ideabank2 не установлен');
            return;
        }

        $domainData = PublicDataService::getDocsData();
        $items = is_array($domainData['items'] ?? null) ? $domainData['items'] : [];
        $supportCategories = is_array($domainData['supportCategories'] ?? null)
            ? $domainData['supportCategories']
            : [];

        $this->arResult = [
            'shell' => is_array($domainData['shell'] ?? null) ? $domainData['shell'] : [],
            'page' => [
                'title' => 'Документация',
                'subtitle' => 'Регламенты, шаблоны и материалы для подачи сильных инициатив.',
                'items' => $items,
            ],
            'widgets' => [
                'supportCategories' => $supportCategories,
            ],
            'meta' => [
                'hasItems' => $items !== [],
                'total' => count($items),
                'emptyMessage' => 'Материалы пока не добавлены.',
            ],
            // backward compatibility
            'items' => $items,
            'supportCategories' => $supportCategories,
        ];

        $this->includeComponentTemplate();
    }
}
