(function () {
  var container = document.getElementById('legal-sections');
  var template = document.getElementById('legal-section-template');
  var addBtn = document.getElementById('add-legal-section');
  if (!container || !template || !addBtn) return;

  function reindexSections() {
    var blocks = container.querySelectorAll('[data-section]');
    blocks.forEach(function (block, index) {
      var heading = block.querySelector('[data-section-heading], input[type="text"]');
      var body = block.querySelector('[data-section-body], textarea');
      if (heading) heading.name = 'sections[' + index + '][heading]';
      if (body) body.name = 'sections[' + index + '][body]';
    });
  }

  function addSection() {
    var clone = template.content.firstElementChild.cloneNode(true);
    container.appendChild(clone);
    reindexSections();
  }

  addBtn.addEventListener('click', addSection);
  container.addEventListener('click', function (event) {
    var btn = event.target.closest('[data-remove-section]');
    if (!btn) return;
    var block = btn.closest('[data-section]');
    if (!block) return;
    if (container.querySelectorAll('[data-section]').length <= 1) {
      block.querySelectorAll('input, textarea').forEach(function (el) {
        el.value = '';
      });
      return;
    }
    block.remove();
    reindexSections();
  });

  reindexSections();
})();
