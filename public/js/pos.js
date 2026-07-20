(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    /* ---------- toast bridge: turn session flash messages into toasts ---------- */
    var flashEl = document.getElementById('pos-flash-data');
    if (flashEl && window.bootstrap) {
      var success = flashEl.dataset.success;
      var error = flashEl.dataset.error;
      if (success) showToast(success, 'success');
      if (error) showToast(error, 'danger');
    }

    /* ---------- staggered entrance for stat/quick-action cards ---------- */
    var animated = document.querySelectorAll('.pos-stat-card, .quick-action');
    animated.forEach(function (el, i) {
      el.classList.add('pos-animate-in');
      el.style.animationDelay = (i * 40) + 'ms';
    });

    /* ---------- sidebar toggle (mobile) ---------- */
    var sidebarToggle = document.getElementById('pos-sidebar-toggle');
    var sidebar = document.querySelector('.pos-sidebar');
    if (sidebarToggle && sidebar) {
      sidebarToggle.addEventListener('click', function () {
        sidebar.classList.toggle('show');
      });
      document.addEventListener('click', function (e) {
        if (sidebar.classList.contains('show') && !sidebar.contains(e.target) && e.target !== sidebarToggle && !sidebarToggle.contains(e.target)) {
          sidebar.classList.remove('show');
        }
      });
    }
  });

  /* ---------- toast helper, reusable for JS-triggered notices too ---------- */
  var TOAST_ICONS = {
    success: 'bi-check-circle-fill',
    danger: 'bi-x-circle-fill',
    warning: 'bi-exclamation-triangle-fill',
    info: 'bi-info-circle-fill',
  };

  function showToast(message, type) {
    type = type || 'info';
    var container = document.getElementById('pos-toast-container');
    if (!container) return;

    var wrapper = document.createElement('div');
    wrapper.className = 'toast align-items-center border-0 mb-2 toast-' + type;
    wrapper.setAttribute('role', 'alert');
    wrapper.innerHTML =
      '<div class="d-flex">' +
        '<div class="toast-accent"></div>' +
        '<div class="d-flex align-items-center gap-2 px-3 py-2 flex-grow-1">' +
          '<i class="bi ' + (TOAST_ICONS[type] || TOAST_ICONS.info) + ' text-' + type + '"></i>' +
          '<div class="toast-body p-0 small">' + message + '</div>' +
          '<button type="button" class="btn-close ms-auto" data-bs-dismiss="toast"></button>' +
        '</div>' +
      '</div>' +
      '<div class="toast-progress text-' + type + '"></div>';

    container.appendChild(wrapper);
    var toast = new bootstrap.Toast(wrapper, { delay: 4500 });

    var progress = wrapper.querySelector('.toast-progress');
    progress.style.transition = 'width 4500ms linear';
    progress.style.width = '100%';
    requestAnimationFrame(function () { progress.style.width = '0%'; });

    wrapper.addEventListener('hidden.bs.toast', function () { wrapper.remove(); });
    toast.show();
  }

  window.posToast = showToast;
})();
