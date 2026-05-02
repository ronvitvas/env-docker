<?php
defined('B_PROLOG_INCLUDED') || die();
require_once $_SERVER['DOCUMENT_ROOT'] . '/local/php_interface/include/ideabank2_public_helpers.php';

$page = is_array($arResult['page'] ?? null) ? $arResult['page'] : [];
$widgets = is_array($arResult['widgets'] ?? null) ? $arResult['widgets'] : [];
$meta = is_array($arResult['meta'] ?? null) ? $arResult['meta'] : [];
$idea = is_array($page['idea'] ?? null) ? $page['idea'] : (is_array($arResult['idea'] ?? null) ? $arResult['idea'] : null);
$title = (string)($page['title'] ?? 'Карточка идеи');
$subtitle = (string)($page['subtitle'] ?? ($idea ? (($idea['CODE'] ?? ('#' . (int)$idea['ID'])) . ' · ' . ($idea['STATUS']['NAME'] ?? '')) : 'Идея не найдена'));
$sections = is_array($page['sections'] ?? null) ? $page['sections'] : [];
$actions = is_array($page['actions'] ?? null) ? $page['actions'] : [];
$metaCards = is_array($widgets['metaCards'] ?? null) ? $widgets['metaCards'] : [];
$discussion = is_array($widgets['discussion'] ?? null) ? $widgets['discussion'] : [
    'reactions' => is_array($arResult['reactions'] ?? null) ? $arResult['reactions'] : [],
    'comments' => is_array($arResult['comments'] ?? null) ? $arResult['comments'] : [],
    'empty' => 'Комментариев пока нет.',
];
$related = is_array($widgets['related'] ?? null) ? $widgets['related'] : [];
$files = is_array($widgets['files'] ?? null) ? $widgets['files'] : (is_array($idea['FILES'] ?? null) ? $idea['FILES'] : []);
$workflow = is_array($widgets['workflow'] ?? null) ? $widgets['workflow'] : ['items' => is_array($arResult['workflow'] ?? null) ? $arResult['workflow'] : []];
$authors = is_array($widgets['authors'] ?? null) ? $widgets['authors'] : (is_array($idea['AUTHORS'] ?? null) ? $idea['AUTHORS'] : []);
$history = is_array($widgets['history'] ?? null) ? $widgets['history'] : [];
$feedback = is_array($widgets['feedback'] ?? null) ? $widgets['feedback'] : (is_array($arResult['feedback'] ?? null) ? $arResult['feedback'] : []);
$expertReviews = is_array($widgets['expertReviews'] ?? null) ? $widgets['expertReviews'] : (is_array($arResult['expertReviews'] ?? null) ? $arResult['expertReviews'] : []);
$committeeDecision = is_array($widgets['committeeDecision'] ?? null) ? $widgets['committeeDecision'] : (is_array($arResult['committeeDecision'] ?? null) ? $arResult['committeeDecision'] : null);

$renderValue = static function (array $row): string {
    $value = $row['value'] ?? '';
    if (($row['type'] ?? '') === 'money') {
        return udsIbMoney($value);
    }
    if (($row['type'] ?? '') === 'date') {
        return udsIbDate($value);
    }

    return udsIbText((string)$value);
};

udsIbShellStart($arResult['shell'] ?? [], $title, $subtitle);
?>
<?php if (!$idea): ?>
    <?php $empty = is_array($page['empty'] ?? null) ? $page['empty'] : []; ?>
    <section class="page-section">
        <div class="panel empty-state">
            <h3><?= udsIbText($empty['title'] ?? 'Идея не найдена') ?></h3>
            <p><?= udsIbText($empty['text'] ?? 'Вернитесь в реестр и выберите существующую запись.') ?></p>
            <?php if (!empty($empty['action']['url'])): ?>
                <a class="primary-button" href="<?= htmlspecialcharsbx((string)$empty['action']['url']) ?>"><?= udsIbText($empty['action']['title'] ?? 'Открыть реестр') ?></a>
            <?php endif; ?>
        </div>
    </section>
