(() => {
    // ESC muss immer funktionieren (Firefox/Safari-kompatibel), auch wenn später ein JS-Teil scheitert.
    window.addEventListener('keydown', function (e) {
        var key = e && (e.key || e.code) ? (e.key || e.code) : '';
        var isEsc = key === 'Escape' || key === 'Esc' || e.keyCode === 27;
        if (!isEsc) return;
        var lb = document.getElementById('lightbox');
        if (lb && lb.classList.contains('is-open')) {
            // Immer zentrale Close-Routine nutzen, damit Scroll-Lock sauber gelöst wird.
            if (typeof closeLightbox === 'function') {
                closeLightbox();
            } else {
                lb.classList.remove('is-open');
                lb.setAttribute('aria-hidden', 'true');
                document.body.style.overflow = '';
                document.body.style.position = '';
                document.body.style.top = '';
                document.body.style.width = '';
                document.body.style.paddingRight = '';
            }
            return;
        }
        window.location.href = 'index.php?page=project_grid';
    }, true);

    const deleteForm = document.getElementById('delete-project-form');
    if (deleteForm) {
        deleteForm.addEventListener('submit', function (event) {
            const ok = window.confirm('Dieses Projekt wirklich löschen?');
            if (!ok) {
                event.preventDefault();
            }
        });
    }
    document.addEventListener('keydown', (event) => {
        const target = event.target;
        if (!target || !target.tagName || target.tagName.toUpperCase() !== 'TEXTAREA') return;
        if (!target.closest || !target.closest('.comment-form')) return;
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            const form = target.closest('form');
            if (!form) return;
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        }
    });

    const lightbox = document.getElementById('lightbox');
    const lightboxMedia = lightbox ? lightbox.querySelector('.lightbox-media') : null;
    const lightboxPanel = lightbox ? lightbox.querySelector('.lightbox-panel') : null;
    const closeBtn = lightbox ? lightbox.querySelector('.lightbox-close') : null;
    const statusEl = document.getElementById('interaction-status');
    const commentItems = document.getElementById('lightbox-comment-items');
    const likeCountEl = lightboxPanel ? lightboxPanel.querySelector('.like-count') : null;
    const dislikeCountEl = lightboxPanel ? lightboxPanel.querySelector('.dislike-count') : null;
    const lightboxInteractionForm = lightbox ? lightbox.querySelector('.lightbox-interaction-form') : null;
    const lightboxCommentForm = lightbox ? lightbox.querySelector('.lightbox-comment-form') : null;
    const body = document.body;
    let bodyOverflow = '';
    let bodyPaddingRight = '';
    let bodyPosition = '';
    let bodyTop = '';
    let bodyWidth = '';
    let scrollYBeforeLock = 0;
    let activeMediaId = null;

    async function submitAjaxForm(form, submitter) {
        const formData = new FormData(form);
        if (submitter && submitter.name) {
            formData.append(submitter.name, submitter.value);
        }
        const actionUrl = form.dataset.ajaxAction || form.action || window.location.href;
        const response = await fetch(actionUrl, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: formData
        });
        if (!response.ok) {
            throw new Error('Serverfehler');
        }
        const contentType = response.headers.get('content-type') || '';
        if (contentType.includes('application/json')) {
            return response.json();
        }
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (error) {
            throw new Error('Ungültige Antwort vom Server');
        }
    }

    function setActiveMediaId(mediaId) {
        activeMediaId = mediaId;
        lightbox.querySelectorAll('input[name="media_id"]').forEach((input) => {
            input.value = mediaId || '';
        });
    }

    function updateMetricCount(mediaId, kind, value) {
        if (!mediaId) return;
        const metric = document.querySelector(`.media-metrics[data-media-id="${mediaId}"] .metric-count[data-kind="${kind}"]`);
        if (metric) {
            metric.textContent = value;
        }
    }

    function updateHoverPreview(mediaId, html) {
        if (!mediaId) return;
        const container = document.querySelector(`.media-hover-comments[data-media-id="${mediaId}"]`);
        if (container) {
            container.innerHTML = html || '';
            setupHoverRotationFor(container);
        }
    }

    async function loadMediaData(mediaId) {
        if (!mediaId) return;
        const formData = new FormData();
        formData.set('ajax', '1');
        formData.set('load_media', '1');
        formData.set('media_id', mediaId);
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: formData
        });
        if (!response.ok) {
            throw new Error('Serverfehler');
        }
        const data = await response.json();
        if (data.ok === false) {
            if (statusEl) statusEl.textContent = data.message || 'Fehler beim Laden.';
            return;
        }
        if (likeCountEl && data.likeCount !== undefined) likeCountEl.textContent = data.likeCount;
        if (dislikeCountEl && data.dislikeCount !== undefined) dislikeCountEl.textContent = data.dislikeCount;
        if (commentItems && typeof data.commentsHtml === 'string') {
            commentItems.innerHTML = data.commentsHtml;
            setupTextareas(commentItems);
            setupCommentExpanders(commentItems);
        }
        if (typeof data.commentLimit === 'number') {
            const note = lightboxPanel ? lightboxPanel.querySelector('.comment-limit-note') : null;
            if (note) {
                note.textContent = data.commentLimitReached && data.commentLimit > 0
                    ? `Kommentar-Limit erreicht (max. ${data.commentLimit} pro Nutzer).`
                    : '';
            }
            if (lightboxCommentForm) {
                const textarea = lightboxCommentForm.querySelector('textarea');
                const button = lightboxCommentForm.querySelector('button[type="submit"]');
                if (textarea) textarea.disabled = !!data.commentLimitReached;
                if (button) button.disabled = !!data.commentLimitReached;
            }
        }
        if (data.currentUserInteraction !== undefined) {
            const likeBtn = lightbox.querySelector('button[name="interaction"][value="like"]');
            const dislikeBtn = lightbox.querySelector('button[name="interaction"][value="dislike"]');
            if (likeBtn) {
                const isActive = data.currentUserInteraction === 'like';
                likeBtn.classList.toggle('is-active', isActive);
                likeBtn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            }
            if (dislikeBtn) {
                const isActive = data.currentUserInteraction === 'dislike';
                dislikeBtn.classList.toggle('is-active', isActive);
                dislikeBtn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            }
        }
        if (data.commentCount !== undefined) {
            updateMetricCount(mediaId, 'comment', data.commentCount);
        }
    }

    let lastSubmitter = null;
    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;
        const button = target.closest('button[type="submit"], input[type="submit"]');
        if (!button) return;
        lastSubmitter = button;
    });

    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (!form.dataset.ajax) return;
        event.preventDefault();
            if (statusEl) statusEl.textContent = 'Speichern...';
        try {
            const submitter = event.submitter || (lastSubmitter && form.contains(lastSubmitter) ? lastSubmitter : null);
            const data = await submitAjaxForm(form, submitter);
            if (data.ok === false) {
                if (statusEl) statusEl.textContent = data.message || 'Fehler beim Speichern.';
                return;
            }
            if (data.action === 'interaction') {
                const mediaId = data.mediaId || activeMediaId;
                if (likeCountEl) likeCountEl.textContent = data.likeCount ?? likeCountEl.textContent;
                if (dislikeCountEl) dislikeCountEl.textContent = data.dislikeCount ?? dislikeCountEl.textContent;
                if (data.currentUserInteraction !== undefined) {
                    const likeBtn = lightbox.querySelector('button[name="interaction"][value="like"]');
                    const dislikeBtn = lightbox.querySelector('button[name="interaction"][value="dislike"]');
                    if (likeBtn) {
                        const isActive = data.currentUserInteraction === 'like';
                        likeBtn.classList.toggle('is-active', isActive);
                        likeBtn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                    }
                    if (dislikeBtn) {
                        const isActive = data.currentUserInteraction === 'dislike';
                        dislikeBtn.classList.toggle('is-active', isActive);
                        dislikeBtn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                    }
                }
                if (mediaId) {
                    if (data.likeCount !== undefined) updateMetricCount(mediaId, 'like', data.likeCount);
                    if (data.dislikeCount !== undefined) updateMetricCount(mediaId, 'dislike', data.dislikeCount);
                }
                if (statusEl) statusEl.textContent = '';
            } else if (data.action === 'comment') {
                if (commentItems && typeof data.commentsHtml === 'string') {
                    commentItems.innerHTML = data.commentsHtml;
                    setupTextareas(commentItems);
                    setupCommentExpanders(commentItems);
                }
                if (typeof data.commentLimit === 'number') {
                    const note = lightboxPanel ? lightboxPanel.querySelector('.comment-limit-note') : null;
                    if (note) {
                        note.textContent = data.commentLimitReached && data.commentLimit > 0
                            ? `Kommentar-Limit erreicht (max. ${data.commentLimit} pro Nutzer).`
                            : '';
                    }
                }
            if (data.commentLimitReached !== undefined) {
                    const mainForm = lightboxCommentForm;
                    if (mainForm) {
                        const textarea = mainForm.querySelector('textarea');
                        const button = mainForm.querySelector('button[type="submit"]');
                        if (textarea) textarea.disabled = data.commentLimitReached;
                        if (button) button.disabled = data.commentLimitReached;
                    }
                    if (commentItems) {
                        commentItems.querySelectorAll('.reply-form textarea').forEach((el) => {
                            el.disabled = data.commentLimitReached;
                        });
                        commentItems.querySelectorAll('.reply-form button[type="submit"]').forEach((el) => {
                            el.disabled = data.commentLimitReached;
                        });
                    }
                    if (data.commentLimit !== undefined) {
                        const note = lightboxPanel ? lightboxPanel.querySelector('.comment-limit-note') : null;
                        if (note) {
                            note.textContent = data.commentLimitReached && data.commentLimit > 0
                                ? `Kommentar-Limit erreicht (max. ${data.commentLimit} pro Nutzer).`
                                : '';
                        }
                    }
                }
                if (form) {
                    const textarea = form.querySelector('textarea');
                    if (textarea) {
                        textarea.value = '';
                        autoGrowTextarea(textarea);
                    }
                }
                if (data.commentCount !== undefined) {
                    const mediaId = data.mediaId || activeMediaId;
                    if (mediaId) updateMetricCount(mediaId, 'comment', data.commentCount);
                }
                if (data.hoverHtml !== undefined) {
                    const mediaId = data.mediaId || activeMediaId;
                    if (mediaId) updateHoverPreview(mediaId, data.hoverHtml);
                }
                if (statusEl) statusEl.textContent = data.message || 'Kommentar gespeichert.';
            }
        } catch (error) {
            if (statusEl) statusEl.textContent = 'Fehler beim Speichern.';
        }
    });

    function autoGrowTextarea(textarea) {
        textarea.style.height = 'auto';
        const cs = getComputedStyle(textarea);
        const minH = parseFloat(cs.minHeight) || 0;
        const maxHPx = parseFloat(cs.maxHeight);
        const cap = Number.isFinite(maxHPx) && maxHPx > 0 ? maxHPx : Number.POSITIVE_INFINITY;
        const next = Math.min(Math.max(textarea.scrollHeight, minH), cap);
        textarea.style.height = `${next}px`;
    }

    function setupTextareas(root) {
        const textareas = root.querySelectorAll('.comment-form textarea');
        textareas.forEach((textarea) => {
            if (textarea.dataset.enhanced === '1') return;
            textarea.dataset.enhanced = '1';
            autoGrowTextarea(textarea);
            textarea.addEventListener('input', (event) => {
                const max = parseInt(textarea.dataset.maxlength || '400', 10);
                if (textarea.value.length >= max && event.inputType && event.inputType.startsWith('insert')) {
                    if (statusEl) statusEl.textContent = 'Keine Romane Schreiben bitte';
                } else if (statusEl && statusEl.textContent === 'Keine Romane Schreiben bitte') {
                    statusEl.textContent = '';
                }
                autoGrowTextarea(textarea);
            });

            textarea.addEventListener('paste', (event) => {
                const max = parseInt(textarea.dataset.maxlength || '400', 10);
                const text = (event.clipboardData || window.clipboardData).getData('text');
                const selection = textarea.selectionEnd - textarea.selectionStart;
                const available = max - (textarea.value.length - selection);
                if (text.length > available) {
                    event.preventDefault();
                    const insert = text.slice(0, Math.max(0, available));
                    const start = textarea.selectionStart;
                    const end = textarea.selectionEnd;
                    textarea.setRangeText(insert, start, end, 'end');
                    if (statusEl) statusEl.textContent = 'Keine Romane Schreiben bitte';
                    autoGrowTextarea(textarea);
                }
            });
        });
    }

    function setupCommentExpanders(root) {
        const texts = root.querySelectorAll('.comment-text');
        texts.forEach((textEl) => {
            if (textEl.dataset.clampReady === '1') return;
            textEl.dataset.clampReady = '1';

            textEl.classList.add('is-collapsed');

            const needsClamp = textEl.scrollHeight > textEl.clientHeight + 1;
            if (!needsClamp) {
                textEl.classList.remove('is-collapsed');
                return;
            }

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'comment-expand';
            btn.textContent = 'Mehr anzeigen';
            btn.addEventListener('click', () => {
                const isCollapsed = textEl.classList.contains('is-collapsed');
                if (isCollapsed) {
                    textEl.classList.remove('is-collapsed');
                    btn.textContent = 'Weniger';
                } else {
                    textEl.classList.add('is-collapsed');
                    btn.textContent = 'Mehr anzeigen';
                }
            });

            textEl.insertAdjacentElement('afterend', btn);
        });
    }

    setupTextareas(document);
    setupCommentExpanders(document);

    function setupHoverRotationFor(container) {
        if (!container) return;
        const comments = Array.from(container.querySelectorAll('.hover-comment'));
        if (comments.length === 0) return;
        comments.forEach((item) => {
            item.classList.remove('is-active');
            item.classList.remove('is-leaving');
        });
        comments[0].classList.add('is-active');
        container.dataset.hoverIndex = '0';
    }

    function rotateHoverComment(container) {
        const comments = Array.from(container.querySelectorAll('.hover-comment'));
        if (comments.length <= 1) return false;
        const currentIndex = parseInt(container.dataset.hoverIndex || '0', 10) || 0;
        const nextIndex = (currentIndex + 1) % comments.length;
        const current = comments[currentIndex];
        if (!current) return false;
        const next = comments[nextIndex];
        if (!next || current === next) return true;
        current.classList.remove('is-active');
        current.classList.add('is-leaving');
        next.classList.remove('is-leaving');
        next.classList.add('is-active');
        window.setTimeout(() => {
            current.classList.remove('is-leaving');
        }, 260);
        container.dataset.hoverIndex = String(nextIndex);
        return true;
    }

    document.querySelectorAll('.media-hover-comments').forEach((container) => {
        setupHoverRotationFor(container);
        const card = container.closest('.media-item');
        if (!card) return;
        let intervalId = null;
        card.addEventListener('pointerenter', (event) => {
            if (event.pointerType === 'touch') return;
            if (intervalId) return;
            setupHoverRotationFor(container);
            intervalId = window.setInterval(() => {
                const keepGoing = rotateHoverComment(container);
                if (!keepGoing && intervalId) {
                    window.clearInterval(intervalId);
                    intervalId = null;
                }
            }, 2500);
        });
        card.addEventListener('pointerleave', (event) => {
            if (event.pointerType === 'touch') return;
            if (intervalId) {
                window.clearInterval(intervalId);
                intervalId = null;
            }
            const comments = Array.from(container.querySelectorAll('.hover-comment'));
            comments.forEach((item) => {
                item.classList.remove('is-active');
                item.classList.remove('is-leaving');
            });
            container.dataset.hoverIndex = '-1';
        });
    });

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;
        const toggle = target.closest('.reply-toggle');
        if (!toggle) return;
        const comment = toggle.closest('.comment');
        if (!comment) return;
        const form = comment.querySelector('.reply-form');
        if (!form) return;
        form.classList.toggle('is-open');
        if (form.classList.contains('is-open')) {
            const textarea = form.querySelector('textarea');
            if (textarea) {
                textarea.focus();
                autoGrowTextarea(textarea);
            }
        }
    });

    function resetLightboxState() {
        if (likeCountEl) likeCountEl.textContent = '0';
        if (dislikeCountEl) dislikeCountEl.textContent = '0';
        if (commentItems) commentItems.innerHTML = '';
        const note = lightboxPanel ? lightboxPanel.querySelector('.comment-limit-note') : null;
        if (note) note.textContent = '';
        const likeBtn = lightbox.querySelector('button[name="interaction"][value="like"]');
        const dislikeBtn = lightbox.querySelector('button[name="interaction"][value="dislike"]');
        if (likeBtn) likeBtn.classList.remove('is-active');
        if (dislikeBtn) dislikeBtn.classList.remove('is-active');
    }

    function lockBodyScroll() {
        if (body.dataset.scrollLock === '1') return;
        bodyOverflow = body.style.overflow;
        bodyPaddingRight = body.style.paddingRight;
        bodyPosition = body.style.position;
        bodyTop = body.style.top;
        bodyWidth = body.style.width;
        scrollYBeforeLock = window.scrollY || window.pageYOffset || 0;
        const scrollBarWidth = window.innerWidth - document.documentElement.clientWidth;
        // Robust (Mobile Safari): body fixieren statt nur overflow hidden
        body.style.position = 'fixed';
        body.style.top = `-${scrollYBeforeLock}px`;
        body.style.width = '100%';
        body.style.overflow = 'hidden';
        if (scrollBarWidth > 0) {
            body.style.paddingRight = `${scrollBarWidth}px`;
        }
        body.dataset.scrollLock = '1';
    }

    function unlockBodyScroll() {
        if (body.dataset.scrollLock !== '1') return;
        body.style.overflow = bodyOverflow;
        body.style.paddingRight = bodyPaddingRight;
        body.style.position = bodyPosition;
        body.style.top = bodyTop;
        body.style.width = bodyWidth;
        window.scrollTo(0, scrollYBeforeLock || 0);
        delete body.dataset.scrollLock;
    }

    function getLightboxVideo() {
        return lightboxMedia ? lightboxMedia.querySelector('video') : null;
    }

    function toggleLightboxMute() {
        const video = getLightboxVideo();
        if (!video) return;
        video.muted = !video.muted;
    }

    function isSpaceKey(e) {
        return e.key === ' ' || e.code === 'Space';
    }

    function isLightboxOpen() {
        return lightbox && lightbox.classList.contains('is-open');
    }

    function toggleLightboxPlayPause() {
        const video = getLightboxVideo();
        if (!video) return;
        if (video.paused) {
            const playPromise = video.play();
            if (playPromise && typeof playPromise.catch === 'function') {
                playPromise.catch(() => {});
            }
        } else {
            video.pause();
        }
    }

    function stopGalleryTilePreview() {
        document.querySelectorAll('.page-project_detail .media-item.is-video-previewing').forEach((el) => {
            el.classList.remove('is-video-previewing');
            const tileVideo = el.querySelector('video');
            if (!tileVideo) return;
            tileVideo.pause();
            try {
                tileVideo.currentTime = 0;
            } catch (e) {
                /* ignore */
            }
        });
    }

    function blurLightboxTriggerFocus() {
        const active = document.activeElement;
        if (active instanceof HTMLElement && active.closest('.media-item')) {
            active.blur();
        }
    }

    function shouldHandleLightboxSpace(e) {
        const target = e.target;
        const isFormField = target instanceof Element
            && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT' || target.isContentEditable);
        return isLightboxOpen() && !isFormField && isSpaceKey(e) && !!getLightboxVideo();
    }

    function handleLightboxSpaceKeydown(e) {
        if (!shouldHandleLightboxSpace(e)) return;
        e.preventDefault();
        e.stopPropagation();
        toggleLightboxPlayPause();
    }

    function handleLightboxSpaceKeyup(e) {
        if (!shouldHandleLightboxSpace(e)) return;
        e.preventDefault();
        e.stopPropagation();
    }

    function openLightbox(type, src, mediaId) {
        if (!lightbox || !lightboxMedia) {
            return;
        }
        lightboxMedia.innerHTML = '';
        if (type === 'video') {
            const video = document.createElement('video');
            video.disablePictureInPicture = true;
            video.src = src;
            video.controls = true;
            video.autoplay = true;
            video.playsInline = true;
            video.muted = true;
            lightboxMedia.appendChild(video);
        } else {
            const img = document.createElement('img');
            img.src = src;
            img.alt = 'Bild';
            lightboxMedia.appendChild(img);
        }
        setActiveMediaId(mediaId || '');
        resetLightboxState();
        lightbox.classList.add('is-open');
        lightbox.setAttribute('aria-hidden', 'false');
        lockBodyScroll();
        stopGalleryTilePreview();
        blurLightboxTriggerFocus();
        if (mediaId) {
            loadMediaData(mediaId).catch(() => {});
        }
    }

    function closeLightbox() {
        if (!lightbox || !lightboxMedia) {
            return;
        }
        lightbox.classList.remove('is-open');
        lightbox.setAttribute('aria-hidden', 'true');
        lightboxMedia.innerHTML = '';
        setActiveMediaId('');
        resetLightboxState();
        unlockBodyScroll();
    }

    function redirectToLogin() {
        const next = window.location.href;
        window.location.href = `index.php?page=login&err=forbidden&next=${encodeURIComponent(next)}`;
    }

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;
        const locked = target.closest('[data-auth-redirect]');
        if (!locked) return;
        event.preventDefault();
        redirectToLogin();
    }, true);

    document.addEventListener('focusin', (event) => {
        const target = event.target;
        if (!(target instanceof Element)) return;
        if (!target.closest('[data-auth-redirect]')) return;
        redirectToLogin();
    }, true);

    document.querySelectorAll('.media-item[data-src]').forEach((item) => {
        item.addEventListener('click', () => {
            openLightbox(item.dataset.type, item.dataset.src, item.dataset.mediaId || '');
        });
    });

    document.querySelectorAll('.media-card').forEach((card) => {
        if (!card.querySelector('.media-item[data-src]')) return;

        let rafId = 0;
        let lastEvent = null;

        function applyTilt() {
            rafId = 0;
            if (!lastEvent) return;
            const rect = card.getBoundingClientRect();
            const x = Math.min(Math.max((lastEvent.clientX - rect.left) / rect.width, 0), 1);
            const y = Math.min(Math.max((lastEvent.clientY - rect.top) / rect.height, 0), 1);
            const rx = (y - 0.5) * 18;
            const ry = (0.5 - x) * 20;

            card.style.setProperty('--rx', `${rx}deg`);
            card.style.setProperty('--ry', `${ry}deg`);
            card.style.setProperty('--mx', `${x * 100}%`);
            card.style.setProperty('--my', `${y * 100}%`);
            card.classList.add('is-tilting');
        }

        card.addEventListener('pointermove', (event) => {
            if (event.pointerType === 'touch') return;
            lastEvent = event;
            if (!rafId) {
                rafId = window.requestAnimationFrame(applyTilt);
            }
        });

        card.addEventListener('pointerleave', () => {
            if (rafId) {
                window.cancelAnimationFrame(rafId);
                rafId = 0;
            }
            lastEvent = null;
            card.classList.remove('is-tilting');
            card.style.removeProperty('--rx');
            card.style.removeProperty('--ry');
            card.style.removeProperty('--mx');
            card.style.removeProperty('--my');
        });
    });

    function getLightboxItems() {
        return Array.from(document.querySelectorAll('.media-item[data-src]'));
    }

    function getActiveIndex(items) {
        if (!items.length) return -1;
        if (activeMediaId) {
            const idIndex = items.findIndex((el) => (el.dataset.mediaId || '') === activeMediaId);
            if (idIndex >= 0) return idIndex;
        }
        const current = lightboxMedia.querySelector('img, video');
        if (current) {
            const src = current.getAttribute('src') || '';
            const srcIndex = items.findIndex((el) => (el.dataset.src || '') === src);
            if (srcIndex >= 0) return srcIndex;
        }
        return -1;
    }

    function navigateLightbox(delta) {
        const items = getLightboxItems();
        if (!items.length) return;
        const currentIndex = getActiveIndex(items);
        if (currentIndex < 0) return;
        const nextIndex = currentIndex + delta;
        if (nextIndex < 0 || nextIndex >= items.length) return;
        const item = items[nextIndex];
        openLightbox(item.dataset.type, item.dataset.src, item.dataset.mediaId || '');
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closeLightbox);
    }
    if (lightboxMedia) {
        lightboxMedia.addEventListener('click', (e) => {
            const target = e.target;
            if (!(target instanceof Element)) return;
            if (target.tagName === 'IMG') {
                closeLightbox();
            }
        });
    }
    if (lightbox) {
        lightbox.addEventListener('click', (e) => {
            if (e.target === lightbox) {
                closeLightbox();
            }
        });
    }

    document.addEventListener('keydown', handleLightboxSpaceKeydown, true);
    document.addEventListener('keyup', handleLightboxSpaceKeyup, true);

    document.addEventListener('keydown', (e) => {
        const target = e.target;
        const isFormField = target instanceof Element
            && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.tagName === 'SELECT' || target.isContentEditable);
        if (e.key === 'Escape') {
            if (lightbox && lightbox.classList.contains('is-open')) {
                closeLightbox();
            } else {
                window.location.href = 'index.php?page=project_grid';
            }
            return;
        }
        if (
            lightbox
            && lightbox.classList.contains('is-open')
            && !isFormField
            && (e.key === 'm' || e.key === 'M')
            && getLightboxVideo()
        ) {
            e.preventDefault();
            toggleLightboxMute();
            return;
        }
        const isDesktop = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
        if (!isDesktop || isFormField) return;
        if (lightbox && lightbox.classList.contains('is-open')) {
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                navigateLightbox(-1);
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                navigateLightbox(1);
            }
        }
    });
})();
