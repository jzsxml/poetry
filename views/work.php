<?php

use Poetry\Labels;
use Poetry\ListContext;
use Poetry\Site;
use Poetry\View;
use Poetry\WorkLayout;

/** @var array<string, mixed> $payload */
$work = $payload['work'];
$bio = $payload['bio'] ?? null;
$authorMore = $payload['authorMore'] ?? [];
$authorMoreCount = (int) ($payload['authorMoreCount'] ?? 0);
$prev = $payload['prev'] ?? null;
$next = $payload['next'] ?? null;

$title = $work['title'] ?: ($work['rhythmic'] ?: '无题');
$pageTitle = $title . ' · ' . $work['author'];
$paragraphs = is_array($work['paragraphs']) ? $work['paragraphs'] : [];
$notes = is_array($work['notes'] ?? null) ? $work['notes'] : [];

$locTitle = View::loc($title);
$locAuthor = View::loc($work['author']);
$locParagraphs = array_map(static fn ($l) => View::loc((string) $l), $paragraphs);
$locNotes = array_map(static fn ($n) => View::loc((string) $n), $notes);
$locBioFull = is_string($bio) && $bio !== '' ? View::loc($bio) : null;
$locBioDisplay = $locBioFull !== null && mb_strlen($locBioFull) > 400
    ? mb_substr($locBioFull, 0, 400, 'UTF-8') . '…'
    : $locBioFull;

$filenameBase = preg_replace('/[<>:"\/\\\\|?*\x00-\x1f]/u', '_', trim("{$locTitle}-{$locAuthor}"));
$filenameBase = mb_substr($filenameBase ?: 'poem', 0, 72, 'UTF-8');
$proseLayout = WorkLayout::prefersProseBody($paragraphs);

