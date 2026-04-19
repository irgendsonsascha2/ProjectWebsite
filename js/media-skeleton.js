(function () {
    function markLoaded(host) {
        host.classList.add('is-loaded');

        var intro = host.closest('.home-intro');
        if (intro) {
            intro.classList.add('home-intro-ready');
        }

        var card = host.closest('[data-skeleton-card]');
        if (card) {
            card.classList.add('is-media-loaded');
        }
    }

    function bindMedia(host) {
        var img = host.querySelector('img');
        var video = host.querySelector('video');

        var done = function () {
            markLoaded(host);
        };

        if (img) {
            if (img.complete && img.naturalWidth > 0) {
                done();
                return;
            }
            img.addEventListener('load', done, { once: true });
            img.addEventListener('error', done, { once: true });
            return;
        }

        if (video) {
            if (video.readyState >= 2) {
                done();
                return;
            }
            video.addEventListener('loadeddata', done, { once: true });
            video.addEventListener('error', done, { once: true });
            return;
        }

        done();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-skeleton-media]').forEach(bindMedia);
    });
})();
