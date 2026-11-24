(() => {
  const page = document.getElementById('mapping-page');
  if (!page) return;
  const toolbar = page.querySelector('[data-mapping-toolbar]');
  const search = page.querySelector('#mapping-search');
  const clear = page.querySelector('.mapping-search-clear');
  const rows = [...page.querySelectorAll('[data-mapping-row]')].map(element => ({ element, text: element.textContent.toLocaleLowerCase('en'), status: element.dataset.routing }));
  const filters = [...page.querySelectorAll('[data-mapping-filter]')];
  const previous = page.querySelector('[data-mapping-previous]');
  const next = page.querySelector('[data-mapping-next]');
  const count = page.querySelector('#mapping-result-count');
  const pageCount = page.querySelector('[data-mapping-page-count]');
  const noResults = page.querySelector('[data-mapping-no-results]');
  const size = 10;
  let filter = 'all';
  let currentPage = 1;
  let composing = false;

  function readUrl() {
    const query = new URL(location.href).searchParams;
    search.value = query.get('mapping_q') || '';
    filter = ['all', 'ready', 'held'].includes(query.get('mapping_status')) ? query.get('mapping_status') : 'all';
    currentPage = Math.max(1, Number.parseInt(query.get('mapping_page'), 10) || 1);
  }
  function saveUrl() {
    const url = new URL(location.href);
    const params = { mapping_q: search.value.trim(), mapping_status: filter === 'all' ? '' : filter, mapping_page: currentPage > 1 ? String(currentPage) : '' };
    Object.entries(params).forEach(([key, value]) => value ? url.searchParams.set(key, value) : url.searchParams.delete(key));
    if (url.href !== location.href) history.replaceState(null, '', url);
  }
  function render(persist = true) {
    const query = search.value.trim().toLocaleLowerCase('en');
    const matching = rows.filter(row => (filter === 'all' || row.status === filter) && row.text.includes(query));
    const pages = Math.max(1, Math.ceil(matching.length / size));
    currentPage = Math.min(currentPage, pages);
    const start = (currentPage - 1) * size;
    const visible = new Set(matching.slice(start, start + size));
    rows.forEach(row => { row.element.hidden = !visible.has(row); });
    noResults.hidden = matching.length > 0 || rows.length === 0;
    count.textContent = matching.length ? `${start + 1}–${Math.min(start + size, matching.length)} of ${matching.length} branches` : '0 branches';
    pageCount.textContent = `Page ${currentPage} of ${pages}`;
    previous.disabled = currentPage === 1;
    next.disabled = currentPage === pages;
    clear.hidden = search.value.length === 0;
    filters.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.mappingFilter === filter)));
    if (persist) saveUrl();
  }
  function updateSearch() { if (composing) return; currentPage = 1; render(); }
  search.addEventListener('compositionstart', () => { composing = true; });
  search.addEventListener('compositionend', () => { composing = false; updateSearch(); });
  search.addEventListener('input', updateSearch);
  search.addEventListener('keydown', event => { if (event.key === 'Enter' && !event.isComposing) updateSearch(); });
  clear.addEventListener('click', () => { search.value = ''; currentPage = 1; render(); search.focus(); });
  filters.forEach(button => button.addEventListener('click', () => { filter = button.dataset.mappingFilter; currentPage = 1; render(); }));
  page.querySelector('[data-mapping-reset]').addEventListener('click', () => { search.value = ''; filter = 'all'; currentPage = 1; render(); search.focus(); });
  function turnPage(delta) {
    currentPage += delta;
    render();
    const heading = page.querySelector('#branch-routes-title');
    heading.focus({ preventScroll: true });
    heading.scrollIntoView({ behavior: 'instant', block: 'start' });
  }
  previous.addEventListener('click', () => turnPage(-1));
  next.addEventListener('click', () => turnPage(1));
  window.addEventListener('popstate', () => { readUrl(); render(false); });
  page.querySelectorAll('[data-mapping-controls]').forEach(control => { control.hidden = false; });
  readUrl();
  render();

  const links = [...toolbar.querySelectorAll('nav a')];
  const sections = links.map(link => document.getElementById(link.hash.slice(1)));
  function updateNavigation() {
    const edge = toolbar.getBoundingClientRect().bottom + 24;
    let active = sections[0];
    sections.forEach(section => {
      if (section.getBoundingClientRect().top > edge) return;
      if (section.getBoundingClientRect().top > active.getBoundingClientRect().top || location.hash === '#' + section.id) active = section;
    });
    // The last panels may not be tall enough to reach the sticky bar.
    if (window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 4) {
      const last = sections.at(-1);
      active = sections.find(section => location.hash === '#' + section.id && Math.abs(section.getBoundingClientRect().top - last.getBoundingClientRect().top) < 1) || last;
    }
    links.forEach(link => link.hash === '#' + active.id ? link.setAttribute('aria-current', 'location') : link.removeAttribute('aria-current'));
  }
  let frame;
  function scheduleNavigation() {
    if (frame) return;
    frame = requestAnimationFrame(() => { frame = null; updateNavigation(); });
  }
  const mobileHeader = document.querySelector('.mobile-topbar');
  const resize = new ResizeObserver(() => {
    page.style.setProperty('--mapping-toolbar-height', `${toolbar.offsetHeight}px`);
    page.style.setProperty('--mapping-mobile-height', `${mobileHeader?.offsetHeight || 0}px`);
    scheduleNavigation();
  });
  resize.observe(toolbar);
  if (mobileHeader) resize.observe(mobileHeader);
  window.addEventListener('scroll', scheduleNavigation, { passive: true });
  window.addEventListener('hashchange', scheduleNavigation);
  updateNavigation();
})();
