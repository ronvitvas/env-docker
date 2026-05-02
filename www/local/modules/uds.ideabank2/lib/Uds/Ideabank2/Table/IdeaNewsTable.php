<?php

declare(strict_types=1);

namespace Uds\Ideabank2\Table;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\TextField;
use Bitrix\Main\ORM\Fields\DateField;

class IdeaNewsTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'b_uds_ideabank_idea_news';
    }

    public static function getMap(): array
    {
        return [
            new IntegerField('ID', ['primary' => true, 'autocomplete' => true]),
            new TextField('CATEGORY'),
            new TextField('TITLE', ['required' => true]),
            new TextField('EXCERPT'),
            new TextField('BODY'),
            new TextField('IMAGE'),
            new TextField('HERO_IMAGE'),
            new IntegerField('AUTHOR_USER_ID'),
            new DateField('DATE'),
            new TextField('QUOTE'),
        ];
    }
}