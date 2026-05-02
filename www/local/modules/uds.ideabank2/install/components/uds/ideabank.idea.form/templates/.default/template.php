<?php
defined('B_PROLOG_INCLUDED') || die();
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/ideabank2_public_helpers.php';

$page = is_array($arResult['page'] ?? null) ? $arResult['page'] : [];
$widgets = is_array($arResult['widgets'] ?? null) ? $arResult['widgets'] : [];
$meta = is_array($arResult['meta'] ?? null) ? $arResult['meta'] : [];
$idea = is_array($page['idea'] ?? null) ? $page['idea'] : (is_array($arResult['idea'] ?? null) ? $arResult['idea'] : []);
$steps = is_array($page['steps'] ?? null) ? $page['steps'] : [];
$actionUrl = (string)($page['actionUrl'] ?? $arResult['actionUrl'] ?? '/local/modules/uds.ideabank2/.ajax.php');
$actions = is_array($page['actions'] ?? null) ? $page['actions'] : [];
$accessDenied = is_array($page['accessDenied'] ?? null) ? $page['accessDenied'] : null;
$title = (string)($page['title'] ?? 'Форма идеи');
$subtitle = (string)($page['subtitle'] ?? 'Заполните 4 смысловых блока: контекст, эффект, внедрение и проверку перед отправкой.');
$mode = (string)($page['mode'] ?? (!empty($idea['ID']) ? 'edit' : 'create'));

