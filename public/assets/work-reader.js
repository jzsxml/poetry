(function () {
  const ZOOM_LEVELS = [0.85, 1, 1.15, 1.3, 1.45];
  const ZOOM_STORAGE = 'poetry-work-zoom';
  const PINYIN_STORAGE = 'poetry-work-pinyin';

  const article = document.getElementById('work-print-root');
  const jsonEl = document.getElementById('work-json');
  if (!article || !jsonEl) return;

  /** @type {{ title: string, author: string, paragraphs: string[], notes: string[], authorBio: string | null, filenameBase: string }} */
  let work;
  try {
    work = JSON.parse(jsonEl.textContent || '{}');
  } catch {
    return;
  }

  let zoomIdx = 1;
  let pinyinOn = false;
  let speaking = false;
  let exportBusy = false;
  let pinyinFn = null;
  let speechCleanup = null;

  const zoomLabel = document.getElementById('work-zoom-label');
  const btnPinyin = document.getElementById('work-btn-pinyin');
  const btnSpeech = document.getElementById('work-btn-speech');
  const btnPdf = document.getElementById('work-btn-pdf');
  const btnDocx = document.getElementById('work-btn-docx');

  function esc(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  }

  function toRubySegments(text) {
    if (!text || !pinyinFn) return [...text].map((char) => ({ char, py: null }));
    try {
      const items = pinyinFn(text, { toneType: 'symbol', type: 'all', nonZh: 'consecutive' });
      return items.map((it) => ({
        char: it.origin,
        py: it.isZh !== false && /[\u4e00-\u9fff]/.test(it.origin) ? it.result : null,
      }));
    } catch {
      return [...text].map((char) => ({
        char,
        py: /[\u4e00-\u9fff]/.test(char) ? pinyinFn(char, { toneType: 'symbol' }) : null,
      }));
    }
  }

  function rubyHtml(text) {
    return toRubySegments(text)
      .map((seg) =>
        seg.py
          ? `<ruby class="pinyin-ruby">${esc(seg.char)}<rt class="pinyin-ruby__py">${esc(seg.py)}</rt></ruby>`
          : `<span class="pinyin-ruby__plain">${esc(seg.char)}</span>`
      )
      .join('');
  }

  function prefersProseBody(paragraphs) {
    const list = Array.isArray(paragraphs) ? paragraphs : [];
    if (list.length === 0) return false;
    let count = 0;
    let total = 0;
    let maxLen = 0;
    let longLines = 0;
    for (const p of list) {
      const t = String(p).replace(/\s+/g, '');
      const len = [...t].length;
      if (!len) continue;
      count++;
      total += len;
      if (len > maxLen) maxLen = len;
      if (len >= 28) longLines++;
    }
    if (!count) return false;
    if (count >= 12 || total >= 220 || maxLen >= 44) return true;
    return longLines >= 3 && longLines * 2 >= count;
  }

  const proseLayout = work.proseLayout !== undefined
    ? Boolean(work.proseLayout)
    : prefersProseBody(work.paragraphs);

  function applyProseLayout() {
    article.classList.toggle('work-article--prose', proseLayout);
    const page = document.getElementById('work-page');
    if (page) page.classList.toggle('work-page--prose', proseLayout);
  }

  function textHtml(text, centered) {
    if (!pinyinOn) return esc(text);
    const cls = centered ? 'pinyin-text pinyin-text--centered' : 'pinyin-text';
    return `<span class="${cls}">${rubyHtml(text)}</span>`;
  }

  function renderReadable() {
    applyProseLayout();
    const titleEl = document.getElementById('work-article__title');
    const authorEl = document.getElementById('work-article__author');
    const bodyEl = document.getElementById('work-article__body');
    const bodyCentered = !proseLayout;
    if (titleEl) titleEl.innerHTML = textHtml(work.title, true);
    if (authorEl) authorEl.innerHTML = textHtml(work.author, true);
    if (bodyEl) {
      bodyEl.classList.toggle('work-article__body--pinyin', pinyinOn);
      bodyEl.innerHTML = (work.paragraphs || [])
        .map((line) => `<p>${textHtml(line, bodyCentered)}</p>`)
        .join('');
    }
    const notesEl = document.getElementById('work-section-notes');
    if (notesEl && work.notes?.length) {
      notesEl.innerHTML = work.notes.map((n) => `<p class="work-section__p">${textHtml(n, false)}</p>`).join('');
    }
    const bioEl = document.getElementById('work-section-bio-text');
    if (bioEl && work.authorBioDisplay) {
      bioEl.innerHTML = textHtml(work.authorBioDisplay, false);
    }
  }

  async function ensurePinyin() {
    if (pinyinFn) return;
    const mod = await import('https://esm.sh/pinyin-pro@3.29.5');
    pinyinFn = mod.pinyin;
  }

  function applyZoom() {
    const scale = ZOOM_LEVELS[zoomIdx];
    article.style.setProperty('--work-scale', String(scale));
    if (zoomLabel) zoomLabel.textContent = `${Math.round(scale * 100)}%`;
    try {
      localStorage.setItem(ZOOM_STORAGE, String(zoomIdx));
    } catch { /* ignore */ }
  }

  function speechAvailable() {
    return 'speechSynthesis' in window && typeof SpeechSynthesisUtterance !== 'undefined';
  }

  function stopSpeech() {
    speechCleanup?.();
    speechCleanup = null;
    if (speechAvailable()) window.speechSynthesis.cancel();
    speaking = false;
    updateSpeechBtn();
  }

  function pickZhVoice() {
    const voices = window.speechSynthesis.getVoices();
    return voices.find((v) => v.lang === 'zh-CN') || voices.find((v) => v.lang.startsWith('zh')) || null;
  }

  function speakText(text) {
    if (!speechAvailable()) return;
    stopSpeech();
    const synth = window.speechSynthesis;
    const start = () => {
      const u = new SpeechSynthesisUtterance(text);
      u.lang = 'zh-CN';
      u.rate = 0.92;
      const voice = pickZhVoice();
      if (voice) u.voice = voice;
      u.onstart = () => {
        speaking = true;
        updateSpeechBtn();
      };
      u.onend = () => stopSpeech();
      u.onerror = () => stopSpeech();
      synth.speak(u);
    };
    if (synth.getVoices().length) start();
    else {
      const onV = () => {
        synth.removeEventListener('voiceschanged', onV);
        start();
      };
      synth.addEventListener('voiceschanged', onV);
      speechCleanup = () => {
        synth.removeEventListener('voiceschanged', onV);
        synth.cancel();
      };
    }
  }

  function speechText() {
    const body = (work.paragraphs || [])
      .map((line) => line.replace(/\s+/g, ''))
      .filter(Boolean)
      .join('。');
    return `${work.title}。作者${work.author}。${body}`;
  }

  function updateSpeechBtn() {
    if (!btnSpeech) return;
    btnSpeech.classList.toggle('work-toolbar__btn--active', speaking);
    btnSpeech.setAttribute('aria-pressed', speaking ? 'true' : 'false');
    btnSpeech.setAttribute('aria-label', speaking ? '停止朗读' : '朗读诗文');
    const label = document.getElementById('work-speech-label');
    if (label) label.textContent = speaking ? '停止' : '朗读';
    const slot = document.getElementById('work-speech-icon');
    const tpl = document.getElementById(speaking ? 'tpl-icon-volume-off' : 'tpl-icon-volume');
    if (slot && tpl) slot.innerHTML = tpl.innerHTML;
  }

  function exportContent() {
    return {
      title: work.title,
      author: work.author,
      lines: work.paragraphs || [],
      notes: work.notes || [],
      authorBio: work.authorBioFull || null,
      filenameBase: work.filenameBase || 'poem',
    };
  }

  function saveBlob(blob, filename) {
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    link.rel = 'noopener';
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(url), 2000);
  }

  function exportFilename(base, ext) {
    const safe = String(base || 'poem')
      .replace(/[<>:"/\\|?*\x00-\x1f]/g, '_')
      .slice(0, 80);
    return `${safe || 'poem'}.${ext}`;
  }

  async function downloadDocx() {
    if (exportBusy) return;
    exportBusy = true;
    btnPdf?.setAttribute('disabled', 'true');
    btnDocx?.setAttribute('disabled', 'true');
    try {
      let docxMod;
      try {
        docxMod = await import('https://esm.sh/docx@9.15.3');
      } catch {
        docxMod = await import('https://cdn.jsdelivr.net/npm/docx@9.15.3/+esm');
      }
      const { AlignmentType, Document, Packer, Paragraph, TextRun } = docxMod;
      const c = exportContent();
      const font = '宋体';
      const body = (text, centered) =>
        new Paragraph({
          alignment: centered ? AlignmentType.CENTER : AlignmentType.LEFT,
          spacing: { after: 160, line: 276 },
          children: [new TextRun({ text, size: centered ? 28 : 22, font })],
        });
      const section = (text) =>
        new Paragraph({
          alignment: AlignmentType.LEFT,
          spacing: { before: 400, after: 200 },
          children: [new TextRun({ text, bold: true, size: 24, font })],
        });
      const children = [
        new Paragraph({
          alignment: AlignmentType.CENTER,
          spacing: { after: 240 },
          children: [new TextRun({ text: c.title, bold: true, size: 36, font })],
        }),
        new Paragraph({
          alignment: AlignmentType.CENTER,
          spacing: { after: 360 },
          children: [new TextRun({ text: c.author, size: 28, font })],
        }),
        ...c.lines.map((line) => body(String(line), true)),
      ];
      if (c.notes.length) {
        children.push(section('注释'));
        c.notes.forEach((n) => children.push(body(n, false)));
      }
      if (c.authorBio) {
        children.push(section('作者'));
        children.push(body(c.authorBio, false));
      }
      const doc = new Document({ sections: [{ children }] });
      const blob = await Packer.toBlob(doc);
      if (!blob || blob.size < 1) {
        throw new Error('empty docx blob');
      }
      saveBlob(blob, exportFilename(c.filenameBase, 'docx'));
    } catch (e) {
      console.error(e);
      window.alert('Word 导出失败，请检查网络后重试');
    } finally {
      exportBusy = false;
      btnPdf?.removeAttribute('disabled');
      btnDocx?.removeAttribute('disabled');
    }
  }

  const PDF_W = 560;
  const PDF_H = Math.round((PDF_W * 269) / 182);

  function splitLongProse(text, max = 320) {
    const t = text.trim();
    if (t.length <= max) return [t];
    const parts = [];
    let rest = t;
    while (rest.length > max) {
      let cut = rest.lastIndexOf('。', max);
      if (cut < max * 0.25) cut = rest.lastIndexOf('，', max);
      if (cut < max * 0.25) cut = max;
      parts.push(rest.slice(0, cut + 1).trim());
      rest = rest.slice(cut + 1).trim();
    }
    if (rest) parts.push(rest);
    return parts;
  }

  async function downloadPdf() {
    if (exportBusy) return;
    exportBusy = true;
    btnPdf?.setAttribute('disabled', 'true');
    btnDocx?.setAttribute('disabled', 'true');
    try {
      const c = exportContent();
      const shellStyle = {
        width: `${PDF_W}px`,
        padding: '32px 40px',
        background: '#fffef9',
        color: '#2c2416',
        fontFamily: '"Noto Serif SC", "Songti SC", "SimSun", serif',
        fontSize: '18px',
        lineHeight: '2',
        textAlign: 'center',
        boxSizing: 'border-box',
      };
      const blocks = [];
      const mk = (tag, text, style) => {
        const el = document.createElement(tag);
        el.textContent = text;
        Object.assign(el.style, style);
        return el;
      };
      blocks.push(mk('h1', c.title, { fontSize: '28px', fontWeight: '400', margin: '0 0 8px' }));
      blocks.push(mk('p', c.author, { margin: '0 0 24px', color: '#8b4513', fontSize: '16px' }));
      c.lines.forEach((line) => blocks.push(mk('p', line, { margin: '8px 0' })));
      if (c.notes.length) {
        blocks.push(mk('h2', '注释', { fontSize: '15px', margin: '28px 0 12px', textAlign: 'left', color: '#5c4f3d' }));
        c.notes.forEach((note) => splitLongProse(note).forEach((chunk) => blocks.push(mk('p', chunk, { textAlign: 'left', fontSize: '15px', lineHeight: '1.85' }))));
      }
      if (c.authorBio) {
        blocks.push(mk('h2', '作者', { fontSize: '15px', margin: '28px 0 12px', textAlign: 'left', color: '#5c4f3d' }));
        splitLongProse(c.authorBio).forEach((chunk) => blocks.push(mk('p', chunk, { textAlign: 'left', fontSize: '15px', lineHeight: '1.85' })));
      }

      const measureRoot = document.createElement('div');
      Object.assign(measureRoot.style, { position: 'fixed', left: '-9999px', visibility: 'hidden' });
      document.body.appendChild(measureRoot);
      const pages = [];
      let page = document.createElement('div');
      Object.assign(page.style, shellStyle);
      measureRoot.appendChild(page);
      for (const block of blocks) {
        page.appendChild(block);
        if (page.scrollHeight > PDF_H) {
          page.removeChild(block);
          if (page.childElementCount) {
            measureRoot.removeChild(page);
            pages.push(page);
            page = document.createElement('div');
            Object.assign(page.style, shellStyle);
            measureRoot.appendChild(page);
          }
          page.appendChild(block);
        }
      }
      if (page.childElementCount) {
        measureRoot.removeChild(page);
        pages.push(page);
      }
      measureRoot.remove();

      if (document.fonts?.ready) await document.fonts.ready;
      await new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r)));

      const html2canvas = (await import('https://esm.sh/html2canvas@1.4.1')).default;
      const { jsPDF } = await import('https://esm.sh/jspdf@2.5.2');
      const margin = 14;
      const pdf = new jsPDF({ orientation: 'portrait', unit: 'mm', format: 'a4' });
      const pageW = pdf.internal.pageSize.getWidth();
      const pageH = pdf.internal.pageSize.getHeight();
      const printW = pageW - margin * 2;
      const printH = pageH - margin * 2;
      const mount = document.createElement('div');
      Object.assign(mount.style, { position: 'fixed', left: '-9999px', zIndex: '2147483647' });
      document.body.appendChild(mount);

      for (let i = 0; i < pages.length; i++) {
        mount.appendChild(pages[i]);
        const el = pages[i];
        const h = Math.max(el.scrollHeight, el.offsetHeight, 80);
        const canvas = await html2canvas(el, {
          scale: 2,
          backgroundColor: '#fffef9',
          useCORS: true,
          width: el.offsetWidth,
          height: h,
        });
        el.remove();
        const img = canvas.toDataURL('image/jpeg', 0.92);
        let imgW = printW;
        let imgH = (canvas.height * imgW) / canvas.width;
        if (imgH > printH) {
          imgH = printH;
          imgW = (canvas.width * imgH) / canvas.height;
        }
        const x = margin + (printW - imgW) / 2;
        if (i > 0) pdf.addPage();
        pdf.addImage(img, 'JPEG', x, margin, imgW, imgH);
      }
      mount.remove();
      pdf.save(`${c.filenameBase}.pdf`);
    } catch (e) {
      console.error(e);
    } finally {
      exportBusy = false;
      btnPdf?.removeAttribute('disabled');
      btnDocx?.removeAttribute('disabled');
    }
  }

  try {
    const zi = parseInt(localStorage.getItem(ZOOM_STORAGE) || '', 10);
    if (zi >= 0 && zi < ZOOM_LEVELS.length) zoomIdx = zi;
  } catch { /* ignore */ }
  try {
    pinyinOn = localStorage.getItem(PINYIN_STORAGE) === '1';
  } catch { /* ignore */ }

  applyZoom();
  if (pinyinOn) {
    void ensurePinyin().then(() => {
      btnPinyin?.classList.add('work-toolbar__btn--active');
      btnPinyin?.setAttribute('aria-pressed', 'true');
      renderReadable();
    });
  }

  document.querySelectorAll('[data-zoom]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const d = btn.getAttribute('data-zoom');
      if (d === 'out' && zoomIdx > 0) zoomIdx--;
      if (d === 'in' && zoomIdx < ZOOM_LEVELS.length - 1) zoomIdx++;
      applyZoom();
    });
  });

  btnPinyin?.addEventListener('click', async () => {
    await ensurePinyin();
    pinyinOn = !pinyinOn;
    btnPinyin.classList.toggle('work-toolbar__btn--active', pinyinOn);
    btnPinyin.setAttribute('aria-pressed', pinyinOn ? 'true' : 'false');
    try {
      localStorage.setItem(PINYIN_STORAGE, pinyinOn ? '1' : '0');
    } catch { /* ignore */ }
    renderReadable();
  });

  if (btnSpeech) {
    if (!speechAvailable()) btnSpeech.hidden = true;
    btnSpeech.addEventListener('click', () => {
      if (speaking) stopSpeech();
      else speakText(speechText());
    });
  }

  document.getElementById('work-btn-print')?.addEventListener('click', () => window.print());
  btnPdf?.addEventListener('click', () => void downloadPdf());
  btnDocx?.addEventListener('click', () => void downloadDocx());

  window.addEventListener('beforeunload', stopSpeech);
})();