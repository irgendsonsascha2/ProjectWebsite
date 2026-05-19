(() => {
  const nav = document.querySelector('.top-nav');
  if (!nav) return;

  const form = nav.querySelector('.nav-search');
  if (!(form instanceof HTMLFormElement)) return;

  const input = form.querySelector('.nav-search-input');
  const toggle = form.querySelector('.nav-search-toggle');
  if (!(input instanceof HTMLInputElement) || !(toggle instanceof HTMLButtonElement)) return;

  function isOpen() {
    return nav.dataset.searchOpen === '1';
  }

  function open() {
    nav.dataset.searchOpen = '1';
    toggle.setAttribute('aria-expanded', 'true');
    // Focus needs to happen after layout update in some browsers.
    window.setTimeout(() => {
      input.focus();
      input.select();
    }, 0);
  }

  function close() {
    delete nav.dataset.searchOpen;
    toggle.setAttribute('aria-expanded', 'false');
  }

  // If we already have a query, show input on load.
  if ((input.value || '').trim() !== '') {
    open();
  }

  toggle.addEventListener('click', () => {
    if (isOpen()) {
      close();
    } else {
      open();
    }
  });

  document.addEventListener('click', (e) => {
    const target = e.target;
    if (!(target instanceof Element)) return;
    const external = target.closest('[data-nav-search-toggle]');
    if (!external) return;
    e.preventDefault();
    if (isOpen()) {
      close();
    } else {
      open();
    }
  });

  input.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      e.preventDefault();
      close();
      toggle.focus();
    }
  });

  document.addEventListener(
    'pointerdown',
    (e) => {
      if (!isOpen()) return;
      const target = e.target;
      if (!(target instanceof Element)) return;
      if (target.closest('nav') === nav) return;
      close();
    },
    { capture: true },
  );

  form.addEventListener('submit', () => {
    // Keep open state stable while navigating.
    open();
  });
})();

