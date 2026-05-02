<?php
declare(strict_types=1);

namespace Uds\Ideabank2\Domain;

use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use Uds\Ideabank2\Table\IdeaContestTable;

class ContestService
{
    public static function create(array $data): int
    {
        $connection = Application::getConnection();
        $id = $connection->insert('b_uds_ideabank_idea_contest', [
            'TITLE' => $data['TITLE'] ?? '',
            'DESCRIPTION' => $data['DESCRIPTION'] ?? '',
            'DATE_LABEL' => $data['DATE_LABEL'] ?? '',
            'DEADLINE' => !empty($data['DEADLINE']) ? $data['DEADLINE'] : null,
            'ORGANIZER_USER_ID' => (int)($data['ORGANIZER_USER_ID'] ?? 0),
            'IMAGE' => $data['IMAGE'] ?? '',
            'REQUIREMENTS' => $data['REQUIREMENTS'] ?? '',
        ]);
        return (int)$id;
    }

    public static function update(int $id, array $data): bool
    {
        $fields = [];
        foreach (['TITLE','DESCRIPTION','DATE_LABEL','DEADLINE','ORGANIZER_USER_ID','IMAGE','REQUIREMENTS'] as $f) {
            if (isset($data[$f])) $fields[$f] = $data[$f];
        }
        if (empty($fields)) return false;
        $connection = Application::getConnection();
        $connection->update('b_uds_ideabank_idea_contest', $fields, ['ID' => $id]);
        return true;
    }

    public static function delete(int $id): bool
    {
        $connection = Application::getConnection();
        $connection->delete('b_uds_ideabank_idea_contest_part', ['CONTEST_ID' => $id]);
        return (bool)$connection->delete('b_uds_ideabank_idea_contest', ['ID' => $id]);
    }

    public static function getList(array $filter = [], array $order = ['ID' => 'DESC']): \Bitrix\Main\ORM\Query\Result
    {
        $allowedOrderFields = ['ID', 'TITLE', 'DEADLINE', 'DATE_LABEL', 'ORGANIZER_USER_ID'];
        $normalizedOrder = [];

        foreach ($order as $field => $direction) {
            $field = mb_strtoupper((string)$field);
            if (!in_array($field, $allowedOrderFields, true)) {
                continue;
            }

            $normalizedOrder[$field] = mb_strtoupper((string)$direction) === 'ASC' ? 'ASC' : 'DESC';
        }

        if ($normalizedOrder === []) {
            $normalizedOrder = ['ID' => 'DESC'];
        }

        return IdeaContestTable::getList([
            'filter' => $filter,
            'order' => $normalizedOrder,
        ]);
    }

    public static function getOne(int $id): ?array
    {
        return IdeaContestTable::getByPrimary($id)->fetch() ?: null;
    }

    public static function addParticipant(int $contestId, int $userId, int $ideaId = 0): bool
    {
        $connection = Application::getConnection();
        return (bool)$connection->insert('b_uds_ideabank_idea_contest_part', [
            'CONTEST_ID' => $contestId,
            'USER_ID' => $userId,
            'IDEA_ID' => $ideaId,
            'CREATED_AT' => date('Y-m-d H:i:s'),
        ]);
    }
}
