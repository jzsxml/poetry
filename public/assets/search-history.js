(function () {
  const STORAGE_KEY = 'poetry-search-history';
  const MAX_ITEMS = 12;

  function safeParse(raw) {
    if (!raw) return [];
    try {
      const data = JSON.parse(raw);
      if (!Array.isArray(data)) return [];
      return data
        .filter((x) => x && typeof x.q === 'string' && typeof x.kind === 'string')
        .map((x) => ({
          q: String(x.q).slice(0, 64),
          kind: String(x.kind),
          at: typeof x.at === 'number' ? x.at : 0,
        }));
    } catch {
      return [];
    }
  }

  function save(items) {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(items.slice(0, MAX_ITEMS)));
    } catch {
      /* ignore */
    }
  }

  window.PoetrySearchHistory = {
    load() {
      return safeParse(localStorage.getItem(STORAGE_KEY));
    },
    add(q, kind) {
      const trimmed = String(q || '').trim().slice(0, 64);
      if (!trimmed) return this.load();
      const items = this.load().filter((x) => !(x.q === trimmed && x.kind === kind));
      items.unshift({ q: trimmed, kind: kind || 'all', at: Date.now() });
      const next = items.slice(0, MAX_ITEMS);
      save(next);
      return next;
    },
    remove(q, kind) {
      const trimmed = String(q || '').trim();
      const next = this.load().filter((x) => !(x.q === trimmed && x.kind === kind));
      save(next);
      return next;
    },
    clear() {
      try {
        localStorage.removeItem(STORAGE_KEY);
      } catch {
        /* ignore */
      }
    },
  };
})();