(function () {
  const root = document.getElementById('corpus-page');
  if (!root) return;
  const corpus = JSON.parse(root.getAttribute('data-corpus') || '{}');
  if (!corpus.inDb) return;

  const KIND_BADGE = {
    tang: '唐', song: '宋', ci: '词', shijing: '经', chuci: '楚', lunyu: '论', sishu: '书',
    caocao: '魏', wudai: '五', yuding: '御', shuimo: '墨', yuanqu: '元', nalan: '清', youmeng: '梦', mengxue: '蒙',
  };
  const PAGE_SIZE = 30;
  const segEl = document.getElementById('corpus-segments');
  const hitsEl = document.getElementById('corpus-hits');
  const pagerEl = document.getElementById('corpus-pager');
  const errEl = document.getElementById('corpus-error');
  const emptyEl = document.getElementById('corpus-empty');

  const params = new URLSearchParams(location.search);
  const defaultSeg = corpus.segments?.[0]?.id ?? null;
  let segment = params.get('segment') || defaultSeg;
  let page = Math.max(1, parseInt(params.get('page') || '1', 10) || 1);
  let total = corpus.total || 0;
  let loading = false;

  function syncUrl() {
    const qs = new URLSearchParams();
    if (segment) qs.set('segment', segment);
    if (page > 1) qs.set('page', String(page));
    const q = qs.toString();
    history.replaceState(null, '', q ? `?${q}` : location.pathname);
  }

  function escapeHtml(s) {
    return s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  }

  function workListQuery() {
    const qs = new URLSearchParams();
    qs.set('list', 'corpus');
    qs.set('corpus', corpus.id);
    if (segment) qs.set('segment', segment);
    if (page > 1) qs.set('page', String(page));
    return `?${qs.toString()}`;
  }

  function renderSegments() {
    if (!corpus.segments?.length || !segEl) return;
    segEl.innerHTML = corpus.segments
      .map(
        (seg) =>
          `<button type="button" class="chip${segment === seg.id ? ' chip--active' : ''}" data-segment="${escapeHtml(seg.id)}">${escapeHtml(seg.label)}<span class="corpus-item__seg-count">${(seg.total || 0).toLocaleString()}</span></button>`
      )
      .join('');
    segEl.querySelectorAll('[data-segment]').forEach((btn) => {
      btn.addEventListener('click', () => {
        segment = btn.getAttribute('data-segment');
        page = 1;
        syncUrl();
        void loadWorks();
        renderSegments();
      });
    });
  }

  function renderPager() {
    const pages = Math.max(1, Math.ceil(total / PAGE_SIZE));
    if (!pagerEl || (pages <= 1 && !loading)) {
      if (pagerEl) pagerEl.hidden = true;
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
        const pages2 = Math.max(1, Math.ceil(total / PAGE_SIZE));
        if (dir === 'prev' && page > 1) {
          page--;
          syncUrl();
          void loadWorks();
        }
        if (dir === 'next' && page < pages2) {
          page++;
          syncUrl();
          void loadWorks();
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
    });
  }

  async function loadWorks() {
    loading = true;
    errEl.hidden = true;
    emptyEl.hidden = true;
    renderPager();
    if (window.PoetryPageLoad) {
      window.PoetryPageLoad.startListLoading(hitsEl, 8);
    } else {
      hitsEl.innerHTML = '';
    }
    try {
      const qs = new URLSearchParams({ limit: String(PAGE_SIZE), page: String(page) });
      if (segment) qs.set('segment', segment);
      const res = await fetch(`/api/corpora/${encodeURIComponent(corpus.id)}/works?${qs}`);
      const data = await res.json();
      if (!data.hits) {
        errEl.textContent = data.error || '加载失败';
        errEl.hidden = false;
        return;
      }
      total = data.total || 0;
      if (data.hits.length === 0) {
        emptyEl.hidden = false;
      } else {
        hitsEl.innerHTML = data.hits
          .map((h) => {
            const title = h.title || h.rhythmic || '无题';
            const badge = KIND_BADGE[h.kind] || h.kind;
            const sub = h.author + (h.rhythmic && h.title ? ` · ${h.rhythmic}` : '');
            return `<li class="result-card"><a href="/work/${encodeURIComponent(h.id)}${workListQuery()}"><span class="kind-badge">${escapeHtml(badge)}</span><strong>${escapeHtml(title)}</strong><span style="color:var(--muted);margin-left:0.5rem">${escapeHtml(sub)}</span><p class="result-card__preview">${escapeHtml(h.preview || '')}</p></a></li>`;
          })
          .join('');
      }
    } catch {
      errEl.textContent = '网络错误';
      errEl.hidden = false;
    } finally {
      loading = false;
      if (window.PoetryPageLoad) {
        window.PoetryPageLoad.stopListLoading(hitsEl);
      }
      if (pagerEl) {
        pagerEl.classList.remove('pager--loading');
        pagerEl.removeAttribute('aria-busy');
      }
      renderPager();
    }
  }

  renderSegments();
  void loadWorks();
})();
