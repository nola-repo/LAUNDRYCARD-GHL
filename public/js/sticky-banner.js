/* Shared measurement for compact banners beneath the responsive app shell. */
(() => {
  document.querySelectorAll('[data-sticky-page]').forEach(page => {
    const banner = page.querySelector('[data-sticky-banner]');
    if (!banner) return;
    const mobileHeader = document.querySelector('.mobile-topbar');
    const update = () => {
      page.style.setProperty('--sticky-banner-height', `${banner.offsetHeight}px`);
      page.style.setProperty('--sticky-topbar-height', `${mobileHeader?.offsetHeight || 0}px`);
      page.dispatchEvent(new Event('sticky-banner-resize'));
    };
    const resize = new ResizeObserver(update);
    resize.observe(banner);
    if (mobileHeader) resize.observe(mobileHeader);
    update();
  });
})();