$workJson = json_encode([
    'title' => $locTitle,
    'author' => $locAuthor,
    'paragraphs' => $locParagraphs,
    'notes' => $locNotes,
    'authorBioFull' => $locBioFull,
    'authorBioDisplay' => $locBioDisplay,
    'filenameBase' => $filenameBase,
    'proseLayout' => $proseLayout,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_THROW_ON_ERROR);

$listCtx = ListContext::fromRequest();
$listQs = ListContext::workHrefSuffix($listCtx);
$listHref = ListContext::listHref($listCtx);

$crumbs = [
    ['href' => '/', 'label' => Site::NAME],
];
if ($listCtx !== null && $listHref !== null) {
    if (($listCtx['type'] ?? '') === 'corpus') {
        $crumbs[] = [
            'href' => $listHref,
            'label' => (string) ($listCtx['corpusName'] ?? '典籍列表'),
        ];
    } elseif (($listCtx['type'] ?? '') === 'search') {
        $crumbs[] = [
            'href' => $listHref,
            'label' => '检索：' . (string) ($listCtx['q'] ?? ''),
        ];
    }
}
$crumbs[] = ['label' => $title, 'current' => true];

View::layoutStart($pageTitle, ['/assets/work-reader.js']);
?>
<div class="page-frame">
<script type="application/json" id="work-json"><?= $workJson ?></script>
<template id="tpl-icon-volume"><?= View::iconVolume(16) ?></template>
<template id="tpl-icon-volume-off"><?= View::iconVolumeOff(16) ?></template>
<main class="work-page<?= $proseLayout ? ' work-page--prose' : '' ?>" id="work-page">
<?php require __DIR__ . '/partials/breadcrumb.php'; ?>

  <div class="work-toolbar no-print" aria-label="阅读工具">
    <div class="work-toolbar__group" role="group" aria-label="字号">
      <button type="button" class="work-toolbar__btn" data-zoom="out" aria-label="缩小字号" title="缩小"><?= View::iconZoomOut(16) ?></button>
      <span class="work-toolbar__zoom" id="work-zoom-label" aria-live="polite">100%</span>
      <button type="button" class="work-toolbar__btn" data-zoom="in" aria-label="放大字号" title="放大"><?= View::iconZoomIn(16) ?></button>
    </div>
    <button type="button" class="work-toolbar__btn" id="work-btn-pinyin" aria-pressed="false" aria-label="显示拼音" title="标注拼音">
      <?= View::iconType(16) ?><span class="ui-btn__label">拼音</span>
    </button>
    <button type="button" class="work-toolbar__btn" id="work-btn-speech" aria-pressed="false" aria-label="朗读诗文" title="语音朗读">
      <span id="work-speech-icon" aria-hidden="true"><?= View::iconVolume(16) ?></span>
      <span class="ui-btn__label" id="work-speech-label">朗读</span>
    </button>
    <button type="button" class="work-toolbar__btn" id="work-btn-print" aria-label="打印">
      <?= View::iconPrint(16) ?><span class="ui-btn__label">打印</span>
    </button>
    <button type="button" class="work-toolbar__btn" id="work-btn-pdf" aria-label="下载 PDF">
      <?= View::iconPdf(16) ?><span class="ui-btn__label">PDF</span>
    </button>
    <button type="button" class="work-toolbar__btn" id="work-btn-docx" aria-label="下载 Word 文档">
      <?= View::iconBook(16) ?><span class="ui-btn__label">Word</span>
    </button>
  </div>

<?php
$prevHint = $prev ? View::loc($prev['title'] ?? $prev['rhythmic'] ?? '无题') : '';
$nextHint = $next ? View::loc($next['title'] ?? $next['rhythmic'] ?? '无题') : '';
$pagerHint = ($prev && $next) ? ($prevHint . ' · ' . $nextHint) : ($prevHint ?: $nextHint);
?>
<?php if ($prev || $next): ?>
  <nav class="pager work-neighbor-pager no-print work-neighbor-pager--top" aria-label="篇目翻页">
    <a class="chip pager__btn pager__btn--prev<?= $prev ? '' : ' chip--disabled' ?>" <?= $prev ? 'href="/work/' . View::e(rawurlencode($prev['id'])) . View::e($listQs) . '"' : 'aria-disabled="true" tabindex="-1"' ?>>← 上一篇</a>
    <span class="pager__info work-neighbor-pager__hint" title="<?= View::e($pagerHint) ?>"><?= View::e($pagerHint ?: '同卷连续阅读') ?></span>
    <a class="chip pager__btn pager__btn--next<?= $next ? '' : ' chip--disabled' ?>" <?= $next ? 'href="/work/' . View::e(rawurlencode($next['id'])) . View::e($listQs) . '"' : 'aria-disabled="true" tabindex="-1"' ?>>下一篇 →</a>
  </nav>
<?php endif; ?>

  <article class="work-article work-article--readable<?= $proseLayout ? ' work-article--prose' : '' ?>" id="work-print-root" style="--work-scale: 1;">
    <p class="work-article__kind">
      <?= View::e(View::loc(Labels::kindLabel($work['kind'], $work['corpus'] ?? null))) ?>
<?php if (!empty($work['rhythmic']) && !empty($work['title'])): ?>
       · <?= View::e(View::loc($work['rhythmic'])) ?>
<?php endif; ?>
    </p>
    <h1 class="work-article__title" id="work-article__title"><?= View::e($locTitle) ?></h1>
    <p class="work-article__author" id="work-article__author"><?= View::e($locAuthor) ?></p>
    <div class="work-article__body" id="work-article__body">
<?php foreach ($locParagraphs as $line): ?>
      <p><?= View::e($line) ?></p>
<?php endforeach; ?>
    </div>
<?php if ($notes !== []): ?>
    <section class="work-section" id="work-section-notes">
      <h2>注释</h2>
<?php foreach ($locNotes as $n): ?>
      <p class="work-section__p"><?= View::e($n) ?></p>
<?php endforeach; ?>
    </section>
<?php endif; ?>
<?php if ($locBioDisplay !== null): ?>
    <section class="work-section">
      <h2>作者</h2>
      <p class="work-section__p" id="work-section-bio-text"><?= View::e($locBioDisplay) ?></p>
    </section>
<?php endif; ?>
  </article>

<?php if ($prev || $next): ?>
  <nav class="pager work-neighbor-pager no-print work-neighbor-pager--bottom" aria-label="篇目翻页">
    <a class="chip pager__btn pager__btn--prev<?= $prev ? '' : ' chip--disabled' ?>" <?= $prev ? 'href="/work/' . View::e(rawurlencode($prev['id'])) . View::e($listQs) . '"' : 'aria-disabled="true" tabindex="-1"' ?>>← 上一篇</a>
    <span class="pager__info work-neighbor-pager__hint"><?= View::e($pagerHint ?: '同卷连续阅读') ?></span>
    <a class="chip pager__btn pager__btn--next<?= $next ? '' : ' chip--disabled' ?>" <?= $next ? 'href="/work/' . View::e(rawurlencode($next['id'])) . View::e($listQs) . '"' : 'aria-disabled="true" tabindex="-1"' ?>>下一篇 →</a>
  </nav>
<?php endif; ?>

<?php if ($authorMore !== []): ?>
  <section class="work-more no-print" aria-labelledby="work-more-title">
    <h2 id="work-more-title" class="section-title work-more__title"><span><?= View::e($locAuthor) ?> · 其他篇目</span></h2>
    <ul class="result-list work-more__list">
<?php foreach ($authorMore as $h):
    $ht = $h['title'] ?: ($h['rhythmic'] ?: '无题');
?>
      <li class="result-card">
        <a href="/work/<?= View::e(rawurlencode($h['id'])) . View::e($listQs) ?>">
          <span class="kind-badge"><?= View::e(Labels::kindBadge($h['kind'])) ?></span>
          <strong><?= View::e(View::loc($ht)) ?></strong>
<?php if (!empty($h['rhythmic']) && !empty($h['title'])): ?>
          <span style="color: var(--muted); margin-left: 0.5rem;"><?= View::e(View::loc($h['rhythmic'])) ?></span>
<?php endif; ?>
          <p class="result-card__preview"><?= View::e(View::loc($h['preview'] ?? '')) ?></p>
        </a>
      </li>
<?php endforeach; ?>
    </ul>
<?php if ($authorMoreCount > count($authorMore)): ?>
    <p class="work-more__foot">
      <a href="/?q=<?= rawurlencode($work['author']) ?>" class="work-more__search">检索该作者全部 <?= number_format($authorMoreCount) ?> 篇 →</a>
    </p>
<?php endif; ?>
  </section>
<?php endif; ?>
</main>
</div>
<?php
View::layoutEnd();