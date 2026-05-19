(function () {
    if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        return;
    }

    function bindVideoHoverFrame(frame) {
        var video = frame.querySelector('video');
        if (!video) {
            return;
        }

        function playPreview() {
            try {
                video.currentTime = 0;
            } catch (e) {
                /* ignore */
            }
            var playPromise = video.play();
            if (playPromise && typeof playPromise.catch === 'function') {
                playPromise.catch(function () {});
            }
            frame.classList.add('is-video-previewing');
        }

        function stopPreview() {
            video.pause();
            try {
                video.currentTime = 0;
            } catch (e) {
                /* ignore */
            }
            frame.classList.remove('is-video-previewing');
        }

        frame.addEventListener('pointerenter', playPreview);
        frame.addEventListener('pointerleave', stopPreview);
        frame.addEventListener('blur', stopPreview, true);
    }

    function init() {
        document.querySelectorAll('.page-project_grid .thumb-wrap').forEach(function (frame) {
            if (frame.querySelector('video')) {
                bindVideoHoverFrame(frame);
            }
        });
        document.querySelectorAll('.page-project_detail .media-item').forEach(function (frame) {
            if (frame.querySelector('video')) {
                bindVideoHoverFrame(frame);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
