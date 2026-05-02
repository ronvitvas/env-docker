<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Uds\Ideabank2\Domain\PublicDataService;

final class UdsIdeabankContestDetailComponent extends CBitrixComponent
{
    public function executeComponent(): void
    {
        if (!Loader::includeModule('uds.ideabank2')) {
            ShowError('Модуль uds.ideabank2 не установлен');
            return;
        }

        $domainData = PublicDataService::getContestDetailData(
            Application::getInstance()->getContext()->getRequest()->getQueryList()->toArray()
        );

        $item = is_array($domainData['item'] ?? null) ? $this->prepareItem($domainData['item']) : null;

        $this->arResult = [
            'shell' => is_array($domainData['shell'] ?? null) ? $domainData['shell'] : [],
            'page' => [
                'title' => $item ? (string)($item['TITLE'] ?? 'Конкурс') : 'Конкурс не найден',
                'subtitle' => 'Детали конкурса, требования и быстрый переход к подаче идеи.',
                'item' => $item,
                'backUrl' => '/ideabank/contests.php',
            ],
            'widgets' => [
                'info' => [
                    'dateLabel' => $item['DATE_LABEL'] ?? null,
                    'deadline' => $item['DEADLINE'] ?? null,
                    'status' => $item['STATUS'] ?? null,
                ],
            ],
            'meta' => [
                'found' => $item !== null,
                'notFoundMessage' => 'Конкурс не найден.',
            ],
            // backward compatibility
            'item' => $item,
        ];

        $this->includeComponentTemplate();
    }

    private function prepareItem(array $item): array
    {
        $item['STATUS'] = $this->resolveStatus($item);

        return $item;
    }

    private function resolveStatus(array $item): string
    {
        $explicitStatus = trim((string)($item['STATUS'] ?? ''));
        if ($explicitStatus !== '') {
            return $explicitStatus;
        }

        $deadline = $item['DEADLINE'] ?? null;
        if ($deadline instanceof \Bitrix\Main\Type\Date) {
            return $deadline->getTimestamp() < time() ? 'Завершен' : 'Активен';
        }

        return 'Открыт';
    }
}
