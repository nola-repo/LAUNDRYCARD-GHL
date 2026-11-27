/* Shared server-filtered workspaces. Native controls; document-owned scrolling. */
(() => {
  document.querySelectorAll('.admin-workspace form[data-workspace-form]').forEach(form => {
    let submitting = false;
    let clearTarget;
    const error = form.querySelector('[data-form-error]');
    const from = form.querySelector('[name="from"]');
    const to = form.querySelector('[name="to"]');
    const reset = () => {
      submitting = false;
      form.querySelectorAll('[type="submit"]').forEach(button => { button.disabled = false; button.removeAttribute('aria-busy'); });
    };
    form.querySelectorAll('[data-clearable]').forEach(input => {
      const clear = input.parentElement.querySelector('[data-clear-search]');
      const update = () => { clear.hidden = !input.value; };
      input.addEventListener('input', update);
      input.addEventListener('keydown', event => { if (event.key === 'Enter' && event.isComposing) event.preventDefault(); });
      clear.addEventListener('click', () => {
        input.value = ''; update(); input.focus();
        if (new URL(location.href).searchParams.has(input.name)) { clearTarget = input.id; form.requestSubmit(); }
      });
      update();
    });
    form.addEventListener('input', () => { if (error) error.hidden = true; to?.removeAttribute('aria-invalid'); to?.removeAttribute('aria-describedby'); });
    form.addEventListener('submit', event => {
      if (submitting) { event.preventDefault(); return; }
      if (from?.value && to?.value && from.value > to.value) {
        event.preventDefault();
        error.textContent = 'Choose an end date on or after the start date.'; error.hidden = false;
        to.setAttribute('aria-invalid', 'true'); to.setAttribute('aria-describedby', error.id); to.focus(); return;
      }
      if (form.method.toLowerCase() === 'get') {
        const url = new URL(form.action); url.hash = clearTarget || 'workspace-results'; form.action = url.href;
      }
      submitting = true;
      form.querySelectorAll('[type="submit"]').forEach(button => { button.disabled = true; button.setAttribute('aria-busy', 'true'); });
    });
    window.addEventListener('pageshow', reset);
  });

  const configButton = document.querySelector('[data-test-oauth]');
  if (!configButton) return;
  const result = document.querySelector('[data-oauth-result]');
  let controller;
  configButton.addEventListener('click', async () => {
    if (controller) return;
    controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 10000);
    configButton.disabled = true; configButton.setAttribute('aria-busy', 'true');
    result.hidden = false; result.dataset.tone = 'info'; result.textContent = 'Checking OAuth configuration…';
    try {
      const response = await fetch(configButton.dataset.testOauth, {signal:controller.signal,headers:{Accept:'application/json'},cache:'no-store'});
      if (response.status === 401 || response.redirected) throw new Error('Your session has expired. Sign in again, then retry the check.');
      if (response.status === 403) throw new Error('Your account cannot run this check.');
      const data = await response.json();
      if (!response.ok || data.error) throw new Error(data.error || 'The check could not be completed. Try again.');
      if (!data.redacted) throw new Error('No authorization preview was returned. Try again.');
      result.dataset.tone = 'success';
      const message = document.createElement('p'); message.textContent = 'Configuration loaded and an authorization URL was generated. This does not test the token endpoint.';
      const preview = document.createElement('code'); preview.textContent = data.redacted;
      result.replaceChildren(message, preview);
    } catch (error) {
      result.dataset.tone = 'error'; result.textContent = error.name === 'AbortError' ? 'The check timed out. Try again.' : error.message;
    } finally {
      clearTimeout(timer); controller = null; configButton.disabled = false; configButton.removeAttribute('aria-busy');
    }
  });
  window.addEventListener('pagehide', () => controller?.abort());
})();
