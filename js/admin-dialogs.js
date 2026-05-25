function adminDialogsInit() {
  var openButtons = Array.from(document.querySelectorAll('[data-dialog-open]'));
  var closeButtons = Array.from(document.querySelectorAll('[data-dialog-close]'));
  var dialogs = Array.from(document.querySelectorAll('dialog'));

  openButtons.forEach(function (button) {
    var dialogId = button.getAttribute('data-dialog-open');
    var dialog = dialogId ? document.getElementById(dialogId) : null;
    if (!dialog || typeof dialog.showModal !== 'function') return;
    button.addEventListener('click', function () {
      showAppDialog(dialog);
    });
  });

  closeButtons.forEach(function (button) {
    button.addEventListener('click', function () {
      var dialog = button.closest('dialog');
      if (dialog) dialog.close();
    });
  });

  dialogs.forEach(function (dialog) {
    dialog.addEventListener('click', function (event) {
      if (event.target === dialog) dialog.close();
    });
  });

  Array.from(document.querySelectorAll('dialog form[method="POST"][data-dialog-close-on-submit]')).forEach(
    function (form) {
      form.addEventListener('submit', function () {
        var dialog = form.closest('dialog');
        if (dialog) dialog.close();
      });
    },
  );

}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', adminDialogsInit, { once: true });
} else {
  adminDialogsInit();
}
