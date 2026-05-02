<?php

declare(strict_types=1);

namespace Uds\Ideabank2\Table;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\FloatField;
use Bitrix\Main\ORM\Fields\BooleanField;
use Bitrix\Main\ORM\Fields\DateField;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\Enums\EnumField;
use Bitrix\Main\ORM\Relations\belongsTo;
use Bitrix\Main\Type\DateTime;

class IdeaTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'b_uds_ideabank_idea';
    }

    public static function getMap(): array
    {
        return [
            new IntegerField('ID', [
                'primary' => true,
                'autocomplete' => true,
            ]),
            new TextField('TITLE', [
                'required' => true,
                'searchable' => true,
                'title' => 'Название',
            ]),
            new TextField('CODE', [
                'title' => 'Код',
            ]),
            new TextField('TYPE', [
                'title' => 'Тип',
                'default' => 'Идея улучшения',
            ]),
            new IntegerField('CATEGORY_ID', [
                'title' => 'Категория',
            ]),
            new IntegerField('STATUS_ID', [
                'required' => true,
                'title' => 'Статус',
                'default' => 1,
            ]),
            new TextField('STAGE', [
                'title' => 'Этап',
            ]),
            new TextField('DESCRIPTION', [
                'title' => 'Описание',
            ]),
            new TextField('PROBLEM', [
                'title' => 'Проблема',
            ]),
            new TextField('LOSSES', [
                'title' => 'Потери',
            ]),
            new TextField('PROPOSAL', [
                'title' => 'Предложение',
            ]),
            new FloatField('ECONOMIC_EFFECT', [
                'title' => 'Экономический эффект',
                'default' => 0,
            ]),
            new FloatField('CONFIRMED_EFFECT', [
                'title' => 'Подтверждённый эффект',
                'default' => 0,
            ]),
            new IntegerField('IMPLEMENTATION_DAYS', [
                'title' => 'Срок реализации (дней)',
                'default' => 0,
            ]),
            new IntegerField('OWNER_USER_ID', [
                'required' => true,
                'title' => 'Владелец',
            ]),
            new IntegerField('ASSIGNEE_USER_ID', [
                'title' => 'Исполнитель',
            ]),
            (new BooleanField('IS_DRAFT'))
                ->configureStorageValues('N', 'Y')
                ->configureDefaultValue(false),
            (new BooleanField('IS_ANONYMOUS'))
                ->configureStorageValues('N', 'Y')
                ->configureDefaultValue(false),
            (new BooleanField('IS_HIDDEN'))
                ->configureStorageValues('N', 'Y')
                ->configureDefaultValue(false),
            new IntegerField('SOURCE_ID', [
                'title' => 'Источник (ID идеи)',
            ]),
            new TextField('BUSINESS_DIRECTION', [
                'title' => 'Бизнес-направление',
            ]),
            new TextField('KEYWORDS', [
                'title' => 'Ключевые слова',
            ]),
            new TextField('ADDITIONAL_WORK', [
                'title' => 'Дополнительная работа',
            ]),
            new TextField('EXTRA_EFFECTS', [
                'title' => 'Дополнительные эффекты',
            ]),
            new TextField('ECONOMIC_EFFECT_TEXT', [
                'title' => 'Текст экономического эффекта',
            ]),
            new TextField('IMPLEMENTATION_PLAN', [
                'title' => 'План реализации',
            ]),
            new TextField('RESOURCES_NEEDED', [
                'title' => 'Необходимые ресурсы',
            ]),
            new TextField('RISKS', [
                'title' => 'Риски',
            ]),
            new DateField('TARGET_DATE', [
                'title' => 'Целевая дата',
            ]),
            new IntegerField('PROGRESS_PERCENT', [
                'title' => 'Прогресс %',
                'default' => 0,
            ]),
            new DatetimeField('SUBMISSION_REWARD_GRANTED_AT', [
                'title' => 'Награда за отправку',
            ]),
            new IntegerField('SUBMISSION_COIN_REWARD', [
                'title' => 'Коинов за отправку',
            ]),
            new DatetimeField('ACCEPTED_REWARD_GRANTED_AT', [
                'title' => 'Награда за принятие',
            ]),
            new IntegerField('ACCEPTED_COIN_REWARD', [
                'title' => 'Коинов за принятие',
            ]),
            new DatetimeField('IMPLEMENTED_REWARD_GRANTED_AT', [
                'title' => 'Награда за реализацию',
            ]),
            new IntegerField('IMPLEMENTED_COIN_REWARD', [
                'title' => 'Коинов за реализацию',
            ]),
            new DatetimeField('REPLICATION_REWARD_GRANTED_AT', [
                'title' => 'Награда за тиражирование',
            ]),
            new IntegerField('REPLICATION_COIN_REWARD', [
                'title' => 'Коинов за тиражирование',
            ]),
            new DatetimeField('ENGAGEMENT_REWARD_GRANTED_AT', [
                'title' => 'Награда за вовлечённость',
            ]),
            new IntegerField('ENGAGEMENT_COIN_REWARD', [
                'title' => 'Коинов за вовлечённость',
            ]),
            new DatetimeField('MODERATED_AT', [
                'title' => 'Дата модерации',
            ]),
            new IntegerField('MODERATED_BY', [
                'title' => 'Модератор',
            ]),
            new TextField('MODERATION_DECISION', [
                'title' => 'Решение модерации',
            ]),
            new TextField('MODERATION_FEEDBACK', [
                'title' => 'Обратная связь модератора',
            ]),
            new DatetimeField('PUBLISHED_AT', [
                'title' => 'Дата публикации',
            ]),
            new DatetimeField('CREATED_AT', [
                'required' => true,
                'title' => 'Создано',
            ]),
            new DatetimeField('SUBMITTED_AT', [
                'title' => 'Отправлено',
            ]),
            new DatetimeField('UPDATED_AT', [
                'title' => 'Обновлено',
            ]),
        ];
    }
}
