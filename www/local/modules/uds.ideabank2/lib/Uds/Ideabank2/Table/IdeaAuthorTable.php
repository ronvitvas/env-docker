<?php

declare(strict_types=1);

namespace Uds\Ideabank2\Table;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;

class IdeaAuthorTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'b_uds_ideabank_idea_author';
    }

    public static function getMap(): array
    {
        return [
            new IntegerField('ID', ['primary' => true, 'autocomplete' => true]),
            new IntegerField('IDEA_ID', ['required' => true]),
            new IntegerField('USER_ID', ['required' => true]),
            new IntegerField('SHARE_PERCENT', ['required' => true, 'default' => 100]),
        ];
    }
}