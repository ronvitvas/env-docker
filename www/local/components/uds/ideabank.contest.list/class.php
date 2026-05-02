<?php

declare(strict_types=1);

use Bitrix\Main\Loader;
use Uds\Ideabank2\Domain\PublicDataService;

final class UdsIdeabankContestListComponent extends CBitrixComponent
{
    public function executeComponent(): void
    {
        if (!Loader::includeModule('uds.ideabank2')) {
            ShowError('Модуль uds.ideabank2 не установлен');
            return;
        }

        $domainData = PublicDataService::getContestListData();
        $items = is_array($domainData['items'] ?? null) ? $domainData['items'] : [];

        $this->arResult = [
            'shell' => is_array($domainData['shell'] ?? null) ? $domainData['shell'] : [],
            'page' => [
                'title' => 'Конкурсы идей',
                'subtitle' => 'Участвуйте в программах развития и подавайте идеи по актуальным бизнес-вызовам.',
                'items' => $this->prepareItems($items),
            ],
            'widgets' => [],
            'meta' => [
                'hasItems' => $items !== [],
                'total' => count($items),
                'emptyMessage' => 'Конкурсов пока нет.',
            ],
            // backward compatibility
            'items' => $this->prepareItems($items),
        ];

        $this->includeComponentTemplate();
    }

    private function prepareItems(array $items): array
    {
        $prepared = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $item['STATUS'] = $this->resolveStatus($item);
            $prepared[] = $item;
        }

        return $prepared;
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
