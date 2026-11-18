(function (global) {
  const PALETTE = [
    '#d45656',
    '#c24a42',
    '#b85a4a',
    '#a65c4e',
    '#9a6b5c',
    '#8b6358',
    '#7a5548',
    '#6d5348',
    '#c98989',
    '#8f5f5f',
  ];

  function fontBounds(boxW, boxH) {
    const side = Math.min(boxW, boxH);
    return {
      min: Math.max(10, Math.round(side * 0.018)),
      max: Math.max(24, Math.round(side * 0.108)),
    };
  }

  function scaleFontPx(count, minC, maxC, fontMin, fontMax) {
    if (maxC <= minC) return (fontMin + fontMax) / 2;
    const linear = (count - minC) / (maxC - minC);
    const t = Math.pow(linear, 0.52);
    return fontMin + t * (fontMax - fontMin);
  }

  function preferVertical(key, index) {
    let h = 0;
    for (let i = 0; i < key.length; i++) {
      h = (h + key.charCodeAt(i) * (i + 1)) | 0;
    }
    return (Math.abs(h) + index) % 5 < 2;
  }

  function measureBox(ctx, label, fontPx, weight, vertical) {
    ctx.font = `${weight} ${fontPx}px "Noto Serif SC", "Songti SC", SimSun, serif`;
    if (vertical) {
      const line = fontPx * 1.08;
      return { w: line + 4, h: label.length * line + 2 };
    }
    const m = ctx.measureText(label);
    return { w: m.width + 6, h: fontPx * 1.18 + 4 };
  }

  function overlaps(a, b, gap) {
    return !(
      a.x + a.w + gap <= b.x ||
      b.x + b.w + gap <= a.x ||
      a.y + a.h + gap <= b.y ||
      b.y + b.h + gap <= a.y
    );
  }

  function placeOne(ctx, label, fontPx, weight, vertical, boxW, boxH, occupied) {
    const { w, h } = measureBox(ctx, label, fontPx, weight, vertical);
    const cx = boxW / 2;
    const cy = boxH / 2;
    const pad = 3;
    const gap = 2;
    const rx = boxW / 2 - pad - w / 2;
    const ry = boxH / 2 - pad - h / 2;
    const rxUse = Math.max(4, rx);
    const ryUse = Math.max(4, ry);

    const tryAt = (x, y) => {
      const rect = { x: x - w / 2, y: y - h / 2, w, h };
      if (
        rect.x < pad ||
        rect.y < pad ||
        rect.x + rect.w > boxW - pad ||
        rect.y + rect.h > boxH - pad
      ) {
        return null;
      }
      if (occupied.some((o) => overlaps(rect, o, gap))) return null;
      return { x, y, w, h };
    };

    const rings = 96;
    for (let ri = 0; ri <= rings; ri++) {
      const t = ri / rings;
      const steps = Math.max(16, Math.floor(14 + t * 72));
      for (let si = 0; si < steps; si++) {
        const deg = (360 / steps) * si + ri * 0.55;
        const rad = (deg * Math.PI) / 180;
        const x = cx + rxUse * t * Math.cos(rad);
        const y = cy + ryUse * t * Math.sin(rad);
        const hit = tryAt(x, y);
        if (hit) return hit;
      }
    }

    const gridCols = Math.max(8, Math.floor(boxW / 42));
    const gridRows = Math.max(8, Math.floor(boxH / 32));
    for (let row = 0; row < gridRows; row++) {
      for (let col = 0; col < gridCols; col++) {
        const jitter = ((col * 7 + row * 11) % 5) - 2;
        const x = pad + w / 2 + ((boxW - pad * 2 - w) * (col + 0.5)) / gridCols + jitter;
        const y = pad + h / 2 + ((boxH - pad * 2 - h) * (row + 0.5)) / gridRows + jitter;
        const hit = tryAt(x, y);
        if (hit) return hit;
      }
    }

    return null;
  }

  function layoutAuthorTags(items, boxW, boxH) {
    if (items.length === 0 || boxW < 40 || boxH < 40) return [];
    if (typeof document === 'undefined') return [];

    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    if (!ctx) return [];

    const { min: fontMin, max: fontMax } = fontBounds(boxW, boxH);
    const counts = items.map((i) => i.count);
    const minC = Math.min(...counts);
    const maxC = Math.max(...counts);

    const sorted = [...items].sort((a, b) => b.count - a.count);
    const occupied = [];
    const placed = [];

    sorted.forEach((item, index) => {
      let fontPx = scaleFontPx(item.count, minC, maxC, fontMin, fontMax);
      const weight = fontPx >= fontMax * 0.62 ? 600 : 500;
      const color = PALETTE[Math.min(index, PALETTE.length - 1)];
      let vertical = preferVertical(item.key, index);

      for (let attempt = 0; attempt < 6; attempt++) {
        const pos = placeOne(ctx, item.label, fontPx, weight, vertical, boxW, boxH, occupied);
        if (pos) {
          const rect = {
            x: pos.x - pos.w / 2,
            y: pos.y - pos.h / 2,
            w: pos.w,
            h: pos.h,
          };
          occupied.push(rect);
          placed.push({
            key: item.key,
            label: item.label,
            count: item.count,
            x: pos.x,
            y: pos.y,
            fontPx,
            vertical,
            color,
            weight,
          });
          return;
        }
        if (attempt === 0) vertical = !vertical;
        else fontPx = Math.max(fontMin, fontPx * 0.86);
      }
    });

    return placed;
  }

  global.PoetryAuthorWallLayout = { layoutAuthorTags };
})(window);