(function () {
    var token = window.PORTFOLIO_CSRF;
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

    document.querySelectorAll('form').forEach(ensureFormToken);

    var originalFetch = window.fetch;
    if (typeof originalFetch !== 'function') {
        return;
    }

    window.fetch = function (input, init) {
        init = init || {};
        var method = (init.method || 'GET').toUpperCase();
        if (method === 'POST' && init.body instanceof FormData && !init.body.has('_token')) {
            init.body.set('_token', token);
        }
        return originalFetch.call(this, input, init);
    };
})();
