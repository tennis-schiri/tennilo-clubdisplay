'use strict';
// TENNILO ClubDisplay | Copyright © 2026 TENNILO
function cdCreateAutoUpdater({build, safe, request, pageUrl, parseBuild, navigate}) {
  const valid = value => typeof value === 'string' && /^[a-f0-9]{40}$/.test(value);
  let pending = false, running = false, navigating = false;
  async function check(quick = false) {
    if (!valid(build) || running || navigating || (quick && (!pending || !safe()))) return;
    running = true;
    try {
      const current = new URL(pageUrl());
      const marker = new URL('/release.json', current);
      marker.searchParams.set('_', String(Date.now()));
      const options = {cache:'no-store', credentials:'same-origin', redirect:'error', signal:AbortSignal.timeout(8000)};
      const response = await request(marker.href, options);
      if (!response.ok) throw Error('release unavailable');
      const release = await response.json();
      if (!valid(release.build)) throw Error('invalid release');
      pending = release.build !== build;
      if (!pending || !safe()) return;
      current.searchParams.set('cd-release', release.build);
      // A stale marker, incomplete upload, or connectivity failure must not trigger navigation.
      const page = await request(current.href, {...options, signal:AbortSignal.timeout(8000)});
      if (!page.ok || parseBuild(await page.text()) !== release.build) throw Error('release not ready');
      if (!safe()) return;
      navigating = true;
      navigate(current.href);
    } catch {
      pending = false;
      navigating = false;
    } finally { running = false; }
  }
  return {check};
}
if (typeof module !== 'undefined' && module.exports) {
  module.exports = {cdCreateAutoUpdater};
} else {
  (() => {
    const build = document.querySelector('meta[name="clubdisplay-build"]')?.content;
    // The Fullscreen API cannot be re-entered without user activation after navigation.
    // Chrome F11 and --kiosk are browser-level modes and do not set fullscreenElement.
    const safe = () => navigator.onLine !== false &&
      document.visibilityState === 'visible' && !document.fullscreenElement &&
      typeof view !== 'undefined' && view === 'info' && !infoPaused &&
      !adminDialog.open && !document.getElementById('nuscore-demo-frame') && !(typeof premiereActive!=='undefined'&&premiereActive) && !document.getElementById('personal-demo-dialog')?.open && !(typeof personalDemo!=='undefined'&&personalDemo) && Date.now() - last >= idle;
    const updater = cdCreateAutoUpdater({
      build, safe, request: (...args) => fetch(...args),
      pageUrl: () => location.href,
      parseBuild: html => new DOMParser().parseFromString(html, 'text/html').querySelector('meta[name="clubdisplay-build"]')?.content,
      navigate: url => location.replace(url)
    });
    setInterval(() => updater.check(), 300000);
    setInterval(() => updater.check(true), 10000);
  })();
}
