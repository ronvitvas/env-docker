<?php

declare(strict_types=1);

namespace Uds\Ideabank2\Table;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\DateField;

class IdeaContestTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'b_uds_ideabank_idea_contest';
    }

    public static function getMap(): array
    {
        return [
            new IntegerField('ID', ['primary' => true, 'autocomplete' => true]),
            new TextField('TITLE', ['required' => true]),
            new TextField('DESCRIPTION'),
            new TextField('DATE_LABEL'),
            new DateField('DEADLINE'),
            new IntegerField('ORGANIZER_USER_ID'),
            new TextField('IMAGE'),
            new TextField('REQUIREMENTS'),
        ];
    }
}