(function () {
  const KIND_BADGE = {
    tang: '唐', song: '宋', ci: '词', shijing: '经', chuci: '楚', lunyu: '论', sishu: '书',
    caocao: '魏', wudai: '五', yuding: '御', shuimo: '墨', yuanqu: '元', nalan: '清', youmeng: '梦', mengxue: '蒙',
  };

  let root = null;
  let abortCtrl = null;
  let fullHref = '';

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  }

  function lineText(line) {
    if (line == null) return '';
    if (typeof line === 'string') return line;
    if (typeof line === 'object' && line !== null) {
      if (typeof line.paragraphs === 'string') return line.paragraphs;
      if (Array.isArray(line.paragraphs)) {
        return line.paragraphs.map(lineText).filter(Boolean).join('\n');
      }
    }
    return '';
  }

  function ensureRoot() {
    if (root) return root;
    root = document.createElement('div');
    root.className = 'work-modal';
    root.setAttribute('hidden', '');
    root.setAttribute('role', 'presentation');
    document.body.appendChild(root);
    root.addEventListener('click', close);
    return root;
  }

  function onKey(e) {
    if (e.key === 'Escape') close();
  }

  function close() {
    if (!root) return;
    root.setAttribute('hidden', '');
    document.body.style.overflow = '';
    window.removeEventListener('keydown', onKey);
    if (abortCtrl) {
      abortCtrl.abort();
      abortCtrl = null;
    }
  }

  function kindLine(work) {
    const corpus = work.corpus || '';
    const base = corpus || KIND_BADGE[work.kind] || work.kind || '';
    if (work.rhythmic && work.title) {
      return `${base} · ${work.rhythmic}`;
    }
    return base;
  }

  function renderPanel(state) {
    const el = ensureRoot();
    if (state.loading) {
      el.innerHTML = `<div class="work-modal__panel" role="dialog" aria-modal="true" aria-labelledby="work-modal-title" aria-busy="true">
        <header class="work-modal__head">
          <button type="button" class="work-modal__close" aria-label="关闭">×</button>
        </header>
        <p class="work-modal__status">载入中…</p>
      </div>`;
    } else if (state.error) {
      el.innerHTML = `<div class="work-modal__panel" role="dialog" aria-modal="true">
        <header class="work-modal__head">
          <button type="button" class="work-modal__close" aria-label="关闭">×</button>
        </header>
        <p class="work-modal__status work-modal__status--error">${escapeHtml(state.error)}</p>
      </div>`;
    } else if (state.work) {
      const work = state.work;
      const title = work.title || work.rhythmic || '无题';
      const paras = (work.paragraphs || []).map((line) => `<p>${escapeHtml(lineText(line))}</p>`).join('');
      const notes = Array.isArray(work.notes) && work.notes.length > 0
        ? `<section class="work-modal__notes"><h3>注释</h3>${work.notes.map((n) => `<p>${escapeHtml(lineText(n))}</p>`).join('')}</section>`
        : '';
      const badge = KIND_BADGE[work.kind] || work.kind;
      el.innerHTML = `<div class="work-modal__panel" role="dialog" aria-modal="true" aria-labelledby="work-modal-title">
        <header class="work-modal__head">
          <button type="button" class="work-modal__close" aria-label="关闭">×</button>
        </header>
        <article class="work-modal__article">
          <p class="work-modal__meta">
            <span class="kind-badge">${escapeHtml(badge)}</span>
            <span class="work-modal__kind">${escapeHtml(kindLine(work))}</span>
          </p>
          <h2 id="work-modal-title" class="work-modal__title">${escapeHtml(title)}</h2>
          <p class="work-modal__author">${escapeHtml(work.author || '')}</p>
          <div class="work-modal__body">${paras}</div>
          ${notes}
        </article>
        <footer class="work-modal__foot">
          <a href="${escapeHtml(fullHref)}" class="work-modal__full">阅读全文 →</a>
        </footer>
      </div>`;
    }
    el.querySelector('.work-modal__panel')?.addEventListener('click', (e) => e.stopPropagation());
    el.querySelector('.work-modal__close')?.addEventListener('click', (e) => {
      e.stopPropagation();
      close();
    });
    const fullLink = el.querySelector('.work-modal__full');
    if (fullLink) {
      fullLink.addEventListener('click', () => close());
    }
  }

  async function open(workId, options) {
    if (!workId) return;
    fullHref = (options && options.fullHref) || `/work/${encodeURIComponent(workId)}`;
    const el = ensureRoot();
    el.removeAttribute('hidden');
    document.body.style.overflow = 'hidden';
    window.addEventListener('keydown', onKey);
    renderPanel({ loading: true });
    if (abortCtrl) abortCtrl.abort();
    abortCtrl = new AbortController();
    try {
      const res = await fetch(`/api/work/${encodeURIComponent(workId)}`, {
        signal: abortCtrl.signal,
      });
      const data = await res.json();
      if (!res.ok) {
        renderPanel({ error: data.error || '加载失败' });
        return;
      }
      const work = data.work || data;
      renderPanel({ work });
    } catch (err) {
      if (err && err.name === 'AbortError') return;
      renderPanel({ error: '网络错误' });
    }
  }

  window.PoetryWorkPreview = { open, close };
})();