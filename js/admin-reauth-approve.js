(function () {
  function bindApproveDialog() {
    var hidden = document.getElementById('approve_request_id');
    if (!hidden) {
      return;
    }
    document.querySelectorAll('[data-approve-request-id]').forEach(function (button) {
      button.addEventListener('click', function () {
        hidden.value = button.getAttribute('data-approve-request-id') || '';
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bindApproveDialog, { once: true });
  } else {
    bindApproveDialog();
  }
})();
