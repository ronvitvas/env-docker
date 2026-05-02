<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Uds\Ideabank2\Domain\PublicDataService;

final class UdsIdeabankNewsDetailComponent extends CBitrixComponent
{
    public function executeComponent(): void
    {
        if (!Loader::includeModule('uds.ideabank2')) {
            ShowError('Модуль uds.ideabank2 не установлен');
            return;
        }

        $domainData = PublicDataService::getNewsDetailData(
            Application::getInstance()->getContext()->getRequest()->getQueryList()->toArray()
        );

        $item = is_array($domainData['item'] ?? null) ? $domainData['item'] : null;

        $this->arResult = [
            'shell' => is_array($domainData['shell'] ?? null) ? $domainData['shell'] : [],
            'page' => [
                'title' => $item ? (string)($item['TITLE'] ?? 'Новость') : 'Новость не найдена',
                'subtitle' => $item
                    ? trim((string)($item['CATEGORY'] ?? 'Новости') . ' · ' . (string)($item['DATE'] ?? ''))
                    : '',
                'item' => $item,
                'backUrl' => '/ideabank/news.php',
            ],
            'widgets' => [
                'info' => [
                    'date' => $item['DATE'] ?? null,
                    'category' => $item['CATEGORY'] ?? null,
                ],
            ],
            'meta' => [
                'found' => $item !== null,
                'notFoundMessage' => 'Новость не найдена.',
            ],
            // backward compatibility
            'item' => $item,
        ];

        $this->includeComponentTemplate();
    }
}
