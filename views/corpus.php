<?php

use Poetry\Site;
use Poetry\View;

/** @var array<string, mixed> $corpus */
$corpusJson = json_encode($corpus, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

$crumbs = [
    ['href' => '/', 'label' => Site::NAME],
    ['label' => (string) $corpus['name'], 'current' => true],
];

View::layoutStart($corpus['name'] . ' · ' . Site::NAME, ['/assets/corpus.js']);
?>
<div class="page-frame" id="corpus-page" data-corpus="<?= View::e($corpusJson) ?>">
<main class="corpus-page">
<?php require __DIR__ . '/partials/breadcrumb.php'; ?>
  <header class="corpus-page__header">
    <div class="corpus-page__glyph" aria-hidden="true"><?= View::e(mb_substr($corpus['name'], 0, 1, 'UTF-8')) ?></div>
    <div>
      <h1 class="corpus-page__title"><?= View::e(View::loc($corpus['name'])) ?></h1>
<?php if (!empty($corpus['desc'])): ?>
      <p class="corpus-page__desc"><?= View::e(View::loc($corpus['desc'])) ?></p>
<?php endif; ?>
<?php if (!empty($corpus['inDb'])): ?>
      <p class="corpus-page__meta">共 <?= number_format((int) ($corpus['total'] ?? 0)) ?> 篇</p>
<?php endif; ?>
    </div>
  </header>

<?php if (empty($corpus['inDb'])): ?>
  <p class="corpus-item__hint">该卷尚未写入数据库。请执行 <code>composer run import</code>。</p>
<?php else: ?>
  <div class="chip-row corpus-item__segments" id="corpus-segments"></div>
  <p class="status-text status-text--error" id="corpus-error" hidden></p>
  <div id="corpus-loading" class="corpus-list-skeleton" hidden></div>
  <ul class="result-list corpus-page__list" id="corpus-hits"></ul>
  <p class="status-text" id="corpus-empty" hidden>暂无篇目</p>
  <nav class="pager" id="corpus-pager" aria-label="分页" hidden></nav>
<?php endif; ?>
</main>
</div>
<?php
View::layoutEnd();
