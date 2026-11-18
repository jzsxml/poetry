(function () {
  const COOKIE = 'poetry-script';

  function isDaytime(hour) {
    return hour >= 6 && hour < 18;
  }

  function refreshScenePhase() {
    const phase = isDaytime(new Date().getHours()) ? 'day' : 'night';
    document.querySelectorAll('.site-top-petals, .site-top-scene').forEach((el) => {
      el.classList.remove('site-top-petals--day', 'site-top-petals--night', 'site-top-scene--day', 'site-top-scene--night');
      if (el.classList.contains('site-top-petals')) {
        el.classList.add(`site-top-petals--${phase}`);
      } else {
        el.classList.add(`site-top-scene--${phase}`);
      }
    });
  }

  refreshScenePhase();
  window.setInterval(refreshScenePhase, 60_000);

  const backToTop = document.getElementById('back-to-top');
  const SHOW_AFTER = 320;
  function refreshBackToTop() {
    if (!backToTop) return;
    const show = window.scrollY > SHOW_AFTER;
    backToTop.hidden = !show;
  }
  if (backToTop) {
    backToTop.addEventListener('click', () => {
      const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
    });
    window.addEventListener('scroll', refreshBackToTop, { passive: true });
    refreshBackToTop();
  }

  document.querySelectorAll('.script-toggle__btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      const v = btn.getAttribute('data-script');
      if (v !== 'simp' && v !== 'trad') return;
      const secure = window.location.protocol === 'https:' ? ';Secure' : '';
      document.cookie = `${COOKIE}=${v};path=/;max-age=31536000;SameSite=Lax${secure}`;
      location.reload();
    });
  });
})();
