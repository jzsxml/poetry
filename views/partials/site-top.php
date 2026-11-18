<?php
$hillSvg = "url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1200 100' preserveAspectRatio='none'%3E%3Cpath fill='%232c2416' opacity='0.9' d='M0 0V45Q200 75 420 52T840 62T1200 38V0Z'/%3E%3C/svg%3E\")";
?>
<div class="site-top-petals site-top-petals--day" aria-hidden="true">
<?php for ($i = 0; $i < 14; $i++): ?>
  <span class="site-top-petals__flake" style="--petal-left: <?= 4 + ($i * 6.8) % 92 ?>%; --petal-delay: <?= $i * 1.8 ?>s; --petal-duration: <?= 12 + ($i % 5) * 2.5 ?>s; --petal-size: <?= 5 + ($i % 5) ?>px; --petal-drift: <?= -24 + ($i % 7) * 12 ?>px;"></span>
<?php endfor; ?>
</div>
<div class="site-top-scene site-top-scene--day" aria-hidden="true">
  <div class="site-top-scene__aura"></div>
  <div class="site-top-scene__sun"></div>
  <div class="site-top-scene__rays"></div>
  <div class="site-top-scene__moon"></div>
  <div class="site-top-scene__stars">
<?php for ($i = 0; $i < 12; $i++): ?>
    <span class="site-top-scene__star" style="--star-left: <?= 12 + ($i * 73) % 86 ?>%; --star-top: <?= 8 + ($i * 41) % 38 ?>%; --star-delay: <?= ($i % 5) * 0.9 ?>s; --star-size: <?= 1 + ($i % 3) ?>px;"></span>
<?php endfor; ?>
  </div>
  <div class="site-top-scene__clouds">
    <div class="site-top-scene__cloud site-top-scene__cloud--1"></div>
    <div class="site-top-scene__cloud site-top-scene__cloud--2"></div>
    <div class="site-top-scene__cloud site-top-scene__cloud--3"></div>
    <div class="site-top-scene__cloud site-top-scene__cloud--4"></div>
    <div class="site-top-scene__cloud site-top-scene__cloud--5"></div>
    <div class="site-top-scene__cloud site-top-scene__cloud--6"></div>
  </div>
  <div class="site-top-scene__hill" style="background-image: <?= $hillSvg ?>"></div>
</div>
