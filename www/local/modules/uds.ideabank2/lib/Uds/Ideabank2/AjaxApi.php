<?php

declare(strict_types=1);

namespace Uds\Ideabank2;

use Bitrix\Main\AjaxResult;
use Bitrix\Main\AjaxError;
use Bitrix\Main\AccessDeniedException;
use Bitrix\Main\Request;
use Bitrix\Main\SystemException;
use Uds\Ideabank2\Domain\ChallengeService;
use Uds\Ideabank2\Domain\IdeaService;
use Uds\Ideabank2\Domain\CoinService;
use Uds\Ideabank2\Domain\PublicDataService;

class AjaxApi
{
    public const PERMISSION_WRITE_ACTIONS = [
        'Create', 'Update', 'Delete', 'Submit', 'Reaction', 'Comment',
    ];

    public const PERMISSION_MANAGE_ACTIONS = [
        'Moderate', 'ChangeStatus',
    ];

    public const PERMISSION_READ_ACTIONS = [
        'List', 'Get', 'CoinBalance', 'CoinHistory',
        'CoinLeaderboard', 'Stats',
    ];

    /** @var Request */
    protected Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Главный роутер AJAX-запросов
     */
    public function handle(): void
    {
        try {
            $action = $this->request->getPost('action');
            if (!$action) {
                $this->jsonResponse(['success' => false, 'error' => 'Action is required']);
                return;
            }

            $method = 'action' . ucfirst(strtolower(str_replace('_', '', $action)));
            if (!method_exists($this, $method)) {
                $this->jsonResponse(['success' => false, 'error' => 'Unknown action']);
                return;
            }

            $this->checkPermission($method);

            /** @var array $result */
            $result = $this->$method();
            $this->jsonResponse($result);
        } catch (AccessDeniedException $e) {
            $this->jsonResponse(['success' => false, 'error' => 'Access denied']);
        } catch (SystemException $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage() ?: 'Server error']);
        } catch (\Throwable $e) {
            $this->jsonResponse(['success' => false, 'error' => 'Unexpected error']);
        }
    }

    /**
     * Check user permission for the given action method
     */
    protected function checkPermission(string $method): void
    {
        $actionName = substr($method, 6); // strip 'action' prefix
        $permissions = PublicDataService::getCurrentUserPermissions();

        if (in_array($actionName, self::PERMISSION_MANAGE_ACTIONS, true)) {
            if (empty($permissions['canViewModeration']) && empty($permissions['canAdminIdeabank'])) {
                throw new AccessDeniedException();
            }
            return;
        }

        if (in_array($actionName, self::PERMISSION_WRITE_ACTIONS, true)) {
            if ((int)($permissions['userId'] ?? 0) <= 0) {
                throw new AccessDeniedException();
            }
        } elseif (in_array($actionName, self::PERMISSION_READ_ACTIONS, true)) {
            global $APPLICATION;
            $userRight = (string)$APPLICATION->GetUserRight('uds.ideabank2');
            if ($userRight < 'R') {
                throw new AccessDeniedException();
            }
        }
    }

    private function canManageIdeas(): bool
    {
        $permissions = PublicDataService::getCurrentUserPermissions();

        return !empty($permissions['canViewModeration']) || !empty($permissions['canAdminIdeabank']);
    }

    private function isCurrentUserOwner(array $idea): bool
    {
        $permissions = PublicDataService::getCurrentUserPermissions();

        return (int)($idea['OWNER_USER_ID'] ?? 0) > 0
            && (int)($idea['OWNER_USER_ID'] ?? 0) === (int)($permissions['userId'] ?? 0);
    }

    // ------------------- Ideas -------------------

    public function actionList(): array
    {
        $filter = $this->request->getPost('filter', []);
        $orderBy = $this->request->getPost('order_by', []);
        $limit = $this->request->getPost('limit', []);

        $items = IdeaService::list($filter, $orderBy, $limit);

        return [
            'success' => true,
            'items' => $items,
            'total' => count($items),
        ];
    }

    public function actionGet(): array
    {
        $id = (int)$this->request->getPost('id');
        $idea = IdeaService::getById($id);

        if (!$idea) {
            return ['success' => false, 'error' => 'Идея не найдена'];
        }

        if ((string)($idea['IS_HIDDEN'] ?? 'N') === 'Y') {
            $permissions = PublicDataService::getCurrentUserPermissions();
            $canViewHidden = (int)($idea['OWNER_USER_ID'] ?? 0) === (int)($permissions['userId'] ?? 0)
                || !empty($permissions['canViewModeration'])
                || !empty($permissions['canAdminIdeabank']);
            if (!$canViewHidden) {
                return ['success' => false, 'error' => 'Идея не найдена'];
            }
        }

        $idea['reactions'] = IdeaService::getReactionCount($id);
        $idea['comments'] = IdeaService::getComments($id);

        return [
            'success' => true,
            'idea' => $idea,
        ];
    }

    public function actionCreate(): array
    {
        $permissions = PublicDataService::getCurrentUserPermissions();
        if (empty($permissions['canSubmitIdea'])) {
            return ['success' => false, 'error' => 'Войдите в портал, чтобы подать идею'];
        }

        $title = trim($this->request->getPost('title', ''));
        if (!$title) {
            return ['success' => false, 'error' => 'Укажите название идеи'];
        }

        $data = [
            'TITLE' => $title,
            'DESCRIPTION' => $this->request->getPost('description', ''),
            'TYPE' => $this->request->getPost('type', 'Идея улучшения'),
            'CATEGORY_ID' => (int)$this->request->getPost('category_id', 0) ?: null,
            'PROBLEM' => $this->request->getPost('problem', ''),
            'LOSSES' => $this->request->getPost('losses', ''),
            'PROPOSAL' => $this->request->getPost('proposal', ''),
            'ECONOMIC_EFFECT' => (float)$this->request->getPost('economic_effect', 0),
            'IMPLEMENTATION_DAYS' => (int)$this->request->getPost('implementation_days', 0),
            'ECONOMIC_EFFECT_TEXT' => $this->request->getPost('economic_effect_text', ''),
            'EXTRA_EFFECTS' => $this->request->getPost('extra_effects', ''),
            'BUSINESS_DIRECTION' => $this->request->getPost('business_direction', ''),
            'KEYWORDS' => $this->request->getPost('keywords', ''),
            'IMPLEMENTATION_PLAN' => $this->request->getPost('implementation_plan', ''),
            'RESOURCES_NEEDED' => $this->request->getPost('resources_needed', ''),
            'RISKS' => $this->request->getPost('risks', ''),
            'ADDITIONAL_WORK' => $this->request->getPost('additional_work', ''),
            'IS_ANONYMOUS' => $this->request->getPost('is_anonymous', 'N') === 'Y' ? 'Y' : 'N',
            'IS_HIDDEN' => $this->request->getPost('is_hidden', 'N') === 'Y' ? 'Y' : 'N',
            'IS_DRAFT' => $this->request->getPost('is_draft', 'Y') === 'Y' ? 'Y' : 'N',
        ];

        $challengeId = (int)$this->request->getPost('challenge_id', 0);
        $ideaId = IdeaService::create($data);
        if (!$ideaId) {
            return ['success' => false, 'error' => 'Ошибка создания идеи'];
        }

        if ($challengeId > 0) {
            ChallengeService::setIdeaChallenge((int)$ideaId, $challengeId);
        }
        $uploadedFiles = IdeaService::saveUploadedFiles((int)$ideaId, IdeaService::normalizeUploadedFiles(), (int)($permissions['userId'] ?? 0));

        return [
            'success' => true,
            'idea_id' => $ideaId,
            'files' => $uploadedFiles,
        ];
    }

    public function actionUpdate(): array
    {
        $id = (int)$this->request->getPost('id');
        $idea = IdeaService::getById($id);

        if (!$idea) {
            return ['success' => false, 'error' => 'Идея не найдена'];
        }

        if (!$this->isCurrentUserOwner($idea) && !$this->canManageIdeas()) {
            return ['success' => false, 'error' => 'Недостаточно прав для изменения идеи'];
        }

        $data = [];
        $fields = [
            'title', 'description', 'problem', 'losses', 'proposal', 'type', 'category_id',
            'economic_effect', 'implementation_days', 'economic_effect_text', 'extra_effects',
            'business_direction', 'keywords', 'implementation_plan', 'resources_needed', 'risks',
            'additional_work', 'is_anonymous', 'is_hidden',
        ];
        foreach ($fields as $field) {
            $key = strtoupper($field);
            $value = $this->request->getPost($field, '');
            if (in_array($field, ['is_anonymous', 'is_hidden'], true)) {
                $data[$key] = $value === 'Y' ? 'Y' : 'N';
            } elseif ($value !== '') {
                $data[$key] = in_array($field, ['category_id', 'implementation_days'], true)
                    ? (int)$value
                    : ($field === 'economic_effect' ? (float)$value : $value);
            }
        }

        $hasChallengeUpdate = $this->request->getPost('challenge_id', null) !== null;
        $uploadedFiles = IdeaService::normalizeUploadedFiles();
        if (empty($data) && !$hasChallengeUpdate && $uploadedFiles === []) {
            return ['success' => false, 'error' => 'Нет данных для обновления'];
        }

        $ok = empty($data) ? true : IdeaService::update($id, $data);
        if ($ok && $hasChallengeUpdate) {
            $ok = ChallengeService::setIdeaChallenge($id, (int)$this->request->getPost('challenge_id', 0));
        }
        $savedFiles = $ok ? IdeaService::saveUploadedFiles($id, $uploadedFiles, (int)(PublicDataService::getCurrentUserPermissions()['userId'] ?? 0)) : [];

        return [
            'success' => (bool)$ok,
            'error' => $ok ? null : 'Ошибка обновления',
            'files' => $savedFiles,
        ];
    }

    public function actionSubmit(): array
    {
        $id = (int)$this->request->getPost('id');
        $idea = IdeaService::getById($id);
        if (!$idea) {
            return ['success' => false, 'error' => 'Идея не найдена'];
        }
        if (!$this->isCurrentUserOwner($idea) && !$this->canManageIdeas()) {
            return ['success' => false, 'error' => 'Недостаточно прав для отправки идеи'];
        }

        return IdeaService::submit($id);
    }

    public function actionDelete(): array
    {
        $id = (int)$this->request->getPost('id');
        $idea = IdeaService::getById($id);
        if (!$idea) {
            return ['success' => false, 'error' => 'Идея не найдена'];
        }
        if (!$this->isCurrentUserOwner($idea) && !$this->canManageIdeas()) {
            return ['success' => false, 'error' => 'Недостаточно прав для удаления идеи'];
        }
        $ok = IdeaService::delete($id);

        return [
            'success' => (bool)$ok,
            'error' => $ok ? null : 'Ошибка удаления',
        ];
    }

    // ------------------- Moderation -------------------

    public function actionModerate(): array
    {
        $id = (int)$this->request->getPost('id');
        $decision = $this->request->getPost('decision', '');
        $feedback = $this->request->getPost('feedback', '');

        if (!in_array($decision, ['publish', 'revise', 'reject'], true)) {
            return ['success' => false, 'error' => 'Неверное решение модерации'];
        }

        return IdeaService::moderate($id, $decision, $feedback);
    }

    public function actionChangeStatus(): array
    {
        $id = (int)$this->request->getPost('id');
        $statusId = (int)$this->request->getPost('status_id');
        $comment = $this->request->getPost('comment', '');

        return IdeaService::changeStatus($id, $statusId, $comment);
    }

    // ------------------- Reactions -------------------

    public function actionReaction(): array
    {
        $id = (int)$this->request->getPost('id');
        $type = $this->request->getPost('type', 'like');

        return IdeaService::addReaction($id, $type);
    }

    // ------------------- Comments -------------------

    public function actionComment(): array
    {
        $id = (int)$this->request->getPost('id');
        $text = trim($this->request->getPost('text', ''));

        if (!$text) {
            return ['success' => false, 'error' => 'Комментарий пуст'];
        }

        return IdeaService::addComment($id, $text);
    }

    // ------------------- Coins -------------------

    public function actionCoinBalance(): array
    {
        $userId = (int)$this->request->getPost('user_id', 0);
        if (!$userId) {
            $userId = IdeaService::currentUser();
        }

        $balance = CoinService::getBalance($userId);

        return [
            'success' => true,
            'balance' => $balance,
        ];
    }

    public function actionCoinHistory(): array
    {
        $userId = (int)$this->request->getPost('user_id', 0);
        if (!$userId) {
            $userId = IdeaService::currentUser();
        }

        $history = CoinService::getHistory($userId);

        return [
            'success' => true,
            'history' => $history,
        ];
    }

    public function actionCoinLeaderboard(): array
    {
        $limit = (int)$this->request->getPost('limit', 10);

        $leaderboard = CoinService::getLeaderboard($limit);

        return [
            'success' => true,
            'leaderboard' => $leaderboard,
        ];
    }

    // ------------------- Stats -------------------

    public function actionStats(): array
    {
        $stats = IdeaService::getStats();

        return [
            'success' => true,
            'stats' => $stats,
        ];
    }

    // ------------------- Helpers -------------------

    protected function jsonResponse(array $data): void
    {
        // Sanitize: ensure no nested objects leak sensitive data
        $safe = $this->sanitizeForJson($data);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            $safe,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
        die;
    }

    /**
     * Recursively sanitize array for safe JSON output
     */
    protected function sanitizeForJson(mixed $data): mixed
    {
        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                $result[$key] = $this->sanitizeForJson($value);
            }
            return $result;
        }

        if (is_object($data)) {
            // Drop objects that cannot be safely serialized
            return null;
        }

        if (is_string($data)) {
            // Ensure valid UTF-8
            return mb_check_encoding($data, 'UTF-8') ? $data : mb_convert_encoding($data, 'UTF-8', 'UTF-8');
        }

        return $data;
    }
}
