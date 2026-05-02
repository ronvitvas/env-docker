<?php
declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Uds\Ideabank2\Domain\PublicDataService;

final class UdsIdeabankIdeaFormComponent extends CBitrixComponent
{
    public function executeComponent(): void
    {
        if (!Loader::includeModule('uds.ideabank2')) {
            ShowError('Модуль uds.ideabank2 не установлен');
            return;
        }

        $query = Application::getInstance()->getContext()->getRequest()->getQueryList()->toArray();
        $domainData = PublicDataService::getIdeaFormData($query);

        $this->arResult = $this->buildPpuFormViewModel($domainData, $query);
        $this->includeComponentTemplate();
    }

    private function buildPpuFormViewModel(array $domainData, array $query): array
    {
        $idea = is_array($domainData['idea'] ?? null) ? $domainData['idea'] : [];
        $categories = is_array($domainData['categories'] ?? null) ? $domainData['categories'] : [];
        $challenges = is_array($domainData['challenges'] ?? null) ? $domainData['challenges'] : [];
        $businessDirections = is_array($domainData['businessDirections'] ?? null) ? $domainData['businessDirections'] : [];
        $actionUrl = (string)($domainData['actionUrl'] ?? '/local/modules/uds.ideabank2/.ajax.php');
        $runtimeMeta = is_array($domainData['meta'] ?? null) ? $domainData['meta'] : PublicDataService::getRuntimeMeta();
        $permissions = is_array($runtimeMeta['permissions'] ?? null) ? $runtimeMeta['permissions'] : [];
        $mode = !empty($idea['ID']) ? 'edit' : 'create';
        $sourceId = (int)($query['source_id'] ?? 0);
        $canSubmit = !empty($permissions['canSubmitIdea']);
        $canUseDrafts = !empty($permissions['canUseDrafts']);

        return [
            'shell' => is_array($domainData['shell'] ?? null) ? $domainData['shell'] : PublicDataService::getShellData('form'),
            'page' => [
                'title' => $mode === 'edit' ? 'Редактирование идеи' : 'Форма идеи',
                'subtitle' => 'Заполните 4 смысловых блока: контекст, эффект, внедрение и проверку перед отправкой.',
                'eyebrow' => 'Новая инициатива',
                'idea' => $idea,
                'mode' => $mode,
                'actionUrl' => $actionUrl,
                'sourceId' => $sourceId,
                'steps' => $canSubmit ? $this->buildSteps($idea, $categories, $businessDirections, $challenges, $canUseDrafts) : [],
                'actions' => [
                    'draft' => $canUseDrafts ? ['title' => 'Сохранить черновик', 'value' => 'Y'] : null,
                    'submit' => $canSubmit ? ['title' => 'Отправить на модерацию', 'value' => 'N'] : null,
                    'cancel' => ['title' => 'Вернуться в реестр', 'url' => '/ideabank/management.php'],
                ],
                'accessDenied' => !$canSubmit ? [
                    'title' => 'Подача идей временно недоступна',
                    'text' => 'Форма отключена настройкой модуля или у текущего пользователя нет права на создание инициатив.',
                ] : null,
            ],
            'widgets' => [
                'guidance' => $this->buildGuidance(),
                'checklist' => $this->buildChecklist(),
                'summary' => $this->buildSummary($idea, $categories),
            ],
            'meta' => [
                'query' => $query,
                'id' => (int)($idea['ID'] ?? $query['id'] ?? 0),
                'sourceId' => $sourceId,
                'isEdit' => $mode === 'edit',
                'hasCategories' => $categories !== [],
            ] + $runtimeMeta,
            // backward compatibility
            'idea' => $idea,
            'statuses' => is_array($domainData['statuses'] ?? null) ? $domainData['statuses'] : [],
            'categories' => $categories,
            'challenges' => $challenges,
            'businessDirections' => $businessDirections,
            'actionUrl' => $actionUrl,
        ];
    }