if ($steps === [] && $accessDenied === null) {
    $steps = [
        ['number' => 1, 'title' => 'Инициатива и проблема', 'fields' => [
            ['type' => 'select', 'name' => 'type', 'label' => 'Тип инициативы', 'value' => (string)($idea['TYPE'] ?? ''), 'options' => [['value' => 'Идея', 'label' => 'Идея'], ['value' => 'Идея улучшения', 'label' => 'Идея улучшения'], ['value' => 'Рационализаторская идея', 'label' => 'Рационализаторская идея']]],
            ['type' => 'select', 'name' => 'category_id', 'label' => 'Категория', 'value' => (int)($idea['CATEGORY_ID'] ?? 0), 'options' => array_map(static fn(array $c): array => ['value' => (int)($c['ID'] ?? 0), 'label' => (string)($c['NAME'] ?? '')], is_array($arResult['categories'] ?? null) ? $arResult['categories'] : [])],
            ['type' => 'input', 'name' => 'title', 'label' => 'Название / участок / текущее состояние', 'value' => (string)($idea['TITLE'] ?? ''), 'required' => true, 'hint' => 'Коротко обозначьте место, процесс или проблему, которую хотите улучшить.'],
            ['type' => 'textarea', 'name' => 'description', 'label' => 'Краткое описание', 'value' => (string)($idea['DESCRIPTION'] ?? ''), 'rows' => 3],
            ['type' => 'textarea', 'name' => 'problem', 'label' => 'Проблема', 'value' => (string)($idea['PROBLEM'] ?? ''), 'rows' => 4, 'required' => true],
            ['type' => 'textarea', 'name' => 'losses', 'label' => 'Потери', 'value' => (string)($idea['LOSSES'] ?? ''), 'rows' => 4],
            ['type' => 'textarea', 'name' => 'proposal', 'label' => 'Предложение', 'value' => (string)($idea['PROPOSAL'] ?? ''), 'rows' => 4, 'required' => true],
        ]],
        ['number' => 2, 'title' => 'Эффект', 'fields' => [
            ['type' => 'number', 'name' => 'economic_effect', 'label' => 'Прогнозируемый экономический эффект, руб.', 'value' => (float)($idea['ECONOMIC_EFFECT'] ?? 0), 'min' => 0, 'step' => 100],
            ['type' => 'number', 'name' => 'implementation_days', 'label' => 'Срок реализации, дней', 'value' => (int)($idea['IMPLEMENTATION_DAYS'] ?? 0), 'min' => 0, 'step' => 1],
            ['type' => 'textarea', 'name' => 'economic_effect_text', 'label' => 'Текст оценки эффекта', 'value' => (string)($idea['ECONOMIC_EFFECT_TEXT'] ?? ''), 'rows' => 3],
            ['type' => 'textarea', 'name' => 'extra_effects', 'label' => 'Дополнительные эффекты', 'value' => (string)($idea['EXTRA_EFFECTS'] ?? ''), 'rows' => 3],
        ]],
        ['number' => 3, 'title' => 'Внедрение', 'fields' => [
            ['type' => 'select', 'name' => 'business_direction', 'label' => 'Бизнес-направление', 'value' => (string)($idea['BUSINESS_DIRECTION'] ?? ''), 'options' => array_merge([['value' => '', 'label' => 'Не выбрано']], array_map(static fn($d): array => ['value' => (string)$d, 'label' => (string)$d], is_array($arResult['businessDirections'] ?? null) ? $arResult['businessDirections'] : []))],
            ['type' => 'input', 'name' => 'keywords', 'label' => 'Ключевые слова', 'value' => (string)($idea['KEYWORDS'] ?? ''), 'placeholder' => 'безопасность, качество, экономия'],
            ['type' => 'textarea', 'name' => 'implementation_plan', 'label' => 'План внедрения', 'value' => (string)($idea['IMPLEMENTATION_PLAN'] ?? ''), 'rows' => 4],
            ['type' => 'textarea', 'name' => 'resources_needed', 'label' => 'Ресурсы', 'value' => (string)($idea['RESOURCES_NEEDED'] ?? ''), 'rows' => 3],
            ['type' => 'textarea', 'name' => 'risks', 'label' => 'Риски', 'value' => (string)($idea['RISKS'] ?? ''), 'rows' => 3],
        ]],
        ['number' => 4, 'title' => 'Проверка перед отправкой', 'fields' => [
            ['type' => 'textarea', 'name' => 'additional_work', 'label' => 'Дополнительная работа', 'value' => (string)($idea['ADDITIONAL_WORK'] ?? ''), 'rows' => 3],
            ['type' => 'file', 'name' => 'attachments', 'label' => 'Файлы к идее', 'multiple' => true, 'items' => is_array($idea['FILES'] ?? null) ? $idea['FILES'] : [], 'hint' => 'Можно приложить схемы, расчеты, презентации или фото. До 20 МБ на файл.'],
            ['type' => 'checkbox', 'name' => 'is_anonymous', 'label' => 'Подать анонимно в публичном обсуждении', 'value' => 'Y', 'checked' => (string)($idea['IS_ANONYMOUS'] ?? 'N') === 'Y'],
            ['type' => 'checkbox', 'name' => 'is_hidden', 'label' => 'Скрытая идея: не показывать в публичных списках и витринах', 'value' => 'Y', 'checked' => (string)($idea['IS_HIDDEN'] ?? 'N') === 'Y'],
        ]],
    ];
}

