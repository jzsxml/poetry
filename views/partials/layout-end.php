<?php require __DIR__ . '/site-footer.php'; ?>
<?php require __DIR__ . '/site-bottom.php'; ?>
<script src="/assets/baidu-hm.js" defer></script>
<script src="/assets/site.js" defer></script>
<script src="/assets/poetry-icons.js" defer></script>
<script src="/assets/pager-loading.js" defer></script>
<?php foreach ($extraScripts ?? [] as $src): ?>
<script src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>" defer></script>
<?php endforeach; ?>
<button type="button" class="back-to-top no-print" id="back-to-top" hidden aria-label="返回顶部" title="返回顶部"><span class="back-to-top__icon" aria-hidden="true">↑</span><span class="back-to-top__label">顶部</span></button>
</body>
</html>
