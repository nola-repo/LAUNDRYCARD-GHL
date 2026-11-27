/* Server-filtered directory controls. Rows change only after explicit navigation. */
(() => {
  const page = document.querySelector('.records-page');
  if (!page) return;
  const form = page.querySelector('.records-filters');
  const search = form.querySelector('#records-search');
  const clear = form.querySelector('.records-search-clear');
  const density = page.querySelector('.records-density');
  const from = form.querySelector('#records-from');
  const to = form.querySelector('#records-to');
  const error = form.querySelector('#records-filter-error');
  let submitting = false;
  let clearNavigation = false;
  const updateClear = () => { clear.hidden = !search.value; };
  search.addEventListener('input', updateClear);
  search.addEventListener('keydown', event => { if (event.key === 'Enter' && event.isComposing) event.preventDefault(); });
  clear.addEventListener('click', () => {
    search.value = '';
    updateClear();
    search.focus();
    const query = new URL(location.href).searchParams;
    if (query.has('search') || query.has('q')) { clearNavigation = true; form.requestSubmit(); }
  });
  function clearError() { error.hidden = true; to.removeAttribute('aria-invalid'); to.removeAttribute('aria-describedby'); }
  from.addEventListener('input', clearError);
  to.addEventListener('input', clearError);
  form.addEventListener('submit', event => {
    if (submitting) { event.preventDefault(); return; }
    clearError();
    if (from.value && to.value && from.value > to.value) {
      event.preventDefault();
      form.querySelector('details').open = true;
      error.textContent = 'Choose an end date on or after the start date.';
      error.hidden = false;
      to.setAttribute('aria-invalid', 'true');
      to.setAttribute('aria-describedby', error.id);
      to.focus();
      return;
    }
    submitting = true;
    const url = new URL(form.action);
    url.hash = clearNavigation ? 'records-search' : 'records-results-title';
    form.action = url.href;
    form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = true; button.setAttribute('aria-busy', 'true'); });
  });
  const reset = () => { submitting = false; clearNavigation = false; form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = false; button.removeAttribute('aria-busy'); }); };
  window.addEventListener('pageshow', reset);
  page.querySelector('#records-per-page').addEventListener('change', event => event.target.form.requestSubmit());
  const setDensity = compact => { page.dataset.density = compact ? 'compact' : 'comfortable'; density.setAttribute('aria-pressed', String(compact)); };
  try { setDensity(localStorage.getItem('laundrycard.records.density') === 'compact'); } catch { setDensity(false); }
  density.hidden = false;
  density.addEventListener('click', () => { const compact = density.getAttribute('aria-pressed') !== 'true'; setDensity(compact); try { localStorage.setItem('laundrycard.records.density', compact ? 'compact' : 'comfortable'); } catch {} });
  updateClear();
})();

(() => {
  const dialog = document.getElementById('records-export-dialog');
  if (!dialog) return;
  const form = dialog.querySelector('form');
  const filters = document.querySelector('.records-filters');
  const appliedFilters = new URLSearchParams(new FormData(filters)).toString();
  const columns = [...form.querySelectorAll('[name="columns[]"]')];
  const group = form.querySelector('.records-export-columns');
  const error = form.querySelector('.records-export-error');
  const status = form.querySelector('[data-confirm-status]');
  const update = () => {
    const count = columns.filter(column => column.checked).length;
    form.querySelector('[data-export-count]').textContent = `${count} of ${columns.length} selected`;
    error.hidden = true;
    group.removeAttribute('aria-invalid');
    status.textContent = '';
  };
  form.querySelectorAll('[data-export-select]').forEach(button => button.addEventListener('click', () => {
    columns.forEach(column => { column.checked = button.dataset.exportSelect === 'all'; });
    update();
  }));
  columns.forEach(column => column.addEventListener('change', update));
  document.querySelector('[data-confirm-open="records-export-dialog"]').addEventListener('click', () => {
    form.querySelector('.records-export-draft').hidden = new URLSearchParams(new FormData(filters)).toString() === appliedFilters;
    status.textContent = '';
  });
  form.addEventListener('submit', event => {
    if (!columns.some(column => column.checked)) {
      event.preventDefault();
      error.textContent = 'Select at least one column to download.';
      error.hidden = false;
      group.setAttribute('aria-invalid', 'true');
      group.focus();
      return;
    }
    // Leave transfer progress and retry to the native download manager. No blob
    // buffering, arbitrary timeout, or false "download completed" message.
    status.textContent = 'Download requested. Check your browser’s downloads for progress.';
  });
  update();
})();

