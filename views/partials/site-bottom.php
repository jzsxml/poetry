<?php
$mountainSvg = "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 120' preserveAspectRatio='none'%3E%3Cpath fill='%232c2416' d='M0 120V75Q180 35 360 62T720 48T1080 68T1200 55V120Z'/%3E%3C/svg%3E\")";
$mountainNearSvg = "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 120' preserveAspectRatio='none'%3E%3Cpath fill='%232c2416' opacity='0.85' d='M0 120V92Q280 58 520 82T960 65T1200 78V120Z'/%3E%3Cpath fill='%232c2416' opacity='0.45' d='M0 120V105Q400 88 700 98T1200 90V120Z'/%3E%3C/svg%3E\")";
?>
<div class="site-bottom-scene" aria-hidden="true">
  <div class="site-bottom-scene__shimmer"></div>
  <div class="site-bottom-scene__glow"></div>
  <div class="site-bottom-scene__mist site-bottom-scene__mist--1"></div>
  <div class="site-bottom-scene__mist site-bottom-scene__mist--2"></div>
  <div class="site-bottom-scene__ridge site-bottom-scene__ridge--far" style="background-image: <?= $mountainSvg ?>"></div>
  <div class="site-bottom-scene__ridge site-bottom-scene__ridge--near" style="background-image: <?= $mountainNearSvg ?>"></div>
  <div class="site-bottom-scene__water"></div>
  <div class="site-bottom-scene__crane">
    <svg viewBox="0 0 48 24" class="site-bottom-scene__crane-svg" aria-hidden="true">
      <path fill="currentColor" d="M4 18c8-2 12-10 20-12 4-1 8 0 10 2-6 1-10 6-14 8-4 2-10 3-16 2z" opacity="0.5" />
      <path fill="currentColor" d="M28 8c2 0 4 1 5 3-3 0-5-1-8-2 2-2 4-3 6-3 2 0 3 1 4 2-2 0-4 0-7-2z" opacity="0.35" />
    </svg>
  </div>
  <div class="site-bottom-scene__petals">
<?php for ($i = 0; $i < 9; $i++): ?>
    <span class="site-bottom-scene__petal" style="--petal-delay: <?= $i * 2.2 ?>s; --petal-left: <?= 6 + $i * 9.5 ?>%; --petal-duration: <?= 16 + ($i % 3) * 4 ?>s; --petal-size: <?= 6 + ($i % 4) ?>px;"></span>
<?php endfor; ?>
  </div>
</div>
