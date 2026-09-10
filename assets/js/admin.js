(function () {
  'use strict';

  var sidebar = document.querySelector('.admin-sidebar');
  var toggle = document.querySelector('.admin-menu');
  if (!sidebar || !toggle) return;

  var overlay = document.createElement('div');
  overlay.className = 'admin-overlay';
  document.body.appendChild(overlay);
  toggle.setAttribute('aria-expanded', 'false');

  function setOpen(open) {
    sidebar.classList.toggle('open', open);
    overlay.classList.toggle('open', open);
    document.body.classList.toggle('menu-open', open);
    toggle.setAttribute('aria-expanded', String(open));
  }

  toggle.addEventListener('click', function () { setOpen(!sidebar.classList.contains('open')); });
  overlay.addEventListener('click', function () { setOpen(false); });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') setOpen(false);
  });
  window.addEventListener('resize', function () {
    if (window.innerWidth >= 1024) setOpen(false);
  });

  function normalizedPath(path) {
    return path.replace(/\/index\.php$/, '').replace(/\/$/, '') || '/';
  }
  var currentPath = normalizedPath(window.location.pathname);
  sidebar.querySelectorAll('nav a[href]').forEach(function (link) {
    var linkPath = normalizedPath(new URL(link.href, window.location.origin).pathname);
    if (linkPath === currentPath) link.setAttribute('aria-current', 'page');
  });

  document.querySelectorAll('.responsive-table').forEach(function (wrapper) {
    var headings = Array.from(wrapper.querySelectorAll('thead th')).map(function (heading) { return heading.textContent.trim(); });
    wrapper.querySelectorAll('tbody tr').forEach(function (row) {
      Array.from(row.children).forEach(function (cell, index) {
        if (!cell.dataset.label && headings[index]) cell.dataset.label = headings[index];
      });
    });
  });
}());
