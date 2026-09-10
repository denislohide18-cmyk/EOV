(function () {
  'use strict';

  var body = document.body;
  var menuButton = document.querySelector('.menu-toggle');
  var navigation = document.getElementById('site-nav');

  function closeMenu() {
    if (!menuButton || !navigation) return;
    menuButton.setAttribute('aria-expanded', 'false');
    navigation.classList.remove('open');
    body.classList.remove('menu-open');
  }

  if (menuButton && navigation) {
    menuButton.addEventListener('click', function () {
      var opening = menuButton.getAttribute('aria-expanded') !== 'true';
      menuButton.setAttribute('aria-expanded', String(opening));
      navigation.classList.toggle('open', opening);
      body.classList.toggle('menu-open', opening);
    });
    navigation.addEventListener('click', function (event) {
      if (event.target.closest('a')) closeMenu();
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') closeMenu();
    });
    window.addEventListener('resize', function () {
      if (window.innerWidth >= 832) closeMenu();
    });
  }

  function normalizedPath(path) {
    return path.replace(/\/index\.php$/, '').replace(/\/$/, '') || '/';
  }
  var currentPath = normalizedPath(window.location.pathname);
  document.querySelectorAll('.site-nav a[href]').forEach(function (link) {
    var linkPath = normalizedPath(new URL(link.href, window.location.origin).pathname);
    if (linkPath === currentPath && !link.href.includes('#')) link.setAttribute('aria-current', 'page');
  });

  function dismissToast(toast) {
    if (!toast || toast.classList.contains('is-hiding')) return;
    toast.classList.add('is-hiding');
    window.setTimeout(function () { toast.remove(); }, 220);
  }

  document.querySelectorAll('.toast').forEach(function (toast) {
    if (!toast.querySelector('.toast-close')) {
      var close = document.createElement('button');
      close.type = 'button';
      close.className = 'toast-close';
      close.setAttribute('aria-label', 'Dismiss notification');
      close.textContent = '\u00d7';
      close.addEventListener('click', function () { dismissToast(toast); });
      toast.appendChild(close);
    }
    window.setTimeout(function () { dismissToast(toast); }, 7000);
  });

  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (event.defaultPrevented || !form.checkValidity()) return;
      var submit = form.querySelector('button[type="submit"], input[type="submit"]');
      if (!submit || submit.dataset.noLoading !== undefined || submit.disabled) return;
      submit.dataset.originalLabel = submit.value || submit.innerHTML;
      submit.disabled = true;
      submit.setAttribute('aria-busy', 'true');
      if (submit.tagName === 'INPUT') submit.value = submit.dataset.loadingText || 'Working...';
      else submit.innerHTML = '<span class="spinner" aria-hidden="true"></span>' + (submit.dataset.loadingText || 'Working...');
      window.setTimeout(function () {
        if (!document.documentElement.contains(submit)) return;
        submit.disabled = false;
        submit.removeAttribute('aria-busy');
        if (submit.tagName === 'INPUT') submit.value = submit.dataset.originalLabel;
        else submit.innerHTML = submit.dataset.originalLabel;
      }, 12000);
    });
  });

  var confirmDialog;
  function ensureConfirmDialog() {
    if (confirmDialog) return confirmDialog;
    confirmDialog = document.createElement('dialog');
    confirmDialog.innerHTML = '<div class="dialog-body"><h2>Confirm action</h2><p data-confirm-message></p></div><div class="dialog-actions"><button class="btn btn-secondary" type="button" data-confirm-cancel>Cancel</button><button class="btn btn-danger" type="button" data-confirm-accept>Confirm</button></div>';
    body.appendChild(confirmDialog);
    confirmDialog.querySelector('[data-confirm-cancel]').addEventListener('click', function () { confirmDialog.close('cancel'); });
    return confirmDialog;
  }

  document.addEventListener('click', function (event) {
    var trigger = event.target.closest('[data-confirm]');
    if (!trigger || trigger.dataset.confirmed === 'true') return;
    event.preventDefault();
    var dialog = ensureConfirmDialog();
    dialog.querySelector('[data-confirm-message]').textContent = trigger.dataset.confirm || 'Are you sure you want to continue?';
    var accept = dialog.querySelector('[data-confirm-accept]');
    accept.textContent = trigger.dataset.confirmLabel || 'Confirm';
    accept.onclick = function () {
      dialog.close('confirm');
      trigger.dataset.confirmed = 'true';
      if (trigger.tagName === 'A') window.location.assign(trigger.href);
      else if (trigger.form) trigger.form.requestSubmit(trigger);
      else trigger.click();
    };
    if (typeof dialog.showModal === 'function') dialog.showModal();
    else if (window.confirm(trigger.dataset.confirm)) accept.click();
  });
}());
