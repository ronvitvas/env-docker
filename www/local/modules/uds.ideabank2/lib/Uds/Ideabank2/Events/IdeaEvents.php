<?php

declare(strict_types=1);

namespace Uds\Ideabank2\Events;

use Bitrix\Main\Event;

class IdeaEvents
{
    public static function onBeforeIdeaCreate(Event $event): ?Event
    {
        $data = $event->getParameter('DATA');
        if (isset($data['TITLE']) && mb_strlen($data['TITLE']) > 255) {
            $data['TITLE'] = mb_substr($data['TITLE'], 0, 255);
            $event->setParameter('DATA', $data);
        }
        return $event;
    }

    public static function onAfterIdeaCreate(Event $event): ?Event
    {
        $ideaId = (int)$event->getParameter('IDEA_ID');
        return $event;
    }

    public static function onBeforeIdeaStatusChange(Event $event): ?Event
    {
        return $event;
    }

    public static function onAfterIdeaStatusChange(Event $event): ?Event
    {
        return $event;
    }
}