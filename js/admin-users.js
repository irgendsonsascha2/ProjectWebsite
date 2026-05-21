(function () {
  document.querySelectorAll('.moderation-form').forEach(function (form) {
    var statusRadios = form.querySelectorAll('.js-moderation-status');
    var timeoutBlock = form.querySelector('.js-timeout-fields');
    var toggleTimeout = function () {
      if (!timeoutBlock) return;
      var selected = form.querySelector('.js-moderation-status:checked');
      timeoutBlock.style.display = selected && selected.value === 'suspended' ? '' : 'none';
    };
    statusRadios.forEach(function (radio) {
      radio.addEventListener('change', toggleTimeout);
    });
    toggleTimeout();

    var reasonSel = form.querySelector('.js-reason-key');
    if (!reasonSel) return;
    var customLabels = form.querySelectorAll('.js-custom-reason');
    var customInput = form.querySelector('.js-custom-reason-input');
    var toggleCustom = function () {
      var show = reasonSel.value === 'custom';
      customLabels.forEach(function (el) {
        el.style.display = show ? '' : 'none';
      });
      if (customInput) customInput.style.display = show ? '' : 'none';
    };
    reasonSel.addEventListener('change', toggleCustom);
    toggleCustom();
  });
})();
