<?php

declare(strict_types=1);

namespace Uds\Ideabank2\Table;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\TextField;

class IdeaStatusTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'b_uds_ideabank_idea_status';
    }

    public static function getMap(): array
    {
        return [
            new IntegerField('ID', ['primary' => true, 'autocomplete' => true]),
            new TextField('NAME', ['required' => true]),
            new TextField('CODE', ['required' => true, 'unique' => true]),
            new IntegerField('SORT', ['default' => 500]),
            new TextField('COLOR'),
        ];
    }
}