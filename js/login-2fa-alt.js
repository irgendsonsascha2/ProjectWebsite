(function () {
  var toggle = document.getElementById('login-2fa-alt-toggle');
  var panel = document.getElementById('login-2fa-alt-panel');
  if (!toggle || !panel) return;

  toggle.addEventListener('click', function (e) {
    e.preventDefault();
    var open = panel.hidden;
    panel.hidden = !open;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) {
      var backup = document.getElementById('backup_code');
      if (backup) backup.focus();
    }
  });

  if (panel.dataset.autoOpen === '1') {
    panel.hidden = false;
    toggle.setAttribute('aria-expanded', 'true');
  }
})();
