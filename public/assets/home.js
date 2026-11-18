(function () {
  const KIND_BADGE = {
    tang: '唐', song: '宋', ci: '词', shijing: '经', chuci: '楚', lunyu: '论', sishu: '书',
    caocao: '魏', wudai: '五', yuding: '御', shuimo: '墨', yuanqu: '元', nalan: '清', youmeng: '梦', mengxue: '蒙',
  };
  const PAGE_SIZE = 15;

  const form = document.getElementById('search-form');
  const qEl = document.getElementById('search-q');
  const panel = document.getElementById('search-panel');
  const progress = document.getElementById('search-progress');
  const submitBtn = document.getElementById('search-submit');
  const resultsWrap = document.getElementById('search-results');
  const errEl = document.getElementById('search-error');
  const hintEl = document.getElementById('search-hint');
  const totalEl = document.getElementById('search-total');
  const emptyEl = document.getElementById('search-empty');
  const hitsEl = document.getElementById('search-hits');
  const pagerEl = document.getElementById('search-pager');
  const idleEl = document.getElementById('idle-section');
  const corpusBrowser = document.getElementById('corpus-browser');
  const historyWrap = document.getElementById('search-history');
  const historyList = document.getElementById('search-history-list');
  const historyClear = document.getElementById('search-history-clear');

  const KIND_LABEL = {
    all: '全部', tang: '唐诗', song: '宋诗', ci: '宋词', yuanqu: '元曲',
  };

  let kind = 'all';
  let activeQ = '';
  let page = 1;
  let total = 0;
  let loading = false;
  let hasResults = false;
  let totalCapped = false;
  let browseMax = 500;
  let searchRequestSeq = 0;
  /** @type {Record<number, string>} */
  let pageAfterId = { 1: '' };
  let seasonOffset = 0;
  let seasonLoading = false;
  let historyDropdownOpen = false;
  let historyBlurTimer = null;
  /** @type {string[]} */
  let highlightTerms = [];

  function setIdleVisible(show) {
    if (idleEl) idleEl.hidden = !show;
    syncSearchHistoryDropdown();
  }

  function setKindValue(k) {
    kind = k;
    document.querySelectorAll('#kind-chips .chip[data-kind]').forEach((btn) => {
      btn.classList.toggle('chip--active', btn.getAttribute('data-kind') === kind);
    });
  }

  function syncSearchHistoryDropdown() {
    if (!historyWrap || !historyList || !window.PoetrySearchHistory) return;
    const items = window.PoetrySearchHistory.load();
    if (!items.length) {
      historyWrap.hidden = true;
      historyList.innerHTML = '';
      if (qEl) qEl.setAttribute('aria-expanded', 'false');
      return;
    }
    historyList.innerHTML = items
      .map((item) => {
        const k = item.kind && KIND_LABEL[item.kind] ? item.kind : 'all';
        const kindHint =
          k !== 'all'
            ? `<span class="search-history-dropdown__kind">${escapeHtml(KIND_LABEL[k] || '')}</span>`
            : '';
        return `<li role="presentation"><button type="button" class="search-history-dropdown__item" role="option" data-q="${escapeHtml(item.q)}" data-kind="${escapeHtml(k)}"><span class="search-history-dropdown__q">${escapeHtml(item.q)}</span>${kindHint}</button></li>`;
      })
      .join('');
    historyList.querySelectorAll('.search-history-dropdown__item').forEach((btn) => {
      btn.addEventListener('click', () => {
        const q = btn.getAttribute('data-q') || '';
        const k = btn.getAttribute('data-kind') || 'all';
        if (qEl) qEl.value = q;
        if (['all', 'tang', 'song', 'ci', 'yuanqu'].includes(k)) setKindValue(k);
        historyDropdownOpen = false;
        syncSearchHistoryDropdown();
        void runSearch(q, kind, 1);
      });
    });
    const show = historyDropdownOpen && !activeQ.trim();
    historyWrap.hidden = !show;
    if (qEl) qEl.setAttribute('aria-expanded', show ? 'true' : 'false');
  }

  function renderSearchHistory() {
    syncSearchHistoryDropdown();
  }

  function setResultsVisible(show) {
    if (!resultsWrap) return;
    resultsWrap.hidden = !show;
    resultsWrap.classList.toggle('search-results--visible', show);
  }

  function syncSearchForm() {
    const trimmed = (qEl?.value || '').trim();
    if (submitBtn) submitBtn.disabled = !trimmed || loading;
    if (panel) {
      panel.classList.toggle('search-panel--loading', loading);
      panel.setAttribute('aria-busy', loading ? 'true' : 'false');
    }
  }

  qEl?.addEventListener('input', syncSearchForm);

  qEl?.addEventListener('focus', () => {
    if (historyBlurTimer) {
      clearTimeout(historyBlurTimer);
      historyBlurTimer = null;
    }
    historyDropdownOpen = true;
    syncSearchHistoryDropdown();
  });
  qEl?.addEventListener('blur', () => {
    historyBlurTimer = setTimeout(() => {
      historyDropdownOpen = false;
      syncSearchHistoryDropdown();
    }, 160);
  });
  historyWrap?.addEventListener('mousedown', (e) => {
    if (e.target.closest('#search-history-clear')) return;
    e.preventDefault();
  });

  function kindFromUrl() {
    const k = new URLSearchParams(location.search).get('kind');
    return k && ['all', 'tang', 'song', 'ci', 'yuanqu'].includes(k) ? k : null;
  }

  function seedFromUrl() {
    const params = new URLSearchParams(location.search);
    const q = params.get('q')?.trim();
    const k = kindFromUrl();
    const p = Math.max(1, parseInt(params.get('page') || '1', 10) || 1);
    if (k) {
      kind = k;
      document.querySelectorAll('#kind-chips .chip[data-kind]').forEach((btn) => {
        btn.classList.toggle('chip--active', btn.getAttribute('data-kind') === kind);
      });
    }
    if (q) {
      qEl.value = q;
      void runSearch(q, kind, p);
    }
  }

  function escapeHtml(s) {
    return s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  }

  function workHrefFromList(id) {
    const base = `/work/${encodeURIComponent(id)}`;
    if (!activeQ.trim()) return base;
    const qs = new URLSearchParams();
    qs.set('list', 'search');
    qs.set('q', activeQ);
    qs.set('kind', kind);
    if (page > 1) qs.set('page', String(page));
    return `${base}?${qs.toString()}`;
  }

  function highlightField(text) {
    const src = text == null ? '' : String(text);
    const hl = window.PoetrySearchHighlight;
    if (!hl || !highlightTerms.length) return escapeHtml(src);
    return hl.highlightSearchText(src, highlightTerms);
  }

  function renderHit(h) {
    const title = h.title || h.rhythmic || '无题';
    const badge = KIND_BADGE[h.kind] || h.kind;
    const sub = h.author + (h.rhythmic && h.title ? ` · ${h.rhythmic}` : '');
    return `<li><button type="button" class="result-card result-card--clickable" data-work-id="${escapeHtml(h.id)}">`
      + `<span class="kind-badge">${escapeHtml(badge)}</span>`
      + `<strong class="result-card__title">${highlightField(title)}</strong>`
      + `<span class="result-card__meta">${highlightField(sub)}</span>`
      + `<p class="result-card__preview">${highlightField(h.preview || '')}</p></button></li>`;
  }

  function effectiveTotalForPager() {
    const cap = Math.min(total, browseMax > 0 ? browseMax : total);
    return cap;
  }

  function renderPager() {
    if (!pagerEl) return;
    const pages = Math.max(1, Math.ceil(effectiveTotalForPager() / PAGE_SIZE));
    if (pages <= 1 && !loading) {
      pagerEl.hidden = true;
      pagerEl.classList.remove('pager--loading');
      return;
    }
    pagerEl.hidden = false;
    pagerEl.removeAttribute('hidden');
    pagerEl.classList.toggle('pager--loading', loading);
    const progress = loading && window.PoetryPageLoad ? window.PoetryPageLoad.pagerProgressMarkup() : '';
    pagerEl.setAttribute('aria-busy', loading ? 'true' : 'false');
    const ic = window.PoetryIcons || {};
    pagerEl.innerHTML = `
      <button type="button" class="chip" data-page="prev" ${page <= 1 || loading ? 'disabled' : ''}>${ic.chevronLeft || ''}<span class="ui-btn__label">上一页</span></button>
      <span class="pager__info">${loading ? '载入中…' : `第 ${page} / ${pages} 页`}</span>
      <button type="button" class="chip" data-page="next" ${page >= pages || loading ? 'disabled' : ''}><span class="ui-btn__label">下一页</span>${ic.chevronRight || ''}</button>
      ${progress}`;
    pagerEl.querySelectorAll('button[data-page]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const dir = btn.getAttribute('data-page');
        const pages2 = Math.max(1, Math.ceil(effectiveTotalForPager() / PAGE_SIZE));
        if (dir === 'prev' && page > 1) void runSearch(activeQ, kind, page - 1);
        if (dir === 'next' && page < pages2) void runSearch(activeQ, kind, page + 1);
        panel?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      });
    });
  }

  async function runSearch(q, k, p) {
    const trimmed = q.trim();
    if (!trimmed) {
      activeQ = '';
      hitsEl.hidden = true;
      totalEl.hidden = true;
      emptyEl.hidden = true;
      pagerEl.hidden = true;
      setResultsVisible(false);
      setIdleVisible(true);
      syncSearchForm();
      void loadSeason(0);
      void loadDaily();
      return;
    }
    const paginating = p > 1 || (hitsEl && !hitsEl.hidden && hitsEl.querySelector('.result-card:not(.skeleton-result)'));
    const reqId = ++searchRequestSeq;
    loading = true;
    syncSearchForm();
    if (p === 1) {
      hasResults = false;
      pageAfterId = { 1: '' };
      total = 0;
      if (pagerEl) {
        pagerEl.hidden = true;
        pagerEl.classList.remove('pager--loading');
        pagerEl.removeAttribute('aria-busy');
      }
    }
    activeQ = trimmed;
    page = p;
    setIdleVisible(false);
    setResultsVisible(true);
    progress.hidden = false;
    errEl.hidden = true;
    if (hintEl) hintEl.hidden = true;
    emptyEl.hidden = true;
    if (window.PoetryPageLoad && (p === 1 || paginating)) {
      window.PoetryPageLoad.startListLoading(hitsEl, p === 1 ? 6 : 5);
    }
    try {
      let url = `/api/search?q=${encodeURIComponent(trimmed)}&kind=${k}&limit=${PAGE_SIZE}&page=${p}`;
      const afterId = pageAfterId[p];
      if (afterId && p > 1) {
        url += `&after_id=${encodeURIComponent(afterId)}`;
      }
      const res = await fetch(url);
      const data = await res.json();
      if (reqId !== searchRequestSeq) return;
      if (!res.ok) {
        errEl.textContent = data.error || '检索失败';
        errEl.hidden = false;
        hitsEl.hidden = true;
        totalEl.hidden = true;
        if (hintEl) hintEl.hidden = true;
        return;
      }
      if (typeof data.browseMax === 'number' && data.browseMax > 0) {
        browseMax = data.browseMax;
      }
      if (hintEl) hintEl.hidden = true;
      if (window.PoetrySearchHistory) {
        window.PoetrySearchHistory.add(trimmed, k);
        renderSearchHistory();
      }
      total = data.total || 0;
      totalCapped = Boolean(data.totalCapped);
      highlightTerms = Array.isArray(data.highlightTerms) && data.highlightTerms.length
        ? data.highlightTerms
        : (window.PoetrySearchHighlight?.termsFromQuery(trimmed) || []);
      const hits = data.hits || [];
      if (hits.length === 0) {
        hasResults = false;
        emptyEl.hidden = false;
        hitsEl.hidden = true;
        totalEl.hidden = true;
        if (p > 1) {
          page = 1;
          pageAfterId = { 1: '' };
        }
      } else {
        hasResults = true;
        hitsEl.innerHTML = hits.map(renderHit).join('');
        if (hits.length > 0 && data.nextAfterId) {
          pageAfterId[p + 1] = data.nextAfterId;
        }
        hitsEl.hidden = false;
        hitsEl.removeAttribute('hidden');
        const pages = Math.max(1, Math.ceil(effectiveTotalForPager() / PAGE_SIZE));
        const totalLabel = totalCapped
          ? `超过 ${(total - 1).toLocaleString()} 条结果`
          : `共 ${total.toLocaleString()} 条结果`;
        let msg = totalLabel;
        if (pages > 1) {
          msg += `<span class="season-card__sep">·</span>每页 ${PAGE_SIZE} 条`;
        }
        totalEl.innerHTML = msg;
        totalEl.hidden = false;
        totalEl.removeAttribute('hidden');
      }
    } catch {
      if (reqId !== searchRequestSeq) return;
      errEl.textContent = '网络错误';
      errEl.hidden = false;
      hasResults = false;
    } finally {
      if (reqId !== searchRequestSeq) return;
      loading = false;
      syncSearchForm();
      progress.hidden = true;
      if (window.PoetryPageLoad) {
        window.PoetryPageLoad.stopListLoading(hitsEl);
      }
      if (pagerEl) {
        pagerEl.classList.remove('pager--loading');
        pagerEl.removeAttribute('aria-busy');
        if (hasResults && effectiveTotalForPager() > PAGE_SIZE) {
          pagerEl.hidden = false;
          renderPager();
        } else {
          pagerEl.hidden = true;
          pagerEl.innerHTML = '';
        }
      }
    }
  }

  hitsEl?.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-work-id]');
    if (!btn || !hitsEl.contains(btn)) return;
    const id = btn.getAttribute('data-work-id');
    if (!id || !window.PoetryWorkPreview) return;
    window.PoetryWorkPreview.open(id, { fullHref: workHrefFromList(id) });
  });

  form?.addEventListener('submit', (e) => {
    e.preventDefault();
    void runSearch(qEl.value, kind, 1);
  });

  document.querySelectorAll('#kind-chips .chip[data-kind]').forEach((btn) => {
    btn.addEventListener('click', () => {
      setKindValue(btn.getAttribute('data-kind') || 'all');
      if (activeQ) void runSearch(activeQ, kind, 1);
    });
  });

  historyClear?.addEventListener('click', () => {
    window.PoetrySearchHistory?.clear();
    renderSearchHistory();
  });

  document.getElementById('btn-random')?.addEventListener('click', async () => {
    try {
      const res = await fetch(`/api/random${kind !== 'all' ? `?kind=${kind}` : ''}`);
      const data = await res.json();
      if (data.id) location.href = `/work/${encodeURIComponent(data.id)}`;
    } catch { /* ignore */ }
  });

  async function loadCorpora() {
    try {
      const res = await fetch('/api/corpora');
      const data = await res.json();
      const list = data.corpora || [];
      corpusBrowser.innerHTML = `<div class="corpus-chips" role="list">${list
        .map(
          (c) =>
            `<a class="corpus-chip" role="listitem" href="/corpus/${encodeURIComponent(c.id)}" title="${escapeHtml(c.name)}"><span class="corpus-chip__glyph" aria-hidden="true">${escapeHtml(c.name.slice(0, 1))}</span><span class="corpus-chip__name">${escapeHtml(c.name)}</span><span class="corpus-chip__count">${c.inDb ? `${(c.total || 0).toLocaleString()}首` : '未入库'}</span></a>`
        )
        .join('')}</div>`;
    } catch {
      corpusBrowser.innerHTML = '<p class="status-text status-text--error">典籍列表加载失败</p>';
    }
  }

  function renderSeason(season) {
    const body = document.getElementById('season-body');
    const loadingEl = document.getElementById('season-loading');
    if (!body || !loadingEl || !season) return;
    const label =
      season.primary
        ? `${season.primary.roleLabel} · ${season.primary.name}`
        : '岁时无特定节令';
    let metaHtml = escapeHtml(season.solarYmd);
    metaHtml += `<span class="season-card__sep">·</span>${escapeHtml(season.lunarLabel || '')}`;
    if (season.secondary?.[0]?.name) {
      metaHtml += `<span class="season-card__sep">·</span>${escapeHtml(season.secondary[0].name)}`;
    }
    const list = (season.hits || [])
      .map((h) => {
        const title = h.title || h.rhythmic || '无题';
        return `<li class="season-list__item"><a href="/work/${encodeURIComponent(h.id)}"><strong>${escapeHtml(title)}</strong>`
          + `<span class="season-list__author">${escapeHtml(h.author)}</span>`
          + `<span class="season-list__preview">${escapeHtml(h.preview || '')}</span></a></li>`;
      })
      .join('');
    body.innerHTML = `<div class="season-card__label">${escapeHtml(label)}</div>`
      + `<p class="season-card__meta">${metaHtml}</p>`
      + `<ul class="season-list">${list}</ul>`
      + `<button type="button" class="suggest-tag" id="season-reshuffle"${seasonLoading ? ' disabled' : ''}>${seasonLoading ? '换批中…' : '换一批'}</button>`;
    body.hidden = false;
    loadingEl.hidden = true;
    document.getElementById('season-reshuffle')?.addEventListener('click', () => {
      void loadSeason(seasonOffset + 1);
    });
  }

  async function loadSeason(offset = 0) {
    const loadingEl = document.getElementById('season-loading');
    const body = document.getElementById('season-body');
    if (!loadingEl) return;
    seasonLoading = true;
    if (!body?.hidden) {
      const btn = document.getElementById('season-reshuffle');
      if (btn) {
        btn.textContent = '换批中…';
        btn.disabled = true;
      }
    } else {
      loadingEl.hidden = false;
      body.hidden = true;
      loadingEl.textContent = '正在合日荐诗…';
    }
    try {
      const res = await fetch(`/api/recommend/today?limit=6&offset=${encodeURIComponent(offset)}`);
      if (!res.ok) {
        loadingEl.textContent = '荐诗暂不可用';
        return;
      }
      const data = await res.json();
      seasonOffset = data.offset ?? offset;
      renderSeason(data);
    } catch {
      loadingEl.textContent = '荐诗加载失败';
      loadingEl.hidden = false;
    } finally {
      seasonLoading = false;
    }
  }

  async function loadDaily() {
    const loadingEl = document.getElementById('daily-loading');
    const body = document.getElementById('daily-body');
    if (!body) return;
    try {
      const res = await fetch('/api/random');
      if (!res.ok) {
        if (loadingEl) {
          loadingEl.textContent = res.status === 503 ? '数据库未就绪，随机读诗暂不可用' : '随机读诗暂不可用';
          loadingEl.hidden = false;
        }
        return;
      }
      const daily = await res.json();
      if (!daily.id) return;
      const title = daily.title || daily.rhythmic || '无题';
      const meta = daily.author + (daily.rhythmic && daily.title ? ` · ${daily.rhythmic}` : '');
      const lines = (daily.paragraphs || []).slice(0, 4).map((l) => `<p style="margin:0.2rem 0">${escapeHtml(l)}</p>`).join('');
      body.innerHTML = `<h3 class="daily-card__title">${escapeHtml(title)}</h3><p class="daily-card__meta">${escapeHtml(meta)}</p><div class="daily-card__lines">${lines}</div><a href="/work/${encodeURIComponent(daily.id)}" class="daily-card__link">阅读全文 →</a><button type="button" class="suggest-tag" id="daily-reshuffle" style="margin-left:1rem">换一首</button>`;
      body.hidden = false;
      loadingEl.hidden = true;
      document.getElementById('daily-reshuffle')?.addEventListener('click', () => {
        loadingEl.hidden = false;
        body.hidden = true;
        void loadDaily();
      });
    } catch {
      loadingEl.textContent = '取诗失败';
    }
  }

  if (window.PoetryAuthorWall) {
    window.PoetryAuthorWall.registerHomeBridge({
      getKind: () => kind,
      setKind: (k) => {
        setKindValue(k);
      },
      searchAuthor: (name) => {
        qEl.value = name;
        void runSearch(name, kind, 1);
        setIdleVisible(false);
        panel?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      },
      onPreviewWork: (id) => {
        if (window.PoetryWorkPreview) {
          window.PoetryWorkPreview.open(id, {
            fullHref: activeQ.trim() ? workHrefFromList(id) : `/work/${encodeURIComponent(id)}`,
          });
        }
      },
    });
    window.PoetryAuthorWall.initHomeButton();
  }

  const urlParams = new URLSearchParams(location.search);
  const urlQ = urlParams.get('q')?.trim();
  loadCorpora();
  if (!urlQ) {
    void loadSeason(0);
    void loadDaily();
  }
  seedFromUrl();
  renderSearchHistory();
  syncSearchForm();
})();
