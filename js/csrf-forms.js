(function () {
  function readCsrfToken() {
    if (window.PORTFOLIO_CSRF) {
      return window.PORTFOLIO_CSRF;
    }
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) {
      var fromMeta = meta.getAttribute('content') || '';
      if (fromMeta) {
        window.PORTFOLIO_CSRF = fromMeta;
        return fromMeta;
      }
    }
    return '';
  }

  var token = readCsrfToken();
  if (!token) {
    return;
  }

  function ensureFormToken(form) {
    if (!(form instanceof HTMLFormElement)) {
      return;
    }
    var method = (form.getAttribute('method') || 'get').toLowerCase();
    if (method !== 'post') {
      return;
    }
    if (form.querySelector('input[name="_token"]')) {
      return;
    }
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = '_token';
    input.value = token;
    form.prepend(input);
  }

  function initCsrfForms() {
    document.querySelectorAll('form').forEach(ensureFormToken);
  }

  function initConfirmForms() {
    document.querySelectorAll('form[data-confirm-submit]').forEach(function (form) {
      var message = form.getAttribute('data-confirm-submit') || '';
      if (!message) {
        return;
      }
      form.addEventListener('submit', function (event) {
        if (!window.confirm(message)) {
          event.preventDefault();
        }
      });
    });
  }

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (form instanceof HTMLFormElement) {
      ensureFormToken(form);
    }
  }, true);

  var originalFetch = window.fetch;
  if (typeof originalFetch === 'function') {
    window.fetch = function (input, init) {
      init = init || {};
      var method = (init.method || 'GET').toUpperCase();
      if (method === 'POST' && init.body instanceof FormData && !init.body.has('_token')) {
        init.body.set('_token', token);
      }
      return originalFetch.call(this, input, init);
    };
  }

  function initForms() {
    initCsrfForms();
    initConfirmForms();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initForms, { once: true });
  } else {
    initForms();
  }
})();
