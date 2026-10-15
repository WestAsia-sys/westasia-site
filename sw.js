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

self.addEventListener("notificationclick", (e) => {
  e.notification.close();
  const url = (e.notification.data && e.notification.data.url) || "/#developments";
  e.waitUntil(self.clients.matchAll({ type: "window", includeUncontrolled: true }).then((wins) => {
    for (const w of wins) {
      if (w.url.startsWith(self.location.origin)) { w.navigate(url); return w.focus(); }
    }
    return self.clients.openWindow(url);
  }));
});
