/**
 * Focus first meaningful control when opening a <dialog>, not the × close button.
 */
function focusDialogInitial(dialog) {
  if (!dialog) return;

  var skipClose =
    'input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled])';
  var firstInput = dialog.querySelector(skipClose);
  if (firstInput) {
    firstInput.focus();
    return;
  }

  var buttons = dialog.querySelectorAll('button:not([disabled])');
  for (var i = 0; i < buttons.length; i++) {
    var btn = buttons[i];
    if (btn.classList.contains('dialog-close')) continue;
    if (btn.hasAttribute('data-dialog-close')) continue;
    btn.focus();
    return;
  }
}

function showAppDialog(dialog) {
  if (!dialog || typeof dialog.showModal !== 'function') return;
  dialog.showModal();
  window.setTimeout(function () {
    focusDialogInitial(dialog);
  }, 0);
}
