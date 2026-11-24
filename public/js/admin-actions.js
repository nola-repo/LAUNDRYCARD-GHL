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
      button.addEventListener('click', () => {
        trigger = button;
        dialog.showModal();
        cancel.focus();
      });
    });
    cancel.addEventListener('click', () => dialog.close());
    dialog.addEventListener('close', () => trigger?.focus());
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
