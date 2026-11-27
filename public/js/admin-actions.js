(() => {
  document.querySelectorAll('[data-confirm-dialog]').forEach(dialog => {
    const form = dialog.querySelector('form');
    const cancel = dialog.querySelector('[data-confirm-close]');
    const submit = form.querySelector('[type="submit"]');
    const status = dialog.querySelector('[data-confirm-status]');
    let trigger;
    let submitting = false;
    document.querySelectorAll('[data-confirm-open]').forEach(button => {
      if (button.dataset.confirmOpen !== dialog.id) return;
      button.hidden = false;
      button.addEventListener('click', () => {
        trigger = button;
        dialog.showModal();
        cancel.focus();
      });
    });
    cancel.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => trigger?.focus());
    dialog.addEventListener('keydown', event => {
      if (event.key !== 'Tab') return;
      const focusable = [...dialog.querySelectorAll('button, a[href], input, select, textarea, [tabindex]')]
        .filter(element => !element.disabled && element.tabIndex >= 0 && element.getClientRects().length);
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    });
    // Native downloads keep the page open; the browser owns transfer progress.
    // Each download workflow validates its own options without locking the dialog.
    if (form.hasAttribute('data-download-form')) return;
    form.addEventListener('submit', event => {
      if (submitting) { event.preventDefault(); return; }
      submitting = true;
      submit.disabled = true;
      submit.setAttribute('aria-busy', 'true');
      status.textContent = status.dataset.busyLabel;
    });
    window.addEventListener('pageshow', () => {
      submitting = false;
      submit.disabled = false;
      submit.removeAttribute('aria-busy');
      status.textContent = '';
    });
  });
})();