    private function buildSteps(array $idea, array $categories, array $businessDirections, array $challenges, bool $canUseDrafts): array
    {
        return [
            [
                'number' => 1,
                'code' => 'context',
                'title' => 'Инициатива и проблема',
                'description' => 'Опишите участок, текущее состояние, проблему, потери и предлагаемое решение.',
                'fields' => [
                    ['type' => 'select', 'name' => 'type', 'label' => 'Тип инициативы', 'value' => (string)($idea['TYPE'] ?? 'Идея улучшения'), 'options' => $this->buildTypeOptions()],
                    ['type' => 'select', 'name' => 'category_id', 'label' => 'Категория', 'value' => (int)($idea['CATEGORY_ID'] ?? 0), 'options' => $this->buildCategoryOptions($categories)],
                    ['type' => 'select', 'name' => 'challenge_id', 'label' => 'Тематический челлендж', 'value' => (int)($idea['CHALLENGE_ID'] ?? 0), 'options' => $this->buildChallengeOptions($challenges), 'hint' => 'Если идея подается в мероприятие руководителя БН, выберите его здесь.'],
                    ['type' => 'input', 'name' => 'title', 'label' => 'Название / участок / текущее состояние', 'value' => (string)($idea['TITLE'] ?? ''), 'required' => true, 'hint' => 'Коротко обозначьте место, процесс или проблему, которую хотите улучшить.'],
                    ['type' => 'textarea', 'name' => 'description', 'label' => 'Краткое описание', 'value' => (string)($idea['DESCRIPTION'] ?? ''), 'rows' => 3],
                    ['type' => 'textarea', 'name' => 'problem', 'label' => 'Проблема', 'value' => (string)($idea['PROBLEM'] ?? ''), 'rows' => 4, 'required' => true],
                    ['type' => 'textarea', 'name' => 'losses', 'label' => 'Потери', 'value' => (string)($idea['LOSSES'] ?? ''), 'rows' => 4],
                    ['type' => 'textarea', 'name' => 'proposal', 'label' => 'Предложение', 'value' => (string)($idea['PROPOSAL'] ?? ''), 'rows' => 4, 'required' => true],
                ],
            ],
            [
                'number' => 2,
                'code' => 'effect',
                'title' => 'Эффект',
                'description' => 'Зафиксируйте прогнозируемую пользу, срок реализации и дополнительные эффекты.',
                'fields' => [
                    ['type' => 'number', 'name' => 'economic_effect', 'label' => 'Прогнозируемый экономический эффект, руб.', 'value' => (float)($idea['ECONOMIC_EFFECT'] ?? 0), 'min' => 0, 'step' => 100],
                    ['type' => 'number', 'name' => 'implementation_days', 'label' => 'Срок реализации, дней', 'value' => (int)($idea['IMPLEMENTATION_DAYS'] ?? 0), 'min' => 0, 'step' => 1],
                    ['type' => 'textarea', 'name' => 'economic_effect_text', 'label' => 'Текст оценки эффекта', 'value' => (string)($idea['ECONOMIC_EFFECT_TEXT'] ?? ''), 'rows' => 3],
                    ['type' => 'textarea', 'name' => 'extra_effects', 'label' => 'Дополнительные эффекты', 'value' => (string)($idea['EXTRA_EFFECTS'] ?? ''), 'rows' => 3],
                ],
            ],
            [
                'number' => 3,
                'code' => 'implementation',
                'title' => 'Внедрение',
                'description' => 'Опишите направление, план, ресурсы и риски пилота или тиражирования.',
                'fields' => [
                    ['type' => 'select', 'name' => 'business_direction', 'label' => 'Бизнес-направление', 'value' => (string)($idea['BUSINESS_DIRECTION'] ?? ''), 'options' => $this->buildBusinessDirectionOptions($businessDirections)],
                    ['type' => 'input', 'name' => 'keywords', 'label' => 'Ключевые слова', 'value' => (string)($idea['KEYWORDS'] ?? ''), 'placeholder' => 'безопасность, качество, экономия'],
                    ['type' => 'textarea', 'name' => 'implementation_plan', 'label' => 'План внедрения', 'value' => (string)($idea['IMPLEMENTATION_PLAN'] ?? ''), 'rows' => 4],
                    ['type' => 'textarea', 'name' => 'resources_needed', 'label' => 'Ресурсы', 'value' => (string)($idea['RESOURCES_NEEDED'] ?? ''), 'rows' => 3],
                    ['type' => 'textarea', 'name' => 'risks', 'label' => 'Риски', 'value' => (string)($idea['RISKS'] ?? ''), 'rows' => 3],
                ],
            ],
            [
                'number' => 4,
                'code' => 'review',
                'title' => 'Проверка перед отправкой',
                'description' => 'Добавьте оставшиеся пояснения и выберите режим публичности.',
                'fields' => [
                    ['type' => 'textarea', 'name' => 'additional_work', 'label' => 'Дополнительная работа', 'value' => (string)($idea['ADDITIONAL_WORK'] ?? ''), 'rows' => 3],
                    ['type' => 'file', 'name' => 'attachments', 'label' => 'Файлы к идее', 'multiple' => true, 'items' => is_array($idea['FILES'] ?? null) ? $idea['FILES'] : [], 'hint' => 'Можно приложить схемы, расчеты, презентации или фото. До 20 МБ на файл.'],
                    ['type' => 'checkbox', 'name' => 'is_anonymous', 'label' => 'Подать анонимно в публичном обсуждении', 'value' => 'Y', 'checked' => (string)($idea['IS_ANONYMOUS'] ?? 'N') === 'Y'],
                    ['type' => 'checkbox', 'name' => 'is_hidden', 'label' => 'Скрытая идея: не показывать в публичных списках и витринах', 'value' => 'Y', 'checked' => (string)($idea['IS_HIDDEN'] ?? 'N') === 'Y'],
                    ['type' => 'note', 'name' => 'draft_note', 'label' => $canUseDrafts ? 'Черновики включены: можно сохранить заявку и вернуться к ней позже.' : 'Черновики отключены: заявка будет отправляться сразу на модерацию.'],
                ],
            ],
        ];
    }

