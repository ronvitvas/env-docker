<?php

declare(strict_types=1);

use Bitrix\Main\Loader;
use Uds\Ideabank2\Domain\PublicDataService;

final class UdsIdeabankNewsListComponent extends CBitrixComponent
{
    public function executeComponent(): void
    {
        if (!Loader::includeModule('uds.ideabank2')) {
            ShowError('Модуль uds.ideabank2 не установлен');
            return;
        }

        $domainData = PublicDataService::getNewsListData();
        $items = is_array($domainData['items'] ?? null) ? $domainData['items'] : [];

        $this->arResult = [
            'shell' => is_array($domainData['shell'] ?? null) ? $domainData['shell'] : [],
            'page' => [
                'title' => 'Новости банка идей',
                'subtitle' => 'Истории реализованных инициатив, лучшие практики и результаты команд.',
                'items' => $items,
            ],
            'widgets' => [],
            'meta' => [
                'hasItems' => $items !== [],
                'total' => count($items),
                'emptyMessage' => 'Новостей пока нет.',
            ],
            // backward compatibility
            'items' => $items,
        ];

        $this->includeComponentTemplate();
    }
}
