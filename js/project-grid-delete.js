(function () {
  const form = document.getElementById('grid-delete-form');
  if (!form) return;
  const deleteButton = document.querySelector('.fab-delete');
  const checkboxes = Array.from(form.querySelectorAll('input[type="checkbox"][name="project_ids[]"]'));
  if (!deleteButton || checkboxes.length === 0) return;

  const grid = document.querySelector('.project-grid');

  function updateDeleteButton() {
    const anyChecked = checkboxes.some((cb) => cb.checked);
    deleteButton.classList.toggle('is-active', anyChecked);
    deleteButton.disabled = !anyChecked;
    if (grid) {
      grid.classList.toggle('has-selection', anyChecked);
    }
    checkboxes.forEach((cb) => {
      const label = cb.closest('.project-select');
      if (label) {
        label.classList.toggle('is-checked', cb.checked);
      }
    });
  }

  form.addEventListener('change', function (event) {
    if (event.target && event.target.matches('input[type="checkbox"][name="project_ids[]"]')) {
      updateDeleteButton();
    }
  });

  form.addEventListener('submit', function (event) {
    if (event.submitter !== deleteButton) {
      return;
    }
    const anyChecked = checkboxes.some((cb) => cb.checked);
    if (!anyChecked) {
      event.preventDefault();
      return;
    }
    const ok = window.confirm('Ausgewählte Projekte wirklich löschen?');
    if (!ok) {
      event.preventDefault();
    }
  });

  updateDeleteButton();
})();
