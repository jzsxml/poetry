(function () {
  const LAYOUT = () => window.PoetryAuthorWallLayout;
  const LIMIT = 120;
  const KIND_BADGE = {
    tang: '唐',
    song: '宋',
    ci: '词',
    shijing: '经',
    chuci: '楚',
    lunyu: '论',
    sishu: '书',
    caocao: '魏',
    wudai: '五',
    yuding: '御',
    shuimo: '墨',
    yuanqu: '元',
    nalan: '清',
    youmeng: '梦',
    mengxue: '蒙',
  };

  /** @type {{ getKind: () => string, setKind: (k: string) => void, searchAuthor: (name: string) => void } | null} */
  let homeBridge = null;

  /** @type {Map<string, { items: { author: string, count: number }[], ts: number }>} */
  const wallItemsCache = new Map();
  const WALL_CLIENT_TTL_MS = 60 * 60 * 1000;

  async function fetchWallItems(kind, signal) {
    const now = Date.now();
    const cached = wallItemsCache.get(kind);
    if (cached && now - cached.ts < WALL_CLIENT_TTL_MS) {
      return { ok: true, items: cached.items };
    }
    const res = await fetch(`/api/authors/wall?${new URLSearchParams({ kind, limit: String(LIMIT) })}`, {
      signal,
    });
    const data = await res.json();
    if (!res.ok) {
      return { ok: false, error: data.error || '加载失败', items: [] };
    }
    const items = data.items || [];
    wallItemsCache.set(kind, { items, ts: now });
    return { ok: true, items };
  }

  function escapeHtml(s) {
    return s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  }

  function loadingMarkup() {
    return `<div class="author-wall-loading" aria-busy="true">
      <p class="author-wall-loading__text">名人汇聚中…</p>
      <div class="author-wall-loading__progress" role="progressbar" aria-label="名人墙加载中" aria-valuetext="正在加载">
        <div class="author-wall-loading__progress-bar"></div>
      </div>
    </div>`;
  }

  function createCloud(mosaicEl, getKind, onTagClick) {
    const layoutAuthorTags = LAYOUT()?.layoutAuthorTags;
    if (!layoutAuthorTags) return { destroy() {} };

    let items = [];
    let loading = true;
    let error = null;
    let box = { w: 640, h: 640 };
    let ro = null;
    let abort = null;

    function measure() {
      const rect = mosaicEl.getBoundingClientRect();
      if (rect.width > 0 && rect.height > 0) {
        box = { w: Math.floor(rect.width), h: Math.floor(rect.height) };
      }
    }

    function renderTags() {
      if (loading || error || items.length === 0) return;
      const inputs = items.map((item) => ({
        key: item.author,
        label: item.author,
        count: item.count,
      }));
      const placed = layoutAuthorTags(inputs, box.w, box.h);
      const motion = document.createElement('div');
      motion.className = 'author-wall-mosaic__motion';
      motion.innerHTML = placed
        .map((tag, index) => {
          const vert = tag.vertical ? ' author-wall-mosaic__tag--vertical' : '';
          return `<button type="button" class="author-wall-mosaic__tag${vert}" title="${escapeHtml(tag.label)} · ${tag.count} 篇" style="left:${tag.x}px;top:${tag.y}px;font-size:${tag.fontPx}px;color:${tag.color};font-weight:${tag.weight};--aw-dur:${6 + (index % 9) * 0.7}s;--aw-delay:${-((index * 0.35) % 8)}s;--aw-shimmer:${3.8 + (index % 7) * 0.5}s;--aw-shimmer-delay:${-((index * 0.27) % 6)}s;--aw-breathe:${7 + (index % 5) * 1.1}s;--aw-breathe-delay:${-((index * 0.41) % 9)}s" data-author="${escapeHtml(tag.key)}">${escapeHtml(tag.label)}</button>`;
        })
        .join('');
      motion.querySelectorAll('[data-author]').forEach((btn) => {
        btn.addEventListener('click', () => onTagClick(btn.getAttribute('data-author')));
      });
      mosaicEl.appendChild(motion);
    }

    function paint() {
      mosaicEl.classList.toggle('author-wall-mosaic--loading', loading);
      mosaicEl.classList.toggle('author-wall-mosaic--error', Boolean(error) && !loading);
      mosaicEl.innerHTML = '';
      if (loading) {
        mosaicEl.innerHTML = loadingMarkup();
        return;
      }
      if (error) {
        mosaicEl.innerHTML = `<p class="author-wall-loading__error status-text status-text--error">${escapeHtml(error)}</p>`;
        return;
      }
      if (items.length === 0) {
        mosaicEl.innerHTML = '<p class="author-wall-loading__error status-text">暂无数据</p>';
        return;
      }
      measure();
      renderTags();
    }

    async function reload() {
      if (abort) abort.abort();
      abort = new AbortController();
      loading = true;
      error = null;
      paint();
      try {
        const kind = getKind();
        const result = await fetchWallItems(kind, abort.signal);
        if (!result.ok) {
          error = result.error || '加载失败';
          items = [];
        } else {
          items = result.items;
        }
      } catch (e) {
        if (e && e.name === 'AbortError') return;
        error = '网络错误';
        items = [];
      } finally {
        if (abort && abort.signal.aborted) return;
        loading = false;
        paint();
      }
    }

    measure();
    ro = typeof ResizeObserver !== 'undefined' ? new ResizeObserver(() => {
      measure();
      if (!loading && !error && items.length > 0) {
        mosaicEl.innerHTML = '';
        renderTags();
      }
    }) : null;
    if (ro) ro.observe(mosaicEl);

    void reload();

    return {
      reload,
      destroy() {
        if (ro) ro.disconnect();
        if (abort) abort.abort();
      },
    };
  }

  function openAuthorDialog(author, kind, handlers) {
    const existing = document.getElementById('author-wall-author-modal');
    if (existing) existing.remove();

    const root = document.createElement('div');
    root.id = 'author-wall-author-modal';
    root.className = 'work-modal author-wall-author-modal';
    root.setAttribute('role', 'presentation');
    root.innerHTML = `<div class="work-modal__panel author-wall-author-modal__panel" role="dialog" aria-modal="true" aria-labelledby="author-wall-author-title">
      <header class="work-modal__head">
        <button type="button" class="work-modal__close" aria-label="关闭">×</button>
      </header>
      <div class="author-wall-author-modal__body">
        <h2 id="author-wall-author-title" class="author-wall-author-modal__title">${escapeHtml(author)}</h2>
        <p class="work-modal__status">载入篇目…</p>
      </div>
      <footer class="author-wall-author-modal__foot">
        <button type="button" class="chip chip--accent" data-action="search">在首页检索该作者</button>
      </footer>
    </div>`;

    const close = () => {
      root.remove();
      document.removeEventListener('keydown', onKey);
      handlers.onClose?.();
    };
    const onKey = (e) => {
      if (e.key === 'Escape') close();
    };

    root.addEventListener('click', (e) => {
      if (e.target === root) close();
    });
    const authorCloseBtn = root.querySelector('.work-modal__close');
    if (authorCloseBtn) {
      authorCloseBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        e.preventDefault();
        close();
      });
    }
    root.querySelector('.work-modal__panel')?.addEventListener('click', (e) => e.stopPropagation());
    root.querySelector('[data-action="search"]')?.addEventListener('click', () => {
      handlers.onSearchInHome?.(author);
      close();
    });
    document.body.appendChild(root);
    document.addEventListener('keydown', onKey);

    const body = root.querySelector('.author-wall-author-modal__body');
    void (async () => {
      try {
        const res = await fetch(
          `/api/search?q=${encodeURIComponent(author)}&kind=${encodeURIComponent(kind)}&limit=8&page=1`
        );
        const data = await res.json();
        if (!res.ok) {
          body.innerHTML = `<h2 id="author-wall-author-title" class="author-wall-author-modal__title">${escapeHtml(author)}</h2>
            <p class="work-modal__status work-modal__status--error">${escapeHtml(data.error || '加载失败')}</p>`;
          return;
        }
        const hits = data.hits || [];
        const total = data.total || 0;
        if (hits.length === 0) {
          body.innerHTML = `<h2 id="author-wall-author-title" class="author-wall-author-modal__title">${escapeHtml(author)}</h2>
            <p class="work-modal__status">暂无匹配篇目</p>`;
          return;
        }
        const meta = total > 0 ? `<p class="author-wall-author-modal__meta">库内约 ${total.toLocaleString()} 篇</p>` : '';
        const list = hits
          .map((h) => {
            const title = h.title || h.rhythmic || '无题';
            const badge = KIND_BADGE[h.kind] || h.kind;
            const rhythmic =
              h.rhythmic && h.title
                ? `<span class="author-wall-author-modal__rhythmic">${escapeHtml(h.rhythmic)}</span>`
                : '';
            return `<li><button type="button" class="author-wall-author-modal__hit" data-work-id="${escapeHtml(h.id)}">
              <span class="kind-badge">${escapeHtml(badge)}</span>
              <strong>${escapeHtml(title)}</strong>${rhythmic}
              <span class="author-wall-author-modal__preview">${escapeHtml(h.preview || '')}</span>
            </button></li>`;
          })
          .join('');
        body.innerHTML = `<h2 id="author-wall-author-title" class="author-wall-author-modal__title">${escapeHtml(author)}</h2>${meta}
          <ul class="author-wall-author-modal__list">${list}</ul>`;
        body.querySelectorAll('[data-work-id]').forEach((btn) => {
          btn.addEventListener('click', () => {
            const id = btn.getAttribute('data-work-id');
            if (id) {
              close();
              if (handlers.onPreviewWork) handlers.onPreviewWork(id);
              else location.href = `/work/${encodeURIComponent(id)}`;
            }
          });
        });
      } catch {
        body.innerHTML = `<h2 id="author-wall-author-title" class="author-wall-author-modal__title">${escapeHtml(author)}</h2>
          <p class="work-modal__status work-modal__status--error">网络错误</p>`;
      }
    })();
  }

  function initAuthorsPage() {
    const mosaic = document.getElementById('author-wall');
    const chips = document.getElementById('author-kind-chips');
    const pageLoading = document.getElementById('author-wall-loading');
    if (!mosaic || !chips) return;

    let kind = 'all';
    chips.querySelectorAll('.chip[data-kind]').forEach((btn) => {
      if (btn.classList.contains('chip--active')) kind = btn.getAttribute('data-kind') || 'all';
    });

    let cloud = null;
    const ensureCloud = () => {
      if (cloud) cloud.destroy();
      if (pageLoading) pageLoading.hidden = true;
      cloud = createCloud(mosaic, () => kind, (name) => {
        openAuthorDialog(name, kind, {
          onSearchInHome(name2) {
            location.href = `/?q=${encodeURIComponent(name2)}&kind=${encodeURIComponent(kind === 'all' ? 'all' : kind)}`;
          },
        });
      });
    };

    chips.querySelectorAll('.chip[data-kind]').forEach((btn) => {
      btn.addEventListener('click', () => {
        kind = btn.getAttribute('data-kind') || 'all';
        chips.querySelectorAll('.chip[data-kind]').forEach((b) => {
          b.classList.toggle('chip--active', b === btn);
        });
        ensureCloud();
      });
    });

    ensureCloud();
  }

  function buildHomeModal() {
    const existing = document.getElementById('author-wall-home-modal');
    if (existing) return existing;

    const chipsHost = document.getElementById('kind-chips');
    const chipHtml = chipsHost
      ? Array.from(chipsHost.querySelectorAll('.chip[data-kind]'))
          .map(
            (btn) =>
              `<button type="button" class="chip${btn.classList.contains('chip--active') ? ' chip--active' : ''}" data-wall-kind="${escapeHtml(btn.getAttribute('data-kind') || 'all')}">${escapeHtml(btn.textContent.trim())}</button>`
          )
          .join('')
      : '';

    const root = document.createElement('div');
    root.id = 'author-wall-home-modal';
    root.className = 'work-modal';
    root.setAttribute('role', 'presentation');
    root.hidden = true;
    root.innerHTML = `<div class="work-modal__panel author-wall-modal__panel" role="dialog" aria-modal="true" aria-labelledby="author-wall-modal-title">
      <header class="work-modal__head">
        <button type="button" class="work-modal__close" aria-label="关闭">×</button>
      </header>
      <div class="author-wall-modal__intro">
        <h2 id="author-wall-modal-title" class="author-wall-modal__title">名人墙</h2>
        <p class="author-wall-modal__hint">字号越大作品越多 · 词云轻浮动 · 悬停单名可定阅 · 点击作者查看篇目</p>
      </div>
      <div class="chip-row author-wall-modal__kinds" id="author-wall-modal-kinds">${chipHtml}</div>
      <div class="author-wall-modal__cloud-wrap">
        <section class="author-wall" aria-label="名人墙词云">
          <div id="author-wall-modal-mosaic" class="author-wall-mosaic author-wall-mosaic--loading">${loadingMarkup()}</div>
        </section>
      </div>
    </div>`;
    document.body.appendChild(root);
    return root;
  }

  let homeModalCloud = null;
  let homeWallKind = 'all';
  const homeModalRuntime = { close: null, onKey: null };

  function bindHomeModalShell(root) {
    if (root.dataset.shellBound === '1') return;
    root.dataset.shellBound = '1';
    const panel = root.querySelector('.work-modal__panel');
    const closeBtn = root.querySelector('.work-modal__close');
    panel?.addEventListener('click', (e) => e.stopPropagation());
    closeBtn?.addEventListener('click', (e) => {
      e.stopPropagation();
      e.preventDefault();
      homeModalRuntime.close?.();
    });
    root.addEventListener('click', (e) => {
      if (e.target === root) homeModalRuntime.close?.();
    });
  }

  function openHomeModal() {
    const bridge = homeBridge;
    if (!bridge) return;
    homeWallKind = bridge.getKind();
    const root = buildHomeModal();
    bindHomeModalShell(root);
    const mosaic = document.getElementById('author-wall-modal-mosaic');
    const kinds = root.querySelector('#author-wall-modal-kinds');
    if (!mosaic) return;

    const close = () => {
      root.setAttribute('hidden', '');
      document.body.style.overflow = '';
      if (homeModalRuntime.onKey) {
        document.removeEventListener('keydown', homeModalRuntime.onKey);
        homeModalRuntime.onKey = null;
      }
      if (homeModalCloud) {
        homeModalCloud.destroy();
        homeModalCloud = null;
      }
      homeModalRuntime.close = null;
    };
    const onKey = (e) => {
      if (e.key === 'Escape' && !document.getElementById('author-wall-author-modal')) close();
    };
    homeModalRuntime.close = close;
    homeModalRuntime.onKey = onKey;

    function onAuthorPick(name) {
      openAuthorDialog(name, homeWallKind, {
        onSearchInHome(author) {
          close();
          bridge.searchAuthor(author);
        },
        onPreviewWork(id) {
          close();
          location.href = `/work/${encodeURIComponent(id)}`;
        },
      });
    }

    kinds?.querySelectorAll('[data-wall-kind]').forEach((btn) => {
      btn.classList.toggle('chip--active', btn.getAttribute('data-wall-kind') === homeWallKind);
      btn.onclick = () => {
        homeWallKind = btn.getAttribute('data-wall-kind') || 'all';
        kinds.querySelectorAll('[data-wall-kind]').forEach((b) => {
          b.classList.toggle('chip--active', b === btn);
        });
        bridge.setKind(homeWallKind);
        if (homeModalCloud) homeModalCloud.destroy();
        homeModalCloud = createCloud(mosaic, () => homeWallKind, onAuthorPick);
      };
    });

    root.removeAttribute('hidden');
    document.body.style.overflow = 'hidden';
    document.addEventListener('keydown', onKey);

    if (homeModalCloud) homeModalCloud.destroy();
    mosaic.classList.add('author-wall-mosaic--loading');
    homeModalCloud = createCloud(mosaic, () => homeWallKind, onAuthorPick);
  }

  function initHomeButton() {
    const btn = document.getElementById('btn-authors');
    if (!btn || btn.disabled || btn.dataset.wallBound === '1') return;
    btn.dataset.wallBound = '1';
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      openHomeModal();
    });
  }

  window.PoetryAuthorWall = {
    registerHomeBridge(bridge) {
      homeBridge = bridge;
    },
    openHomeModal,
    initAuthorsPage,
    initHomeButton,
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAuthorsPage);
  } else {
    initAuthorsPage();
  }
})();