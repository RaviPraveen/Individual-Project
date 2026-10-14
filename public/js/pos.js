(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    // Update the displayed date and time.
    function updateClock() {
      var dateEl = document.getElementById('pos-live-date');
      var clockEl = document.getElementById('pos-live-clock');
      if (!dateEl && !clockEl) return;

      var now = new Date();

      if (dateEl) {
        var dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        dateEl.textContent = new Intl.DateTimeFormat(navigator.language || 'en-US', dateOptions).format(now);
      }

      if (clockEl) {
        var timeOptions = { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true };
        clockEl.textContent = new Intl.DateTimeFormat(navigator.language || 'en-US', timeOptions).format(now);
      }
    }

    updateClock();
    setInterval(updateClock, 1000);

    // Show session messages as toasts.
    var flashEl = document.getElementById('pos-flash-data');
    if (flashEl) {
      var success = flashEl.dataset.success;
      var error = flashEl.dataset.error;
      if (success && success !== 'null' && success !== '') showToast(success, 'success');
      if (error && error !== 'null' && error !== '') showToast(error, 'danger');
    }

    var animated = document.querySelectorAll('.pos-stat-card, .quick-action, .card');
    animated.forEach(function (el, i) {
      if (!el.classList.contains('pos-animate-in')) {
        el.classList.add('pos-animate-in');
        el.style.animationDelay = Math.min(i * 30, 300) + 'ms';
      }
    });

    // Mobile sidebar.
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

    if (window.Swal) {
      window.posAlert = function(title, text, icon) {
        return Swal.fire({
          title: title || '',
          text: text || '',
          icon: icon || 'info',
          confirmButtonColor: '#5B5CEB',
          customClass: {
            popup: 'rounded-4 shadow-lg',
            confirmButton: 'btn btn-primary px-4'
          }
        });
      };

      window.posConfirm = function(title, text, icon) {
        return Swal.fire({
          title: title || '',
          text: text || '',
          icon: icon || 'question',
          showCancelButton: true,
          confirmButtonColor: '#5B5CEB',
          cancelButtonColor: '#94A3B8',
          confirmButtonText: 'Confirm',
          customClass: {
            popup: 'rounded-4 shadow-lg',
            confirmButton: 'btn btn-primary px-4 me-2',
            cancelButton: 'btn btn-outline-secondary px-4'
          },
          buttonsStyling: false,
        }).then(function (result) { return result.isConfirmed; });
      };

      // Submit forms after confirmation, or notify the page's button handler.
      document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
          if (el.dataset.confirmed === '1') return;
          e.preventDefault();

          window.posConfirm(el.dataset.confirm, el.dataset.confirmText || '', el.dataset.confirmIcon || 'warning').then(function (ok) {
            if (!ok) return;
            el.dataset.confirmed = '1';

            if (el.tagName === 'FORM') {
              el.requestSubmit ? el.requestSubmit() : el.submit();
            } else if (el.form) {
              el.form.requestSubmit ? el.form.requestSubmit(el) : el.form.submit();
            } else {
              el.dispatchEvent(new CustomEvent('pos-confirmed', { bubbles: true }));
            }
          });
        });
      });
    }
  });

  // Shared toast messages.
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
        '<div class="d-flex align-items-center gap-2 px-3 py-2.5 flex-grow-1">' +
          '<i class="bi ' + (TOAST_ICONS[type] || TOAST_ICONS.info) + ' text-' + type + ' fs-5"></i>' +
          '<div class="toast-body p-0 fw-semibold text-dark">' + message + '</div>' +
          '<button type="button" class="btn-close ms-auto" data-bs-dismiss="toast"></button>' +
        '</div>' +
      '</div>' +
      '<div class="toast-progress text-' + type + '"></div>';

    container.appendChild(wrapper);
    if (window.bootstrap) {
      var toast = new bootstrap.Toast(wrapper, { delay: 4500 });

      var progress = wrapper.querySelector('.toast-progress');
      progress.style.transition = 'width 4500ms linear';
      progress.style.width = '100%';
      requestAnimationFrame(function () { progress.style.width = '0%'; });

      wrapper.addEventListener('hidden.bs.toast', function () { wrapper.remove(); });
      toast.show();
    }
  }

  window.posToast = showToast;
})();
