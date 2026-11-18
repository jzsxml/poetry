<?php

use Poetry\Site;
use Poetry\View;

/** @var list<array{href?: string, label: string, current?: bool}> $crumbs */
if (!isset($crumbs) || $crumbs === []) {
    return;
}
?>
<nav class="breadcrumb no-print" aria-label="面包屑">
  <ol class="breadcrumb__list">
<?php foreach ($crumbs as $i => $item):
    $label = (string) ($item['label'] ?? '');
    $isCurrent = !empty($item['current']);
    $href = isset($item['href']) && is_string($item['href']) ? $item['href'] : null;
?>
    <li<?= $isCurrent ? ' class="breadcrumb__current" aria-current="page"' : '' ?>>
<?php if (!$isCurrent && $href !== null && $href !== ''): ?>
      <a href="<?= View::e($href) ?>"><?= View::e(View::loc($label)) ?></a>
<?php else: ?>
      <?= View::e(View::loc($label)) ?>
<?php endif; ?>
    </li>
<?php endforeach; ?>
  </ol>
</nav>