(() => {
  const bar        = document.getElementById('records-live-bar');
  const text       = document.getElementById('records-live-text');
  const badge      = document.getElementById('records-new-badge');
  const refreshBtn = document.getElementById('records-refresh-btn');
  const signInLink = document.getElementById('records-signin-link');
  const checkedEl  = document.getElementById('records-live-checked');
  const countEl    = document.getElementById('records-live-count');
  const progress   = document.getElementById('records-live-progress');
  if (!bar) return;

  const apiUrl  = bar.dataset.url;
  const POLL_MS = 6000;
  const POLL_SECONDS = Math.round(POLL_MS / 1000);
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  let knownTotal  = Number(bar.dataset.total);
  let lastEventId = 0;
  let newCount    = 0;
  let animFrame   = null;
  let pollTimer   = null;
  let requestTimer = null;
  let controller  = null;
  let stopped     = false;
  let inFlight    = false;

  function animateProgress(startTs) {
    const elapsed = Date.now() - startTs;
    const pct     = Math.min(100, (elapsed / POLL_MS) * 100);
    progress.style.width = pct + '%';
    if (pct < 100 && !document.hidden && !stopped) {
      animFrame = requestAnimationFrame(() => animateProgress(startTs));
    }
  }

  function setState(state) {
    bar.className = 'records-live-bar records-live-bar--' + state;
  }

  function setStatus(message) {
    if (text.textContent !== message) text.textContent = message;
  }

  function checkedTime(value) {
    const date = value ? new Date(value) : new Date();
    if (Number.isNaN(date.getTime())) return 'UTC';
    return new Intl.DateTimeFormat(undefined, {
      hour: 'numeric', minute: '2-digit', timeZone: 'UTC', timeZoneName: 'short'
    }).format(date);
  }

  function stopPolling() {
    stopped = true;
    clearTimeout(pollTimer);
    clearTimeout(requestTimer);
    cancelAnimationFrame(animFrame);
    controller?.abort();
    progress.style.width = '0%';
  }

  async function poll() {
    if (stopped || document.hidden || inFlight) return;
    cancelAnimationFrame(animFrame);
    progress.style.width = '0%';
    inFlight = true;
    controller = new AbortController();
    requestTimer = setTimeout(() => controller?.abort(), 10000);

    try {
      const res = await fetch(`${apiUrl}?after=${lastEventId}`, {
        signal: controller.signal,
        headers: { 'Accept': 'application/json' },
        cache: 'no-store'
      });
      if (res.status === 401) {
        setStatus('Sign in again to reconnect the live feed.');
        signInLink.style.display = 'inline-flex';
        setState('disconnected');
        stopPolling();
        return;
      }
      if (res.status === 403) {
        setStatus('Your account cannot access live record updates.');
        setState('disconnected');
        stopPolling();
        return;
      }
      if (!res.ok) throw new Error('Feed request failed');
      const data = await res.json();

      lastEventId = data.last_event_id || lastEventId;

      const liveTotal = data.stats?.records ?? knownTotal;
      const diff = Number(liveTotal) - Number(knownTotal);

      if (diff > 0) {
        newCount += diff;
        badge.textContent = `+${newCount.toLocaleString()} new record${newCount !== 1 ? 's' : ''}`;
        badge.style.display = 'inline-flex';
        refreshBtn.style.display = 'inline-flex';
        setStatus('New records are ready to load.');
        setState('new');
      } else if (newCount === 0) {
        setStatus('Live feed connected.');
        setState('connected');
      }
      knownTotal = Number(liveTotal);

      if (data.stats) {
        for (const [key, value] of Object.entries(data.stats)) {
          const el = document.querySelector(`[data-live-stat="${key}"]`);
          if (el && value !== null && typeof value !== 'object') el.textContent = Number(value).toLocaleString();
        }
      }

      checkedEl.textContent = `Checked ${checkedTime(data.updated_at)} · every ${POLL_SECONDS}s`;
      countEl.textContent = `${Number(liveTotal).toLocaleString()} records in directory`;
    } catch (err) {
      if (!document.hidden && !stopped) {
        setStatus('Live feed unavailable. Retrying shortly.');
        setState('disconnected');
      }
    } finally {
      clearTimeout(requestTimer);
      controller = null;
      inFlight = false;
      if (!stopped && !document.hidden) {
        if (!reduceMotion) animateProgress(Date.now());
        pollTimer = setTimeout(poll, POLL_MS);
      }
    }
  }

  refreshBtn.addEventListener('click', () => window.location.reload());
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      clearTimeout(pollTimer);
      clearTimeout(requestTimer);
      cancelAnimationFrame(animFrame);
      controller?.abort();
      progress.style.width = '0%';
    } else if (!stopped && !inFlight) {
      poll();
    }
  });
  window.addEventListener('pagehide', stopPolling, { once: true });
  window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
      stopped = false;
      poll();
    }
  });

  poll();
})();
