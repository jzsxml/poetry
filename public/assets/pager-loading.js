(function (global) {
  function skeletonRows(rows) {
    return Array.from({ length: rows }, () =>
      '<li><div class="result-card skeleton-result" aria-hidden="true">'
      + '<span class="skeleton skeleton-result__badge"></span>'
      + '<span class="skeleton skeleton-result__title"></span>'
      + '<span class="skeleton skeleton-result__author"></span>'
      + '<span class="skeleton skeleton-result__preview"></span>'
      + '</div></li>'
    ).join('');
  }

  let topBar = null;

  function topLoadingBar(show) {
    if (!topBar) {
      topBar = document.createElement('div');
      topBar.className = 'route-loading-bar';
      topBar.setAttribute('aria-hidden', 'true');
      document.body.appendChild(topBar);
    }
    topBar.hidden = !show;
  }

  function startListLoading(listEl, rows) {
    if (!listEl) return;
    const n = rows || 6;
    listEl.setAttribute('aria-busy', 'true');
    listEl.classList.add('result-list--loading');
    listEl.innerHTML = skeletonRows(n);
    listEl.hidden = false;
    listEl.removeAttribute('hidden');
    topLoadingBar(true);
  }

  function stopListLoading(listEl) {
    if (listEl) {
      listEl.removeAttribute('aria-busy');
      listEl.classList.remove('result-list--loading');
    }
    topLoadingBar(false);
  }

  function pagerProgressMarkup() {
    return '<div class="pager__progress" aria-hidden="true"><div class="search-panel__progress-bar"></div></div>';
  }

  global.PoetryPageLoad = {
    startListLoading,
    stopListLoading,
    pagerProgressMarkup,
  };
})(window);
