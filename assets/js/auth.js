(function () {
  'use strict';

  document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
    var selector = button.getAttribute('data-password-toggle');
    var input = selector ? document.querySelector(selector) : button.closest('.input-with-action')?.querySelector('input');
    if (!input) return;
    button.addEventListener('click', function () {
      var showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';
      button.setAttribute('aria-pressed', String(!showing));
      button.textContent = showing ? 'Show' : 'Hide';
      input.focus();
    });
  });

  document.querySelectorAll('input[data-password-strength], input[name="password"]').forEach(function (input) {
    var meter = document.querySelector(input.dataset.passwordStrength || '[data-password-meter]');
    if (!meter) return;
    function updateStrength() {
      var value = input.value;
      var score = 0;
      if (value.length >= 10) score++;
      if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score++;
      if (/\d/.test(value)) score++;
      if (/[^A-Za-z0-9]/.test(value) && value.length >= 12) score++;
      meter.dataset.strength = String(score);
      var bar = meter.querySelector('span');
      if (bar) bar.style.width = (score * 25) + '%';
      meter.setAttribute('aria-valuenow', String(score * 25));
    }
    input.addEventListener('input', updateStrength);
    updateStrength();
  });
}());
