(function (global) {
  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, (c) => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#39;',
    })[c]);
  }

  /** @param {string} query */
  function termsFromQuery(query) {
    const raw = String(query || '').trim();
    if (!raw) return [];
    const parts = raw.split(/\s+/).filter(Boolean);
    const terms = [];
    for (const p of parts) {
      if (p && !terms.includes(p)) terms.push(p);
    }
    return terms.sort((a, b) => b.length - a.length);
  }

  /**
   * @param {string} text
   * @param {string|string[]} queryOrTerms
   */
  function highlightSearchText(text, queryOrTerms) {
    const src = text == null ? '' : String(text);
  /** @type {string[]} */
    const terms = Array.isArray(queryOrTerms)
      ? queryOrTerms.filter(Boolean)
      : termsFromQuery(queryOrTerms);
    if (!src || terms.length === 0) return escapeHtml(src);

    /** @type {[number, number][]} */
    const ranges = [];
    for (const term of terms) {
      if (!term) continue;
      let idx = 0;
      while (idx <= src.length) {
        const pos = src.indexOf(term, idx);
        if (pos === -1) break;
        ranges.push([pos, pos + term.length]);
        idx = pos + 1;
      }
    }
    if (ranges.length === 0) return escapeHtml(src);

    ranges.sort((a, b) => a[0] - b[0]);
    /** @type {[number, number][]} */
    const merged = [];
    for (const r of ranges) {
      const last = merged[merged.length - 1];
      if (!last || r[0] > last[1]) merged.push(r);
      else last[1] = Math.max(last[1], r[1]);
    }

    let out = '';
    let pos = 0;
    for (const [s, e] of merged) {
      if (s > pos) out += escapeHtml(src.slice(pos, s));
      out += `<mark class="search-hit">${escapeHtml(src.slice(s, e))}</mark>`;
      pos = e;
    }
    if (pos < src.length) out += escapeHtml(src.slice(pos));
    return out;
  }

  global.PoetrySearchHighlight = {
    termsFromQuery,
    highlightSearchText,
    escapeHtml,
  };
})(typeof window !== 'undefined' ? window : globalThis);