<?php else: ?>
    <section class="page-section">
        <a class="back-link" href="/ideabank/management.php">Вернуться в реестр идей</a>
        <div class="detail-layout">
            <article class="panel detail-article detail-article--ppu">
                <div class="detail-kicker">
                    <span class="status<?= udsIbStatusClass($idea['STATUS']['CODE'] ?? $idea['STAGE'] ?? '') ?>"><?= udsIbText($idea['STATUS']['NAME'] ?? $idea['ROUTE_LABEL'] ?? '') ?></span>
                    <span class="detail-kicker__code"><?= udsIbText($idea['CODE'] ?? ('#' . (int)$idea['ID'])) ?></span>
                </div>
                <h2 class="detail-article__title"><?= udsIbText($idea['TITLE'] ?? '') ?></h2>

                <?php if ($metaCards): ?>
                    <div class="detail-meta-grid">
                        <?php foreach ($metaCards as $card): ?>
                            <div class="detail-meta-card">
                                <span><?= udsIbText($card['label'] ?? '') ?></span>
                                <strong><?= ($card['type'] ?? '') === 'money' ? udsIbMoney($card['value'] ?? 0) : udsIbText($card['value'] ?? '') ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php foreach ($sections as $section): ?>
                    <section class="detail-section">
                        <div class="panel__title"><?= udsIbText($section['title'] ?? '') ?></div>
                        <?php foreach ((array)($section['rows'] ?? []) as $row): ?>
                            <div class="summary-row">
                                <div class="summary-row__label"><?= udsIbText($row['label'] ?? '') ?></div>
                                <div class="summary-row__value"><?= $renderValue(is_array($row) ? $row : []) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php endforeach; ?>

                <?php if ($files !== []): ?>
                    <section class="detail-section detail-section--files">
                        <div class="panel__title">Файлы к идее</div>
                        <div class="idea-files">
                            <?php foreach ($files as $file): ?>
                                <?php if (empty($file['URL'])) { continue; } ?>
                                <a class="idea-file" href="<?= udsIbE((string)$file['URL']) ?>" target="_blank" rel="noopener">
                                    <span class="idea-file__icon">Файл</span>
                                    <span class="idea-file__meta">
                                        <strong><?= udsIbE((string)($file['NAME'] ?? 'Файл')) ?></strong>
                                        <small><?= udsIbE((string)($file['SIZE_FORMATTED'] ?? '')) ?></small>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (!empty($discussion['enabled'])): ?>
                    <section class="detail-section detail-section--discussion">
                        <div class="panel__title">Обсуждение идеи</div>
                        <?php if (!empty($discussion['reactionsEnabled'])): ?>
                            <p class="uds-ib-muted">👍 <?= (int)($discussion['reactions']['like'] ?? 0) ?> · 👎 <?= (int)($discussion['reactions']['dislike'] ?? 0) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($discussion['commentsEnabled'])): ?>
                            <?php foreach ((array)($discussion['comments'] ?? []) as $comment): ?>
                                <div class="uds-ib-list-item">
                                    <div>
                                        <strong><?= udsIbText($comment['USER_LABEL'] ?? 'Участник') ?></strong><br>
                                        <?= udsIbText($comment['TEXT_VALUE'] ?? $comment['TEXT'] ?? '') ?><br>
                                        <span class="uds-ib-muted"><?= udsIbDate($comment['CREATED_AT'] ?? null) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($discussion['comments'])): ?><div class="empty-state empty-state--compact"><?= udsIbText($discussion['empty'] ?? 'Комментариев пока нет.') ?></div><?php endif; ?>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <section class="detail-section detail-section--supporting">
                    <div class="panel__title">Связанные данные</div>
                    <?php foreach ($related as $row): ?>
                        <div class="summary-row">
                            <div class="summary-row__label"><?= udsIbText($row['label'] ?? '') ?></div>
                            <div class="summary-row__value">
                                <?php if (!empty($row['statusCode'])): ?>
                                    <span class="status<?= udsIbStatusClass($row['statusCode']) ?>"><?= udsIbText($row['value'] ?? '') ?></span>
                                <?php else: ?>
                                    <?= $renderValue(is_array($row) ? $row : []) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </section>
            </article>

            <aside class="panel detail-side">
                <div class="detail-side__section">
                    <div class="panel__title">Действия</div>
                    <div class="detail-side__actions">
                        <?php foreach ($actions as $action): ?>
                            <a class="<?= !empty($action['primary']) ? 'primary-button' : 'outline-button' ?>" href="<?= htmlspecialcharsbx((string)($action['url'] ?? '#')) ?>"><?= udsIbText($action['title'] ?? '') ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if ($committeeDecision): ?>
                    <div class="detail-side__section">
                        <div class="panel__title">Решение инновационного комитета</div>
                        <div class="summary-row"><div class="summary-row__label">Решение</div><div class="summary-row__value"><?= udsIbText($committeeDecision['DECISION'] ?? $committeeDecision['TEXT_VALUE'] ?? '') ?></div></div>
                        <div class="summary-row"><div class="summary-row__label">Комментарий</div><div class="summary-row__value"><?= udsIbText($committeeDecision['TEXT_VALUE'] ?? '') ?></div></div>
                        <div class="uds-ib-muted"><?= udsIbDate($committeeDecision['DECIDED_AT'] ?? $committeeDecision['CREATED_AT'] ?? null) ?> · <?= udsIbText($committeeDecision['USER_LABEL'] ?? 'Комитет') ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($expertReviews): ?>
                    <div class="detail-side__section">
                        <div class="panel__title">Экспертная оценка</div>
                        <?php foreach ($expertReviews as $review): ?>
                            <div class="uds-ib-list-item"><div><?= udsIbText($review['TEXT_VALUE'] ?? '') ?><br><span class="uds-ib-muted"><?= udsIbText($review['USER_LABEL'] ?? 'Эксперт') ?> · <?= udsIbDate($review['CREATED_AT'] ?? null) ?></span></div></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="detail-side__section">
                    <div class="panel__title"><?= udsIbText($workflow['title'] ?? 'Маршрут идеи') ?></div>
                    <div class="workflow-summary">
                        <div class="workflow-summary__row"><span>Текущий этап</span><strong><?= udsIbText($workflow['current'] ?? 'Маршрут не задан') ?></strong></div>
                        <div class="workflow-summary__row"><span>Ответственный</span><strong><?= udsIbText($workflow['assignee'] ?? 'Не назначен') ?></strong></div>
                        <div class="workflow-summary__row"><span>Целевая дата</span><strong><?= udsIbDate($workflow['targetDate'] ?? null) ?: '—' ?></strong></div>
                        <div class="workflow-progress"><div class="workflow-progress__bar"><span style="width:<?= (int)($workflow['progress'] ?? 0) ?>%"></span></div><strong><?= (int)($workflow['progress'] ?? 0) ?>%</strong></div>
                    </div>
                    <div class="workflow-log timeline">
                        <?php foreach ((array)($workflow['items'] ?? []) as $entry): ?>
                            <article class="workflow-log__item timeline__item">
                                <strong><?= udsIbText($entry['TITLE'] ?? $entry['STAGE'] ?? $entry['STATUS'] ?? '') ?></strong>
                                <div class="uds-ib-muted"><?= udsIbText($entry['TEXT'] ?? $entry['COMMENT'] ?? '') ?></div>
                                <small><?= udsIbDate($entry['CREATED_AT'] ?? null) ?> · <?= udsIbText($entry['USER_LABEL'] ?? 'Система') ?></small>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <?php if (empty($workflow['items'])): ?><div class="empty-state empty-state--compact"><?= udsIbText($workflow['empty'] ?? 'История маршрута пока не заполнена.') ?></div><?php endif; ?>
                </div>

                <div class="detail-side__section">
                    <div class="panel__title">Авторы и доли участия</div>
                    <div class="detail-people">
                        <?php foreach ($authors as $author): ?>
                            <div class="detail-person-card">
                                <div class="detail-person-card__meta"><strong><?= udsIbText($author['USER_LABEL'] ?? 'Автор') ?></strong><span><?= udsIbText($author['USER_ROLE'] ?? '') ?></span></div>
                                <div class="detail-person-card__share"><?= (int)($author['SHARE'] ?? 0) ?>%</div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="detail-side__section">
                    <div class="panel__title">История статусов</div>
                    <div class="timeline">
                        <?php foreach ($history as $entry): ?>
                            <div class="timeline__item"><div class="timeline__dot"></div><div class="timeline__content"><strong><?= udsIbText($entry['label'] ?? '') ?></strong><p><?= udsIbText($entry['description'] ?? '') ?></p><span class="timeline__date"><?= udsIbDate($entry['date'] ?? null) ?></span></div></div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if ($feedback): ?>
                    <div class="detail-side__section">
                        <div class="panel__title">Обратная связь</div>
                        <?php foreach ($feedback as $item): ?>
                            <div class="uds-ib-list-item"><div><?= udsIbText($item['TEXT_VALUE'] ?? '') ?><br><span class="uds-ib-muted"><?= udsIbText($item['USER_LABEL'] ?? 'Участник') ?> · <?= udsIbDate($item['CREATED_AT'] ?? null) ?></span></div></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </aside>
        </div>
    </section>
<?php endif; ?>
<?php udsIbShellEnd(); ?>
