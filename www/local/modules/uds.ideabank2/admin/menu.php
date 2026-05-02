<?php

use Bitrix\Main\Loader;

if (!Loader::includeModule('uds.ideabank2')) {
    return [];
}

return [
    'parent_menu' => 'global_menu_services',
    'section' => 'uds_ideabank2',
    'sort' => 100,
    'text' => 'Банк идей',
    'title' => 'Управление банком идей',
    'url' => '/local/admin/uds_ideabank2.php',
    'items_id' => 'menu_uds_ideabank2',
    'items' => [
        [
            'text' => 'Управление',
            'title' => 'Главная страница управления банком идей',
            'url' => '/local/admin/uds_ideabank2.php',
        ],
        [
            'text' => 'Модерация идей',
            'title' => 'Модерация и управление идеями',
            'url' => '/local/admin/uds_ideabank2_moderation.php',
        ],
        [
            'text' => 'Статусы',
            'title' => 'Управление статусами идей',
            'url' => '/local/admin/uds_ideabank2_statuses.php',
        ],
        [
            'text' => 'Категории',
            'title' => 'Управление категориями',
            'url' => '/local/admin/uds_ideabank2_categories.php',
        ],
        [
            'text' => 'Правила наград',
            'title' => 'Настройка начисления коинов',
            'url' => '/local/admin/uds_ideabank2_rewards.php',
        ],
        [
            'text' => 'Конкурсы',
            'title' => 'Конкурсы идей',
            'url' => '/local/admin/uds_ideabank2_contests.php',
        ],
        [
            'text' => 'Челленджи',
            'title' => 'Челленджи',
            'url' => '/local/admin/uds_ideabank2_challenges.php',
        ],
    ],
];