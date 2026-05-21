(function () {
  var forms = Array.from(document.querySelectorAll('.role-form'));

  function snapshotForm(form) {
    var data = new FormData(form);
    var entries = [];
    var it = data.entries();
    var step = it.next();
    while (!step.done) {
      entries.push(step.value);
      step = it.next();
    }
    entries.sort(function (a, b) {
      if (a[0] === b[0]) return String(a[1]).localeCompare(String(b[1]));
      return String(a[0]).localeCompare(String(b[0]));
    });
    return JSON.stringify(entries);
  }

  function updateCommentLimitState(form) {
    var limitPermission = form.querySelector('input[name="permissions[]"][value="comment_limit"]');
    var limitField = form.querySelector('[data-limit-field]');
    if (!limitField) return;
    var limitInput = limitField.querySelector('input[name="comment_limit"]');
    if (!limitInput) return;
    var enabled = limitPermission && limitPermission.checked;
    limitInput.disabled = !enabled;
    limitField.classList.toggle('is-disabled', !enabled);
  }

  function updateDeleteRolesState(form) {
    var deletePermission = form.querySelector('input[name="permissions[]"][value="delete_comments"]');
    var selector = form.querySelector('[data-role-selector]');
    if (!selector) return;
    var enabled = deletePermission && deletePermission.checked;
    selector.classList.toggle('is-disabled', !enabled);
    selector.querySelectorAll('button, input').forEach(function (el) {
      el.disabled = !enabled;
    });
  }

  forms.forEach(function (form) {
    var saveButton = form.querySelector('.save-button');
    if (!saveButton) return;
    var initial = snapshotForm(form);

    function updateState() {
      saveButton.disabled = snapshotForm(form) === initial;
    }

    form.addEventListener('input', updateState);
    form.addEventListener('change', updateState);
    form.addEventListener('input', function () {
      updateCommentLimitState(form);
    });
    form.addEventListener('change', function () {
      updateCommentLimitState(form);
    });
    form.addEventListener('input', function () {
      updateDeleteRolesState(form);
    });
    form.addEventListener('change', function () {
      updateDeleteRolesState(form);
    });
    form.addEventListener('reset', function () {
      initial = snapshotForm(form);
      updateState();
      updateCommentLimitState(form);
      updateDeleteRolesState(form);
    });
    form.addEventListener('submit', function () {
      saveButton.disabled = true;
    });

    updateCommentLimitState(form);
    updateDeleteRolesState(form);
  });

  Array.from(document.querySelectorAll('[data-role-selector]')).forEach(function (selector) {
    var toggle = selector.querySelector('.role-selector-toggle');
    var panel = selector.querySelector('.role-selector-panel');
    var searchInput = selector.querySelector('.role-selector-search input');
    var selectAll = selector.querySelector('[data-role-select-all]');
    var items = Array.from(selector.querySelectorAll('.role-selector-list label'));
    if (!toggle || !panel) return;

    function updateToggleLabel() {
      var checked = items.filter(function (item) {
        var input = item.querySelector('input');
        return input && input.checked;
      }).length;
      toggle.textContent = checked > 0 ? checked + ' Rolle(n) ausgewählt' : 'Rollen auswählen';
    }

    function updateSelectAllState() {
      var inputs = items
        .map(function (item) {
          return item.querySelector('input');
        })
        .filter(Boolean);
      var checkedCount = inputs.filter(function (input) {
        return input.checked;
      }).length;
      if (selectAll) {
        selectAll.checked = checkedCount > 0 && checkedCount === inputs.length;
        selectAll.indeterminate = checkedCount > 0 && checkedCount < inputs.length;
      }
    }

    function filterList() {
      var query = (searchInput && searchInput.value ? searchInput.value : '').trim().toLowerCase();
      items.forEach(function (item) {
        var text = item.textContent ? item.textContent.toLowerCase() : '';
        item.style.display = text.indexOf(query) !== -1 ? '' : 'none';
      });
    }

    toggle.addEventListener('click', function () {
      var isOpen = !panel.hasAttribute('hidden');
      if (isOpen) {
        panel.setAttribute('hidden', '');
        toggle.setAttribute('aria-expanded', 'false');
      } else {
        panel.removeAttribute('hidden');
        toggle.setAttribute('aria-expanded', 'true');
        if (searchInput) searchInput.focus();
      }
    });

    document.addEventListener('click', function (event) {
      if (!selector.contains(event.target)) {
        panel.setAttribute('hidden', '');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });

    items.forEach(function (item) {
      var input = item.querySelector('input');
      if (!input) return;
      input.addEventListener('change', function () {
        updateSelectAllState();
        updateToggleLabel();
      });
    });

    if (selectAll) {
      selectAll.addEventListener('change', function () {
        var checked = selectAll.checked;
        items.forEach(function (item) {
          var input = item.querySelector('input');
          if (input) input.checked = checked;
        });
        updateSelectAllState();
        updateToggleLabel();
      });
    }

    if (searchInput) {
      searchInput.addEventListener('input', filterList);
    }

    updateSelectAllState();
    updateToggleLabel();
  });
})();
