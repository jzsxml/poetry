(function (g) {
  function svg(size, body) {
    return (
      `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" `
      + `stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${body}</svg>`
    );
  }

  g.PoetryIcons = {
    chevronLeft: svg(14, '<path d="M15 6 9 12l6 6" />'),
    chevronRight: svg(14, '<path d="M9 6l6 6-6 6" />'),
  };
})(window);