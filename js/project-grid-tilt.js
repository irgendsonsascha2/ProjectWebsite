(function () {
  const cards = Array.from(document.querySelectorAll('.project-card'));
  if (cards.length === 0) return;

  cards.forEach((card) => {
    let rafId = 0;
    let lastEvent = null;

    function applyTilt() {
      rafId = 0;
      if (!lastEvent) return;
      const rect = card.getBoundingClientRect();
      const x = Math.min(Math.max((lastEvent.clientX - rect.left) / rect.width, 0), 1);
      const y = Math.min(Math.max((lastEvent.clientY - rect.top) / rect.height, 0), 1);
      const rx = (0.5 - y) * 10;
      const ry = (x - 0.5) * 12;

      card.style.setProperty('--rx', `${rx}deg`);
      card.style.setProperty('--ry', `${ry}deg`);
      card.style.setProperty('--mx', `${x * 100}%`);
      card.style.setProperty('--my', `${y * 100}%`);
      card.classList.add('is-tilting');
    }

    card.addEventListener('pointermove', (event) => {
      if (event.pointerType === 'touch') return;
      lastEvent = event;
      if (!rafId) {
        rafId = window.requestAnimationFrame(applyTilt);
      }
    });

    card.addEventListener('pointerleave', () => {
      if (rafId) {
        window.cancelAnimationFrame(rafId);
        rafId = 0;
      }
      lastEvent = null;
      card.classList.remove('is-tilting');
      card.style.removeProperty('--rx');
      card.style.removeProperty('--ry');
      card.style.removeProperty('--mx');
      card.style.removeProperty('--my');
    });
  });
})();
