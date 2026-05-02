<?php

declare(strict_types=1);

namespace Uds\Ideabank2\Events;

use Bitrix\Main\Event;

class CoinEvents
{
    public static function onBeforeCoinAward(Event $event): ?Event
    {
        return $event;
    }
}