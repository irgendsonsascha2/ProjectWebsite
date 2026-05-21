(() => {
  const dialog = document.getElementById('request-code-dialog');
  const openBtn = document.getElementById('open-request-code-dialog');
  if (!dialog || !openBtn) return;

  function openDialog() {
    if (typeof dialog.showModal === 'function') {
      dialog.showModal();
    } else {
      window.location.href = 'index.php?page=register#request-code';
    }
  }

  function closeDialog() {
    if (typeof dialog.close === 'function') {
      dialog.close();
    }
  }

  openBtn.addEventListener('click', openDialog);
  dialog.addEventListener('click', (e) => {
    if (e.target === dialog) closeDialog();
  });
  dialog.querySelectorAll('[data-dialog-close]').forEach((btn) => {
    btn.addEventListener('click', closeDialog);
  });

  if (dialog.dataset.autoOpen === '1') {
    openDialog();
  }
})();
