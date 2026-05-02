<?php

declare(strict_types=1);

if (!function_exists('udsIdeabank2OnBuildGlobalMenu')) {
    function udsIdeabank2OnBuildGlobalMenu(array &$aGlobalMenu, array &$aModuleMenu): void
    {
        foreach ($aModuleMenu as $item) {
            if (($item['items_id'] ?? '') === 'menu_uds_ideabank2') {
                return;
            }
        }

        $aModuleMenu[] = [
            'parent_menu' => 'global_menu_services',
            'section' => 'uds_ideabank2',
            'sort' => 100,
            'text' => 'Банк идей',
            'title' => 'Управление банком идей',
            'url' => '/local/admin/uds_ideabank2.php?lang=' . LANGUAGE_ID,
            'items_id' => 'menu_uds_ideabank2',
            'items' => [
                [
                    'text' => 'Управление',
                    'title' => 'Главная страница управления банком идей',
                    'url' => '/local/admin/uds_ideabank2.php?lang=' . LANGUAGE_ID,
                ],
                [
                    'text' => 'Модерация идей',
                    'title' => 'Модерация и управление идеями',
                    'url' => '/local/admin/uds_ideabank2_moderation.php?lang=' . LANGUAGE_ID,
                ],
                [
                    'text' => 'Статусы',
                    'title' => 'Управление статусами идей',
                    'url' => '/local/admin/uds_ideabank2_statuses.php?lang=' . LANGUAGE_ID,
                ],
                [
                    'text' => 'Категории',
                    'title' => 'Управление категориями',
                    'url' => '/local/admin/uds_ideabank2_categories.php?lang=' . LANGUAGE_ID,
                ],
                [
                    'text' => 'Правила наград',
                    'title' => 'Настройка начисления коинов',
                    'url' => '/local/admin/uds_ideabank2_rewards.php?lang=' . LANGUAGE_ID,
                ],
                [
                    'text' => 'Конкурсы',
                    'title' => 'Конкурсы идей',
                    'url' => '/local/admin/uds_ideabank2_contests.php?lang=' . LANGUAGE_ID,
                ],
                [
                    'text' => 'Челленджи',
                    'title' => 'Челленджи',
                    'url' => '/local/admin/uds_ideabank2_challenges.php?lang=' . LANGUAGE_ID,
                ],
            ],
        ];
    }
}
