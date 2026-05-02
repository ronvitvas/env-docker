<?php

declare(strict_types=1);

namespace Uds\Ideabank2\Table;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\TextField;

class IdeaChallengeTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'b_uds_ideabank_idea_challenge';
    }

    public static function getMap(): array
    {
        return [
            new IntegerField('ID', ['primary' => true, 'autocomplete' => true]),
            new TextField('TITLE', ['required' => true]),
            new TextField('PERIOD'),
            new TextField('TARGET'),
            new IntegerField('REWARD_BONUS', ['default' => 0]),
            new TextField('BUSINESS_DIRECTION'),
            new TextField('TIPS'),
        ];
    }
}