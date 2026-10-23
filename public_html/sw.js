const CACHE_PREFIX = "hedefakademi-";
const CACHE = "hedefakademi-2026-08-31-5-0-hedef-ai";
const STATIC_ASSETS = [
  "/manifest.webmanifest",
  "/baris.webp",
  "/hedef-icon.png",
  "/assets/student-content-a1c2-ai-v2.js",
  "/assets/learning-journey-v2.css?v=20260831-2",
  "/assets/learning-journey-v2.js?v=20260831-2",
  "/assets/student-route-v5.css?v=20260831-5",
  "/assets/student-route-v5.js?v=20260831-5"
];
self.addEventListener("install", (event) => {
  event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(STATIC_ASSETS)).catch(() => undefined));
  self.skipWaiting();
});
self.addEventListener("activate", (event) => {
  event.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((key) => key.startsWith(CACHE_PREFIX) && key !== CACHE).map((key) => caches.delete(key)))));
  self.clients.claim();
});
self.addEventListener("fetch", (event) => {
  const request = event.request;
  if (request.method !== "GET") return;
  const url = new URL(request.url);
  if (url.origin !== location.origin || url.pathname.startsWith("/api/") || url.pathname.includes("signout")) return;
  if (request.mode === "navigate") {
    event.respondWith(fetch(new Request(request, { cache: "no-store" })));
    return;
  }
  if (!url.pathname.startsWith("/_next/") && !url.pathname.startsWith("/assets/") && !STATIC_ASSETS.includes(url.pathname)) return;
  event.respondWith(fetch(request, { cache: "no-cache" }).then((response) => {
    if (response.ok) {
      const copy = response.clone();
      event.waitUntil(caches.open(CACHE).then((cache) => cache.put(request, copy)));
    }
    return response;
  }).catch(() => caches.match(request).then((cached) => cached || Response.error())));
});
