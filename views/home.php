<?php

use Poetry\Database;
use Poetry\Site;
use Poetry\View;

$dbReady = Database::available();
$corpusNames = [];
foreach (\Poetry\CorpusCatalog::all() as $c) {
    if (!empty($c['inDb'])) {
        $corpusNames[] = $c['name'];
    }
}

View::layoutStart(Site::TITLE, ['/assets/search-highlight.js', '/assets/author-wall-layout.js', '/assets/author-wall.js', '/assets/search-history.js', '/assets/work-preview.js', '/assets/home.js']);
?>
<div class="page-frame">
<?php require __DIR__ . '/partials/side-ornaments.php'; ?>
<main class="home-main">
  <header class="hero">
    <div class="hero__seal" aria-hidden="true">诗词<br />古库</div>
    <h1 class="hero__title"><a href="/"><?= View::e(Site::NAME) ?></a></h1>
    <p class="hero__subtitle"><?= View::e(Site::TAGLINE) ?></p>
    <div class="hero__rule" aria-hidden="true">并以情志 观古今之变</div>
  </header>

<?php if (!$dbReady): ?>
  <div class="banner-warn">
    数据库未就绪。请配置 MySQL 并执行 <code>composer run import</code>。
  </div>
<?php endif; ?>

  <section class="search-panel" aria-label="诗词检索" id="search-panel">
    <form class="search-panel__form" id="search-form">
      <div class="search-field">
        <input type="search" class="search-input" id="search-q" name="q" placeholder="搜作者或篇名…" autocomplete="off" enterkeyhint="search" autofocus aria-expanded="false" aria-controls="search-history" />
        <div class="search-history-dropdown" id="search-history" hidden>
          <div class="search-history-dropdown__head">
            <span class="search-history-dropdown__label">最近检索</span>
            <button type="button" class="search-history-dropdown__clear" id="search-history-clear">清空</button>
          </div>
          <ul class="search-history-dropdown__list" id="search-history-list" role="listbox" aria-label="最近检索"></ul>
        </div>
      </div>
      <button type="submit" class="search-panel__submit" id="search-submit" disabled>
        <?= View::iconSearch(17) ?><span class="ui-btn__label">检索</span>
      </button>
    </form>
    <div class="search-panel__progress" id="search-progress" hidden role="progressbar" aria-label="检索进行中" aria-valuetext="正在检索"><div class="search-panel__progress-bar"></div></div>
    <p class="search-panel__hint">请输入中文关键词，按回车或点「检索」开始查询（不自动检索）</p>
    <div class="chip-row search-panel__kinds" id="kind-chips">
<?php foreach (Site::KIND_FILTER_OPTIONS as $opt): ?>
      <button type="button" class="chip<?= $opt['value'] === 'all' ? ' chip--active' : '' ?>" data-kind="<?= View::e($opt['value']) ?>"><?= View::e($opt['label']) ?></button>
<?php endforeach; ?>
      <button type="button" class="chip chip--accent" id="btn-random"><?= View::iconShuffle(15) ?><span class="ui-btn__label">随机一首</span></button>
      <button type="button" class="chip" id="btn-authors"<?= $dbReady ? '' : ' disabled' ?>><?= View::iconUsers(15) ?><span class="ui-btn__label">名人墙</span></button>
    </div>
  </section>

  <div class="search-results" id="search-results" hidden>
    <p class="status-text status-text--error" id="search-error" hidden></p>
    <p class="status-text search-results__hint" id="search-hint" hidden></p>
    <p class="status-text search-results__summary" id="search-total" hidden></p>
    <p class="status-text" id="search-empty" hidden>未找到相关篇目</p>
    <ul class="result-list" id="search-hits" hidden aria-live="polite"></ul>
    <nav class="pager" id="search-pager" aria-label="检索分页" hidden></nav>
  </div>

  <div class="idle-section" id="idle-section">
    <h2 class="section-title"><span>诸库</span></h2>
    <div class="corpus-browser" id="corpus-browser"><p class="status-text">载入典籍…</p></div>

<?php if ($dbReady): ?>
    <h2 class="section-title"><span>时令荐诗</span></h2>
    <div class="season-card" id="season-card">
      <p class="status-text" id="season-loading">正在合日荐诗…</p>
      <div id="season-body" hidden></div>
    </div>

    <h2 class="section-title"><span>今日偶遇</span></h2>
    <div class="daily-card" id="daily-card">
      <div class="daily-card__label">每日一首</div>
      <p class="status-text" id="daily-loading">正在取诗…</p>
      <div id="daily-body" hidden></div>
    </div>
<?php endif; ?>
  </div>

  <section class="seo-intro" aria-labelledby="seo-intro-heading">
    <h2 id="seo-intro-heading" class="seo-intro__heading"><?= View::e(Site::NAME) ?>简介</h2>
    <p class="seo-intro__text">
      <?= View::e(Site::NAME) ?>是在线<?= View::e(str_replace(' · ', '、', Site::TAGLINE)) ?>。
      本站汇集<?= View::e(implode('、', array_slice($corpusNames, 0, 6))) ?>等典籍，可按作者与篇名快速定位作品，并提供简繁切换、随机读诗、时令荐诗与诸库浏览，适合诗词赏析、教学与自学。
    </p>
    <nav class="seo-intro__nav" aria-label="典籍导航">
      <span class="seo-intro__nav-label">诸库：</span>
      <ul class="seo-intro__list">
<?php foreach ($corpusNames as $name):
    $id = null;
    foreach (\Poetry\CorpusCatalog::all() as $c) {
        if ($c['name'] === $name) {
            $id = $c['id'];
            break;
        }
    }
    if ($id === null) {
        continue;
    }
?>
        <li><a href="/corpus/<?= View::e(rawurlencode($id)) ?>"><?= View::e(View::loc($name)) ?></a></li>
<?php endforeach; ?>
      </ul>
    </nav>
  </section>
</main>
</div>
<?php
View::layoutEnd();
