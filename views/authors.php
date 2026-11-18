<?php

use Poetry\Database;
use Poetry\Site;
use Poetry\View;

$dbReady = Database::available();

$crumbs = [
    ['href' => '/', 'label' => Site::NAME],
    ['label' => '名人墙', 'current' => true],
];

View::layoutStart('名人墙 · ' . Site::NAME, ['/assets/author-wall-layout.js', '/assets/author-wall.js']);
?>
<div class="page-frame">
<?php require __DIR__ . '/partials/breadcrumb.php'; ?>
  <header class="authors-page__head">
    <h1 class="authors-page__title">名人墙</h1>
    <p class="authors-page__lead">字号越大作品越多 · 点击作者名将弹出篇目列表</p>
<?php if (!$dbReady): ?>
    <p class="status-text status-text--error">数据库未就绪</p>
<?php endif; ?>
  </header>

  <div class="chip-row" id="author-kind-chips">
<?php foreach (Site::KIND_FILTER_OPTIONS as $opt): ?>
    <button type="button" class="chip<?= $opt['value'] === 'all' ? ' chip--active' : '' ?>" data-kind="<?= View::e($opt['value']) ?>"><?= View::e($opt['label']) ?></button>
<?php endforeach; ?>
  </div>

  <section class="author-wall" aria-label="名人墙词云"<?= $dbReady ? '' : ' hidden' ?>>
    <div id="author-wall" class="author-wall-mosaic author-wall-mosaic--loading"></div>
  </section>
  <p class="status-text" id="author-wall-loading" hidden>载入中…</p>

  <p class="authors-page__back"><a href="/">返回首页</a></p>
</div>
<?php
View::layoutEnd();
