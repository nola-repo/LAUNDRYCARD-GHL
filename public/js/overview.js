(() => {
  const page = document.querySelector('.overview[data-feed]');
  if (!page) return;

  const find = (selector) => page.querySelector(selector);
  const status = find('#dashboard-live-state');
  const checked = find('#ov-checked');
  const toggle = find('#ov-pause');
  const refresh = find('#manual-refresh-btn');
  const signIn = find('#ov-sign-in');
  const filter = find('#customer-search-input');
  const clear = find('#ov-clear');
  const customers = find('#ov-customers');
  const count = find('#ov-result-count');
  const noMatch = find('#ov-no-match');
  const formatCount = new Intl.NumberFormat(document.documentElement.lang || 'en');
  const utcTime = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'UTC', hour: '2-digit', minute: '2-digit', second: '2-digit',
  });
  const parts = ['health', 'balances', 'customers', 'events', 'stores', 'runs'];
  const previousMarkup = new Map();
  const interval = 4000;
  let paused = false;
  let timer;
  let activeRequest;

  function showState(state, label) {
    status.dataset.state = state;
    if (status.textContent !== label) status.textContent = label;
  }

  function applyFilter() {
    const query = filter.value.trim().toLowerCase();
    const rows = customers.querySelectorAll('tr:not([data-key="empty"])');
    let visible = 0;
    for (const row of rows) {
      row.hidden = Boolean(query) && !row.textContent.toLowerCase().includes(query);
      if (!row.hidden) visible++;
    }
    noMatch.hidden = !query || !rows.length || visible > 0;
    clear.hidden = !filter.value;
    const label = query ? `${visible} of ${rows.length} shown` : `${rows.length} recent`;
    if (count.textContent !== label) count.textContent = label;
  }

  filter.addEventListener('input', applyFilter);
  clear.addEventListener('click', () => {
    filter.value = '';
    applyFilter();
    filter.focus();
  });
  find('#ov-filter').hidden = false;
  applyFilter();

  function highlight(element) {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    element.classList.add('ov-changed');
    element.addEventListener('animationend', () => element.classList.remove('ov-changed'), { once: true });
  }

  // Rendered fragments come from the authenticated Overview, with all data escaped in Blade.
  function parsePart(name, html) {
    const parent = find('#ov-' + name);
    const range = document.createRange();
    range.selectNodeContents(parent);
    return { name, parent, fragment: range.createContextualFragment(html) };
  }

  function updatePart({ name, parent, fragment }) {
    if (name === 'balances') {
      parent.replaceChildren(fragment);
      return;
    }

    const existing = new Map(Array.from(parent.children, node => [node.dataset.key, node]));
    let position = parent.firstElementChild;
    for (const next of Array.from(fragment.children)) {
      const key = next.dataset.key;
      let node = existing.get(key);
      existing.delete(key);
      if (!node) {
        node = next;
        if (key !== 'empty') highlight(node);
      } else if (name === 'health') {
        // Keep each disclosure, its open state and its focused summary in place.
        for (const tag of ['summary', 'dl']) {
          const current = node.querySelector(tag);
          const updated = next.querySelector(tag);
          if (current.innerHTML !== updated.innerHTML) current.replaceChildren(...updated.childNodes);
        }
      } else if (node.innerHTML !== next.innerHTML) {
        node.replaceChildren(...next.childNodes);
        if (key !== 'empty') highlight(node);
      }
      if (node !== position) parent.insertBefore(node, position);
      position = node.nextElementSibling;
    }
    for (const node of existing.values()) node.remove();
  }

  function applySnapshot(data) {
    if (!data.stats || !parts.every(name => typeof data.parts?.[name] === 'string')) {
      throw new Error('Incomplete overview');
    }
    const updatedAt = utcTime.format(new Date(data.updated_at));
    const fragments = parts.filter(name => previousMarkup.get(name) !== data.parts[name])
      .map(name => parsePart(name, data.parts[name]));
    for (const part of fragments) {
      updatePart(part);
      previousMarkup.set(part.name, data.parts[part.name]);
    }
    for (const element of page.querySelectorAll('[data-stat]')) {
      const key = element.dataset.stat;
      const value = data.stats[key];
      if (typeof value !== 'number') continue;
      const next = formatCount.format(value);
      if (element.textContent !== next) {
        element.textContent = next;
        highlight(element);
      }
      if (key === 'failed') element.dataset.attention = String(value > 0);
    }
    find('#ov-write-notice').hidden = data.sync_enabled;
    applyFilter();
    checked.textContent = `Updated ${updatedAt} UTC`;
  }

  function schedule(delay = interval) {
    clearTimeout(timer);
    if (!paused && !document.hidden) timer = setTimeout(() => poll(), delay);
  }

  function stopRequest() {
    clearTimeout(timer);
    activeRequest?.abort();
    activeRequest = null;
    refresh.removeAttribute('aria-busy');
  }

  async function poll(manual = false) {
    if (activeRequest || document.hidden || (paused && !manual)) return;
    clearTimeout(timer);
    const request = new AbortController();
    activeRequest = request;
    const timeout = setTimeout(() => request.abort(), 10000);
    if (manual) refresh.setAttribute('aria-busy', 'true');
    try {
      const response = await fetch(page.dataset.feed, {
        headers: { Accept: 'application/json' }, cache: 'no-store', signal: request.signal,
      });
      if (activeRequest !== request) return;
      if (response.status === 401 || response.status === 403) {
        paused = true;
        toggle.hidden = true;
        signIn.hidden = response.status !== 401;
        showState('retry', response.status === 401 ? 'Session expired' : 'Access denied');
        return;
      }
      if (!response.ok) throw new Error('Refresh failed');
      const snapshot = await response.json();
      if (activeRequest !== request) return;
      applySnapshot(snapshot);
      if (toggle.hidden) {
        // A manual refresh can recover after the session is restored in another tab.
        paused = false;
        toggle.hidden = false;
        toggle.textContent = 'Pause updates';
        signIn.hidden = true;
      }
      showState(paused ? 'paused' : 'live', paused ? 'Updates paused' : 'Live · every 4s');
    } catch {
      if (activeRequest !== request) return;
      showState('retry', paused ? 'Refresh failed · paused' : 'Disconnected · retrying');
    } finally {
      clearTimeout(timeout);
      if (activeRequest === request) {
        activeRequest = null;
        refresh.removeAttribute('aria-busy');
        schedule();
      }
    }
  }

  toggle.hidden = false;
  refresh.hidden = false;
  refresh.addEventListener('click', () => poll(true));
  toggle.addEventListener('click', () => {
    paused = !paused;
    stopRequest();
    toggle.textContent = paused ? 'Resume updates' : 'Pause updates';
    showState(paused ? 'paused' : 'initial', paused ? 'Updates paused' : 'Connecting…');
    if (!paused) poll();
  });

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) stopRequest();
    else if (!paused) poll();
  });
  window.addEventListener('pagehide', stopRequest);
  window.addEventListener('pageshow', event => { if (event.persisted && !paused) poll(); });

  const links = Array.from(page.querySelectorAll('.ov-nav a'));
  const sections = links.map(link => document.getElementById(link.hash.slice(1)));
  function updateNavigation() {
    const edge = window.matchMedia('(max-width: 900px)').matches ? 96 : 32;
    let active = sections[0];
    for (const section of sections) {
      const top = section.getBoundingClientRect().top;
      if (top > edge) continue;
      // At desktop widths health and flow share a row. Honor the explicit link.
      if (top > active.getBoundingClientRect().top || location.hash === '#' + section.id) active = section;
    }
    for (const link of links) {
      if (link.hash === '#' + active.id) link.setAttribute('aria-current', 'location');
      else link.removeAttribute('aria-current');
    }
  }
  let frame;
  function queueNavigation() {
    if (frame) return;
    frame = requestAnimationFrame(() => { frame = null; updateNavigation(); });
  }
  window.addEventListener('scroll', queueNavigation, { passive: true });
  window.addEventListener('resize', queueNavigation);
  window.addEventListener('hashchange', queueNavigation);
  updateNavigation();
  showState('initial', 'Connecting…');
  poll();
})();
