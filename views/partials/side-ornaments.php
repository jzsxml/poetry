<?php
/** 与 Next SideInkOrnaments 首屏文案一致 */
$left = '诗卷千载';
$right = '千载有知音';

function ornamentChars(string $text): array
{
    return preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
}
?>
<div class="side-ornament side-ornament--left side-ornament--ink" aria-hidden="true" style="--ink-stroke-duration: <?= count(ornamentChars($left)) * 0.4 + 0.9 ?>s;">
  <div class="side-ornament__inner">
    <span class="side-ornament__brush"></span>
<?php foreach (ornamentChars($left) as $i => $char): ?>
    <span class="side-ornament__char" style="animation-delay: <?= $i * 0.4 ?>s;"><?= htmlspecialchars($char, ENT_QUOTES, 'UTF-8') ?></span>
<?php endforeach; ?>
  </div>
</div>
<div class="side-ornament side-ornament--right side-ornament--ink" aria-hidden="true" style="--ink-stroke-duration: <?= count(ornamentChars($right)) * 0.4 + 0.9 ?>s;">
  <div class="side-ornament__inner">
    <span class="side-ornament__brush"></span>
<?php foreach (ornamentChars($right) as $i => $char): ?>
    <span class="side-ornament__char" style="animation-delay: <?= $i * 0.4 ?>s;"><?= htmlspecialchars($char, ENT_QUOTES, 'UTF-8') ?></span>
<?php endforeach; ?>
  </div>
</div>