$renderField = static function (array $field): void {
    $type = (string)($field['type'] ?? 'input');
    $name = (string)($field['name'] ?? '');
    $label = (string)($field['label'] ?? $name);
    $value = $field['value'] ?? '';
    $required = !empty($field['required']);
    $hint = (string)($field['hint'] ?? '');
    $placeholder = (string)($field['placeholder'] ?? '');

    if ($type === 'note') {
        ?>
        <div class="empty-state empty-state--compact"><?= udsIbE($label) ?></div>
        <?php
        return;
    }

    if ($type === 'checkbox') {
        ?>
        <label class="uds-ib-field uds-ib-field--checkbox">
            <input type="hidden" name="<?= udsIbE($name) ?>" value="N">
            <span><input type="checkbox" name="<?= udsIbE($name) ?>" value="<?= udsIbE($field['value'] ?? 'Y') ?>" <?= !empty($field['checked']) ? 'checked' : '' ?>> <?= udsIbE($label) ?></span>
        </label>
        <?php
        return;
    }

    if ($type === 'file') {
        $items = is_array($field['items'] ?? null) ? $field['items'] : [];
        ?>
        <label class="uds-ib-field uds-ib-field--file">
            <span><?= udsIbE($label) ?></span>
            <input type="file" name="<?= udsIbE($name) ?><?= !empty($field['multiple']) ? '[]' : '' ?>" <?= !empty($field['multiple']) ? 'multiple' : '' ?> accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.webp,.txt,.csv,.zip,.rar">
            <?php if ($hint !== ''): ?><small class="field__hint"><?= udsIbE($hint) ?></small><?php endif; ?>
            <?php if ($items !== []): ?>
                <div class="idea-files idea-files--compact">
                    <?php foreach ($items as $item): ?>
                        <a class="idea-file" href="<?= udsIbE((string)($item['URL'] ?? '#')) ?>" target="_blank" rel="noopener">
                            <span class="idea-file__icon">Файл</span>
                            <span class="idea-file__meta">
                                <strong><?= udsIbE((string)($item['NAME'] ?? 'Файл')) ?></strong>
                                <small><?= udsIbE((string)($item['SIZE_FORMATTED'] ?? '')) ?></small>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </label>
        <?php
        return;
    }

    ?>
    <label class="uds-ib-field">
        <span><?= udsIbE($label) ?><?= $required ? ' *' : '' ?></span>
        <?php if ($type === 'select'): ?>
            <select name="<?= udsIbE($name) ?>" <?= $required ? 'required' : '' ?>>
                <?php foreach ((array)($field['options'] ?? []) as $option): ?>
                    <?php
                    $optionValue = (string)($option['value'] ?? '');
                    $selected = (string)$value === $optionValue;
                    ?>
                    <option value="<?= udsIbE($optionValue) ?>" <?= $selected ? 'selected' : '' ?>><?= udsIbE($option['label'] ?? $optionValue) ?></option>
                <?php endforeach; ?>
            </select>
        <?php elseif ($type === 'textarea'): ?>
            <textarea name="<?= udsIbE($name) ?>" rows="<?= (int)($field['rows'] ?? 3) ?>" <?= $required ? 'required' : '' ?> placeholder="<?= udsIbE($placeholder) ?>"><?= udsIbE($value) ?></textarea>
        <?php else: ?>
            <input type="<?= $type === 'number' ? 'number' : 'text' ?>" name="<?= udsIbE($name) ?>" value="<?= udsIbE($value) ?>" <?= $required ? 'required' : '' ?><?= isset($field['min']) ? ' min="' . udsIbE($field['min']) . '"' : '' ?><?= isset($field['step']) ? ' step="' . udsIbE($field['step']) . '"' : '' ?> placeholder="<?= udsIbE($placeholder) ?>">
        <?php endif; ?>
        <?php if ($hint !== ''): ?><small class="field__hint"><?= udsIbE($hint) ?></small><?php endif; ?>
    </label>
    <?php
};