    private function buildTypeOptions(): array
    {
        return [
            ['value' => 'Идея', 'label' => 'Идея'],
            ['value' => 'Идея улучшения', 'label' => 'Идея улучшения'],
            ['value' => 'Рационализаторская идея', 'label' => 'Рационализаторская идея'],
        ];
    }

    private function buildCategoryOptions(array $categories): array
    {
        $options = [];
        foreach ($categories as $category) {
            if (is_array($category)) {
                $options[] = ['value' => (int)($category['ID'] ?? 0), 'label' => (string)($category['NAME'] ?? '')];
            }
        }

        return $options;
    }

    private function buildBusinessDirectionOptions(array $directions): array
    {
        $options = [['value' => '', 'label' => 'Не выбрано']];
        foreach ($directions as $direction) {
            $direction = (string)$direction;
            $options[] = ['value' => $direction, 'label' => $direction];
        }

        return $options;
    }

    private function buildChallengeOptions(array $challenges): array
    {
        $options = [['value' => 0, 'label' => 'Без челленджа']];
        foreach ($challenges as $challenge) {
            if (!is_array($challenge)) {
                continue;
            }

            $id = (int)($challenge['ID'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $period = trim((string)($challenge['PERIOD'] ?? ''));
            $label = trim((string)($challenge['TITLE'] ?? ('Челлендж #' . $id)));
            if ($period !== '') {
                $label .= ' · ' . $period;
            }

            $options[] = ['value' => $id, 'label' => $label];
        }

        return $options;
    }

    private function buildGuidance(): array
    {
        return [
            ['title' => 'Контекст', 'text' => 'Пишите конкретно: процесс, участок, текущие потери и кто сталкивается с проблемой.'],
            ['title' => 'Эффект', 'text' => 'Можно указать не только экономию, но и безопасность, качество, сроки или вовлеченность.'],
            ['title' => 'Внедрение', 'text' => 'Опишите первый пилот, ресурсы и риски так, чтобы эксперт мог быстро оценить реализуемость.'],
        ];
    }

    private function buildChecklist(): array
    {
        return [
            'Есть понятное название и участок применения',
            'Описаны проблема, потери и предложение',
            'Указан ожидаемый эффект или качественная польза',
            'Есть план внедрения, ресурсы и риски',
        ];
    }

    private function buildSummary(array $idea, array $categories): array
    {
        $categoryId = (int)($idea['CATEGORY_ID'] ?? 0);
        $categoryName = 'Не выбрана';
        foreach ($categories as $category) {
            if (is_array($category) && (int)($category['ID'] ?? 0) === $categoryId) {
                $categoryName = (string)($category['NAME'] ?? $categoryName);
                break;
            }
        }

        return [
            ['label' => 'Режим', 'value' => !empty($idea['ID']) ? 'Редактирование черновика' : 'Новая идея'],
            ['label' => 'Категория', 'value' => $categoryName],
            ['label' => 'Эффект', 'value' => (float)($idea['ECONOMIC_EFFECT'] ?? 0), 'type' => 'money'],
            ['label' => 'Срок', 'value' => ((int)($idea['IMPLEMENTATION_DAYS'] ?? 0)) . ' дн.'],
        ];
    }
}
