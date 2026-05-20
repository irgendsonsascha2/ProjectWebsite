(function () {
    if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        return;
    }

    var activePreviewVideo = null;

    function isFormField(target) {
        if (!target || !(target instanceof Element)) {
            return false;
        }
        var tag = target.tagName;
        return (
            tag === 'INPUT'
            || tag === 'TEXTAREA'
            || tag === 'SELECT'
            || target.isContentEditable
        );
    }

    function isSpaceKey(e) {
        return e.key === ' ' || e.code === 'Space';
    }

    function blockPreviewSpaceDefault(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    function isLightboxOpen() {
        var lb = document.getElementById('lightbox');
        return !!(lb && lb.classList.contains('is-open'));
    }

    function handlePreviewSpaceKeydown(e) {
        if (isLightboxOpen()) {
            return;
        }
        if (!activePreviewVideo) {
            return;
        }
        if (!isSpaceKey(e)) {
            return;
        }
        if (isFormField(e.target)) {
            return;
        }
        blockPreviewSpaceDefault(e);
        if (activePreviewVideo.paused) {
            var playPromise = activePreviewVideo.play();
            if (playPromise && typeof playPromise.catch === 'function') {
                playPromise.catch(function () {});
            }
        } else {
            activePreviewVideo.pause();
        }
    }

    function handlePreviewSpaceKeyup(e) {
        if (isLightboxOpen()) {
            return;
        }
        if (!activePreviewVideo) {
            return;
        }
        if (!isSpaceKey(e)) {
            return;
        }
        if (isFormField(e.target)) {
            return;
        }
        blockPreviewSpaceDefault(e);
    }

    function bindVideoHoverCard(link) {
        var video = link.querySelector('video');
        if (!video) {
            return;
        }
        var thumbWrap = link.querySelector('.thumb-wrap');

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
            if (thumbWrap) {
                thumbWrap.classList.add('is-video-previewing');
            }
            activePreviewVideo = video;
        }

        function stopPreview() {
            video.pause();
            try {
                video.currentTime = 0;
            } catch (e) {
                /* ignore */
            }
            if (thumbWrap) {
                thumbWrap.classList.remove('is-video-previewing');
            }
            if (activePreviewVideo === video) {
                activePreviewVideo = null;
            }
        }

        function blockLinkSpaceActivation(e) {
            if (activePreviewVideo !== video) {
                return;
            }
            if (!isSpaceKey(e)) {
                return;
            }
            e.preventDefault();
            e.stopImmediatePropagation();
        }

        link.addEventListener('mouseenter', playPreview);
        link.addEventListener('mouseleave', stopPreview);
        link.addEventListener('keydown', blockLinkSpaceActivation, true);
        link.addEventListener('keyup', blockLinkSpaceActivation, true);
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

    document.addEventListener('keydown', handlePreviewSpaceKeydown, true);
    document.addEventListener('keyup', handlePreviewSpaceKeyup, true);

    function init() {
        document.querySelectorAll('.page-project_grid .project-card-link').forEach(function (link) {
            if (link.querySelector('video')) {
                bindVideoHoverCard(link);
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
