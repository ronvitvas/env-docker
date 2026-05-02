<?php

declare(strict_types=1);

namespace Uds\Ideabank2\Table;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\TextField;

class IdeaFileTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'b_uds_ideabank_idea_file';
    }

    public static function getMap(): array
    {
        return [
            new IntegerField('ID', ['primary' => true, 'autocomplete' => true]),
            new IntegerField('IDEA_ID', ['required' => true]),
            new IntegerField('FILE_ID', ['required' => true]),
            new TextField('ORIGINAL_NAME'),
            new IntegerField('SIZE'),
            new TextField('CONTENT_TYPE'),
            new IntegerField('CREATED_BY'),
            new DatetimeField('CREATED_AT', ['required' => true]),
        ];
    }
}