udsIbShellStart(is_array($arResult['shell'] ?? null) ? $arResult['shell'] : [], $title, $subtitle);
?>
<div class="uds-ib-layout-grid">
    <?php if ($accessDenied !== null): ?>
        <section class="panel empty-state">
            <h3><?= udsIbText($accessDenied['title'] ?? 'Форма недоступна') ?></h3>
            <p><?= udsIbText($accessDenied['text'] ?? 'Подача идей временно отключена.') ?></p>
            <a class="primary-button" href="/ideabank/management.php">Вернуться в реестр</a>
        </section>
    <?php else: ?>
    <form class="panel uds-ib-form" method="post" action="<?= udsIbE($actionUrl) ?>" enctype="multipart/form-data">
        <?= bitrix_sessid_post() ?>
        <input type="hidden" name="action" value="<?= $mode === 'edit' ? 'update' : 'create' ?>">
        <?php if (!empty($idea['ID'])): ?><input type="hidden" name="id" value="<?= (int)$idea['ID'] ?>"><?php endif; ?>
        <?php if (!empty($meta['sourceId'])): ?><input type="hidden" name="source_id" value="<?= (int)$meta['sourceId'] ?>"><?php endif; ?>

        <?php foreach ($steps as $step): ?>
            <?php $fields = is_array($step['fields'] ?? null) ? $step['fields'] : []; ?>
            <section class="form-section" data-step="<?= udsIbE($step['code'] ?? $step['number'] ?? '') ?>">
                <div class="panel__header">
                    <div>
                        <div class="coin-panel__eyebrow">Шаг <?= (int)($step['number'] ?? 0) ?></div>
                        <div class="panel__title"><?= udsIbE($step['title'] ?? '') ?></div>
                        <?php if (!empty($step['description'])): ?><p class="page-subtitle"><?= udsIbE($step['description']) ?></p><?php endif; ?>
                    </div>
                </div>
                <?php for ($i = 0, $count = count($fields); $i < $count; $i++): ?>
                    <?php if ($i === 0 && isset($fields[1]) && ($fields[0]['type'] ?? '') !== 'textarea' && ($fields[1]['type'] ?? '') !== 'textarea'): ?>
                        <div class="form-grid">
                            <?php $renderField($fields[$i]); $renderField($fields[$i + 1]); $i++; ?>
                        </div>
                    <?php else: ?>
                        <?php $renderField($fields[$i]); ?>
                    <?php endif; ?>
                <?php endfor; ?>
            </section>
        <?php endforeach; ?>

        <div class="uds-ib-actions">
            <a class="outline-button" href="<?= udsIbE($actions['cancel']['url'] ?? '/ideabank/management.php') ?>"><?= udsIbE($actions['cancel']['title'] ?? 'Вернуться в реестр') ?></a>
            <?php if (is_array($actions['draft'] ?? null)): ?>
                <button class="outline-button" type="submit" name="is_draft" value="<?= udsIbE($actions['draft']['value'] ?? 'Y') ?>"><?= udsIbE($actions['draft']['title'] ?? 'Сохранить черновик') ?></button>
            <?php endif; ?>
            <?php if (is_array($actions['submit'] ?? null)): ?>
                <button class="primary-button" type="submit" name="is_draft" value="<?= udsIbE($actions['submit']['value'] ?? 'N') ?>"><?= udsIbE($actions['submit']['title'] ?? 'Отправить на модерацию') ?></button>
            <?php endif; ?>
        </div>
    </form>
    <?php endif; ?>

    <aside class="uds-ib-sidebar-stack">
        <?php if (!empty($widgets['summary'])): ?>
            <section class="panel">
                <div class="panel__header"><div><div class="coin-panel__eyebrow">Сводка</div><div class="panel__title">Параметры заявки</div></div></div>
                <div class="metric-grid metric-grid--compact">
                    <?php foreach ((array)$widgets['summary'] as $item): ?>
                        <div class="metric-card">
                            <span><?= udsIbE($item['label'] ?? '') ?></span>
                            <strong><?= (($item['type'] ?? '') === 'money') ? udsIbMoney($item['value'] ?? 0) : udsIbE($item['value'] ?? '') ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!empty($widgets['checklist'])): ?>
            <section class="panel">
                <div class="panel__header"><div><div class="coin-panel__eyebrow">Перед отправкой</div><div class="panel__title">Проверьте заявку</div></div></div>
                <ul class="uds-ib-check-list">
                    <?php foreach ((array)$widgets['checklist'] as $item): ?><li><?= udsIbE($item) ?></li><?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if (!empty($widgets['guidance'])): ?>
            <section class="panel">
                <div class="panel__header"><div><div class="coin-panel__eyebrow">Подсказки</div><div class="panel__title">Как заполнить сильную идею</div></div></div>
                <div class="uds-ib-list-stack">
                    <?php foreach ((array)$widgets['guidance'] as $item): ?>
                        <article class="mini-card"><strong><?= udsIbE($item['title'] ?? '') ?></strong><span><?= udsIbE($item['text'] ?? '') ?></span></article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </aside>
</div>
<?php udsIbShellEnd(); ?>
