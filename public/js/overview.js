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
  const dialog = find('#ov-detail-dialog');
  const detailTitle = find('#ov-detail-title');
  const detailSummary = find('#ov-detail-summary');
  const detailContent = find('#ov-detail-content');
  const customers = find('#ov-customers');
  const count = find('#ov-result-count');
  const noMatch = find('#ov-no-match');
  const formatCount = new Intl.NumberFormat(document.documentElement.lang || 'en');
  const utcTime = new Intl.DateTimeFormat('en-GB', {
    timeZone: 'UTC', hour: '2-digit', minute: '2-digit', second: '2-digit',
  });
  const parts = ['health', 'balances', 'customers', 'events', 'stores', 'runs'];
  const previousMarkup = new Map();
  const hasDetails = dialog && typeof dialog.showModal === 'function';
  const interval = 4000;
  let paused = false;
  let timer;
  let activeRequest;
  let lastDetailTrigger;
  let lastDetailKey;
  let lastDetailHeading;
  const sectionNav = find('.ov-nav');

  function setupStatCarousel() {
    const track = find('#ov-stat-cards');
    const pager = find('.ov-stat-pager');
    const cards = Array.from(track.children);
    const nextButton = pager.querySelector('.ov-next-stat');
    const position = pager.querySelector('.ov-stat-position');
    const phone = window.matchMedia('(max-width: 520px)');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let current = -1;

    function distanceFromCenter(card) {
      const slide = card.getBoundingClientRect();
      const viewport = track.getBoundingClientRect();
      return slide.left - viewport.left + (slide.width - viewport.width) / 2;
    }

    nextButton.addEventListener('click', () => {
      const next = (current + 1) % cards.length;
      track.scrollBy({
        left: distanceFromCenter(cards[next]),
        behavior: next === 0 || reducedMotion.matches ? 'auto' : 'smooth',
      });
    });

    function updatePosition() {
      const distances = cards.map(card => Math.abs(distanceFromCenter(card)));
      const nearest = distances.indexOf(Math.min(...distances));
      if (nearest === current) return;
      position.textContent = `Stat ${nearest + 1} of ${cards.length}`;
      nextButton.textContent = nearest === cards.length - 1 ? 'First stat' : 'Next stat';
      current = nearest;
    }

    function updateLayout() {
      pager.hidden = !phone.matches;
      if (phone.matches) {
        track.closest('section').setAttribute('aria-roledescription', 'carousel');
        track.addEventListener('scroll', updatePosition, { passive: true });
        updatePosition();
      } else {
        track.closest('section').removeAttribute('aria-roledescription');
        track.removeEventListener('scroll', updatePosition);
      }
    }

    phone.addEventListener('change', updateLayout);
    updateLayout();
  }

  setupStatCarousel();

  if (hasDetails) {
    page.querySelectorAll('[data-detail-open]').forEach(button => { button.hidden = false; });
    page.addEventListener('click', event => {
      const trigger = event.target.closest('[data-detail-open]');
      if (trigger) {
        const template = trigger.parentElement.querySelector('template.ov-detail-template');
        if (!template) return;
        lastDetailTrigger = trigger;
        lastDetailKey = trigger.closest('[data-key]')?.dataset.key || null;
        lastDetailHeading = trigger.closest('.ov-panel')?.querySelector('.ov-panel-heading h2') || null;
        detailTitle.textContent = trigger.dataset.detailTitle || 'Details';
        detailSummary.textContent = trigger.dataset.detailSummary || '';
        detailContent.replaceChildren(template.content.cloneNode(true));
        dialog.showModal();
        return;
      }
      if (event.target.closest('[data-close-details]')) dialog.close();
    });
    dialog.addEventListener('click', event => {
      if (event.target === dialog) dialog.close();
    });
    dialog.addEventListener('close', () => {
      const row = lastDetailKey
        ? Array.from(lastDetailHeading?.closest('.ov-panel')?.querySelectorAll('[data-key]') || [])
          .find(item => item.dataset.key === lastDetailKey)
        : null;
      const restoreTarget = lastDetailTrigger?.isConnected
        ? lastDetailTrigger
        : row?.querySelector('[data-detail-open]') || lastDetailHeading;
      if (restoreTarget) {
        if (restoreTarget.matches('.ov-panel-heading h2')) restoreTarget.setAttribute('tabindex', '-1');
        restoreTarget.focus({ preventScroll: true });
      }
      lastDetailTrigger = null;
      lastDetailKey = null;
      lastDetailHeading = null;
    });
  }

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
        if (hasDetails) node.querySelectorAll('[data-detail-open]').forEach(button => { button.hidden = false; });
        if (key !== 'empty') highlight(node);
      } else if (name === 'health') {
        // Keep service cards and detail controls stable while their live values update.
        const currentBadge = node.querySelector('.badge');
        const nextBadge = next.querySelector('.badge');
        currentBadge.className = nextBadge.className;
        currentBadge.textContent = nextBadge.textContent;
        node.querySelector('.ov-health-hint').textContent = next.querySelector('.ov-health-hint').textContent;
        node.querySelector('.ov-detail-template').innerHTML = next.querySelector('.ov-detail-template').innerHTML;
      } else if (node.innerHTML !== next.innerHTML) {
        const restoreDetailFocus = node.contains(document.activeElement)
          && document.activeElement.matches('[data-detail-open]');
        node.replaceChildren(...next.childNodes);
        if (hasDetails) node.querySelectorAll('[data-detail-open]').forEach(button => { button.hidden = false; });
        if (restoreDetailFocus) node.querySelector('[data-detail-open]')?.focus({ preventScroll: true });
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
    const failedMetric = find('#ov-metric-failed');
    if (failedMetric) failedMetric.dataset.state = Number(data.stats.failed) > 0 ? 'attention' : 'clear';
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
  const mobileHeader = document.querySelector('.mobile-topbar');
  function updateNavigation() {
    const stickyTop = parseFloat(getComputedStyle(sectionNav).top);
    sectionNav.classList.toggle('is-pinned', sectionNav.getBoundingClientRect().top <= stickyTop + 1);
    const edge = sectionNav.getBoundingClientRect().bottom + 20;
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
  const navSize = new ResizeObserver(entries => {
    for (const { target } of entries) {
      const property = target === sectionNav ? '--ov-nav-height' : '--ov-mobile-header-height';
      page.style.setProperty(property, `${target.offsetHeight}px`);
    }
    queueNavigation();
  });
  navSize.observe(sectionNav);
  if (mobileHeader) navSize.observe(mobileHeader);
  window.addEventListener('scroll', queueNavigation, { passive: true });
  window.addEventListener('resize', queueNavigation);
  window.addEventListener('hashchange', queueNavigation);
  updateNavigation();
  showState('initial', 'Connecting…');
  poll();
})();
