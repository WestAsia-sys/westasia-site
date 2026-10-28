/* West Asia Report service worker: shows Developments alerts, even when the site is closed. */
self.addEventListener("install", () => self.skipWaiting());
self.addEventListener("activate", (e) => e.waitUntil(self.clients.claim()));

self.addEventListener("push", (e) => {
  let d = {};
  try { d = e.data ? e.data.json() : {}; } catch (_) { d = { title: "West Asia Report", body: e.data ? e.data.text() : "" }; }
  e.waitUntil(self.registration.showNotification(d.title || "West Asia Report", {
    body: d.body || "",
    tag: d.tag || undefined,          // a follow-up replaces the first alert for the same story
    renotify: !!d.tag,
    icon: "icons/icon-192.png",
    badge: "icons/badge-72.png",
    data: { url: d.url || "/#developments" }
  }));
});

// Tapping an alert opens that development: in the app if it's already open (the page
// loads the latest developments and pins this one), otherwise in a new window.
self.addEventListener("notificationclick", (e) => {
  e.notification.close();
  const url = new URL((e.notification.data && e.notification.data.url) || "/#developments", self.location.origin).href;
  e.waitUntil((async () => {
    const wins = await self.clients.matchAll({ type: "window", includeUncontrolled: true });
    const win = wins.find((w) => w.url.startsWith(self.location.origin));
    if (win) {
      try { await win.focus(); } catch (_) {}
      try { win.postMessage({ type: "open-url", url }); return; } catch (_) {}
      try { await win.navigate(url); return; } catch (_) {}
    }
    await self.clients.openWindow(url);
  })());
});
