<?php

declare(strict_types=1);

namespace Uds\Ideabank2\Table;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\DatetimeField;

class IdeaCoinTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'b_uds_ideabank_idea_coin';
    }

    public static function getMap(): array
    {
        return [
            new IntegerField('ID', ['primary' => true, 'autocomplete' => true]),
            new IntegerField('USER_ID', ['required' => true]),
            new IntegerField('IDEA_ID'),
            new TextField('EVENT', ['required' => true]),
            new IntegerField('COINS', ['required' => true, 'default' => 0]),
            new TextField('DESCRIPTION'),
            new DatetimeField('CREATED_AT', ['required' => true]),
        ];
    }
}