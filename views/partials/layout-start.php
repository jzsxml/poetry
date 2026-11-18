<?php
use Poetry\Site;
use Poetry\View;

$v = View::variant();
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= View::e($pageTitle) ?></title>
  <meta name="description" content="<?= View::e(Site::DESCRIPTION) ?>" />
  <link rel="icon" href="/icon.svg" type="image/svg+xml" />
  <link rel="stylesheet" href="/assets/poetry-fonts.css" />
  <link rel="stylesheet" href="/assets/globals.css" />
  <link rel="stylesheet" href="/assets/pager-ui.css" />
</head>
<body>
<?php require __DIR__ . '/site-top.php'; ?>
<div class="script-toggle no-print" role="group" aria-label="简繁切换">
  <button type="button" class="script-toggle__btn<?= $v === 'simp' ? ' script-toggle__btn--active' : '' ?>" data-script="simp">简</button>
  <button type="button" class="script-toggle__btn<?= $v === 'trad' ? ' script-toggle__btn--active' : '' ?>" data-script="trad">繁</button>
</div>
