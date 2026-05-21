(function () {
  const backTarget = 'index.php';
  const navEntries = performance.getEntriesByType('navigation');
  const navType = navEntries && navEntries.length ? navEntries[0].type : '';
  if (navType === 'back_forward') {
    window.location.replace(backTarget);
    return;
  }

  const mediaSection = document.getElementById('media-upload');
  if (!mediaSection) return;

  const mediaActionUrl = mediaSection.dataset.mediaActionUrl || '';
  const mode = mediaSection.dataset.mediaMode || 'edit';
  const fileInputId = mode === 'create' ? 'draft_gallery_files' : 'gallery_files';

  const returnToInput = document.getElementById('return_to');
  if (returnToInput && mediaSection.dataset.defaultReturn) {
    const defaultReturn = mediaSection.dataset.defaultReturn;
    returnToInput.value = document.referrer || defaultReturn || returnToInput.value || defaultReturn;
  }

  let isSubmitting = false;
  if (mode === 'create') {
    const createProjectForm = document.getElementById('create-project-form');
    if (createProjectForm) {
      createProjectForm.addEventListener('submit', function () {
        isSubmitting = true;
      });
    }
    document.addEventListener(
      'submit',
      function (event) {
        const form = event.target;
        if (form instanceof HTMLFormElement && form.dataset.ajax === 'true') {
          return;
        }
        isSubmitting = true;
      },
      true
    );
  }

  function getCsrfToken() {
    if (window.PORTFOLIO_CSRF) return window.PORTFOLIO_CSRF;
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') || '' : '';
  }

  function showMediaUploadError(text) {
    mediaSection.innerHTML = '<div class="alert media-alert">❌ ' + text + '</div>';
  }

  async function submitMediaForm(form, submitter) {
    const formData = new FormData(form);
    formData.set('ajax', '1');
    if (submitter && submitter.name) {
      formData.set(submitter.name, submitter.value || '1');
    }
    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'fetch',
        },
      });
      if (!response.ok) {
        showMediaUploadError('Upload fehlgeschlagen (HTTP ' + response.status + ').');
        return;
      }
      const html = await response.text();
      mediaSection.innerHTML = html;
    } catch (err) {
      showMediaUploadError('Upload fehlgeschlagen (Netzwerk oder Zeitüberschreitung).');
    }
  }

  mediaSection.addEventListener('submit', function (event) {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.dataset.ajax !== 'true') {
      return;
    }
    event.preventDefault();
    submitMediaForm(form, event.submitter);
  });

  mediaSection.addEventListener('change', function (event) {
    const target = event.target;
    if (!target || target.id !== fileInputId) {
      return;
    }
    const form = target.closest('form');
    if (!form || form.dataset.ajax !== 'true') {
      return;
    }
    if (!target.files || target.files.length === 0) {
      return;
    }
    submitMediaForm(form);
  });

  function sendReorder(grid) {
    if (!mediaActionUrl) return;
    const order = Array.from(grid.querySelectorAll('.media-tile')).map((tile) => tile.dataset.index);
    const formData = new FormData();
    order.forEach((idx) => formData.append('order[]', idx));
    formData.set('reorder_media', '1');
    formData.set('ajax', '1');
    const token = getCsrfToken();
    if (token) formData.set('_token', token);
    fetch(mediaActionUrl, {
      method: 'POST',
      body: formData,
      headers: {
        'X-Requested-With': 'fetch',
      },
    })
      .then((response) => response.text())
      .then((html) => {
        mediaSection.innerHTML = html;
      })
      .catch(() => {});
  }

  let pointerDrag = null;
  mediaSection.addEventListener('pointerdown', function (event) {
    const tile = event.target.closest('.media-tile');
    if (!tile) return;
    if (event.pointerType === 'mouse' && event.button !== 0) return;
    if (event.target.closest('button, input, form')) return;
    pointerDrag = tile;
    tile.classList.add('is-dragging');
    tile.setPointerCapture(event.pointerId);
    event.preventDefault();
  });

  mediaSection.addEventListener('pointermove', function (event) {
    if (!pointerDrag) return;
    const el = document.elementFromPoint(event.clientX, event.clientY);
    const tile = el ? el.closest('.media-tile') : null;
    if (!tile || tile === pointerDrag) return;
    const grid = tile.parentElement;
    const tiles = Array.from(grid.querySelectorAll('.media-tile'));
    const draggedIndex = tiles.indexOf(pointerDrag);
    const targetIndex = tiles.indexOf(tile);
    if (draggedIndex < targetIndex) {
      grid.insertBefore(pointerDrag, tile.nextSibling);
    } else {
      grid.insertBefore(pointerDrag, tile);
    }
  });

  function endPointerDrag(event) {
    if (!pointerDrag) return;
    const grid = pointerDrag.parentElement;
    pointerDrag.classList.remove('is-dragging');
    try {
      pointerDrag.releasePointerCapture(event.pointerId);
    } catch (e) {
      // ignore
    }
    pointerDrag = null;
    if (grid) {
      sendReorder(grid);
    }
  }

  mediaSection.addEventListener('pointerup', endPointerDrag);
  mediaSection.addEventListener('pointercancel', endPointerDrag);

  if (mode === 'create' && mediaActionUrl) {
    function getDraftId() {
      const node = document.getElementById('draft-id');
      if (!node) return null;
      const value = node.value ? node.value.trim() : '';
      return value.length > 0 ? value : null;
    }

    window.addEventListener('beforeunload', function () {
      const draftId = getDraftId();
      if (isSubmitting || !draftId) {
        return;
      }
      const data = new URLSearchParams({ cleanup_draft: '1' });
      const token = getCsrfToken();
      if (token) {
        data.set('_token', token);
      }
      navigator.sendBeacon(mediaActionUrl, new Blob([data.toString()], { type: 'application/x-www-form-urlencoded' }));
    });
  }
})();
