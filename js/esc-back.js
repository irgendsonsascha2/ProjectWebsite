(() => {
  function isProjectDetailPage() {
    // Router rendert Seiten in <main class="... page-project_detail">
    const main = document.querySelector('main.page-project_detail');
    if (main) return true;
    // Fallback: direkte Einbindung / URL-Pattern
    return /[?&]page=project_detail(?:&|$)/.test(window.location.search);
  }

  function isEscapeEvent(e) {
    const key = e && (e.key || e.code) ? (e.key || e.code) : '';
    return key === 'Escape' || key === 'Esc' || e.keyCode === 27;
  }

  function closeLightboxIfOpen() {
    const lb = document.getElementById('lightbox');
    if (!lb || !lb.classList || !lb.classList.contains('is-open')) return false;
    lb.classList.remove('is-open');
    lb.setAttribute('aria-hidden', 'true');
    return true;
  }

  function onKey(e) {
    if (!isProjectDetailPage()) return;
    if (!isEscapeEvent(e)) return;

    // Firefox/Safari: ESC muss immer greifen, selbst wenn andere Listener existieren.
    if (typeof e.preventDefault === 'function') e.preventDefault();
    if (typeof e.stopPropagation === 'function') e.stopPropagation();
    if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();

    if (closeLightboxIfOpen()) return;
    window.location.href = 'index.php?page=project_grid';
  }

  // Capture + bubbling, keydown + keyup: maximal robust.
  window.addEventListener('keydown', onKey, true);
  window.addEventListener('keydown', onKey, false);
  window.addEventListener('keyup', onKey, true);
  window.addEventListener('keyup', onKey, false);
})();

