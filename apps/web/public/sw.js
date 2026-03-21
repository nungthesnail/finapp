const CACHE_NAME = 'finwise-cache-v1'
const OFFLINE_ASSETS = ['/', '/manifest.webmanifest', '/vite.svg', '/offline.html', '/landing.html', '/privacy.html', '/help.html']

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(OFFLINE_ASSETS))
  )
  self.skipWaiting()
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)))
    )
  )
  self.clients.claim()
})

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') return
  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request).catch(() => caches.match('/offline.html'))
    )
    return
  }

  event.respondWith(
    caches.match(event.request).then((cached) => {
      if (cached) return cached
      return fetch(event.request).catch(() => caches.match('/offline.html'))
    })
  )
})

self.addEventListener('push', (event) => {
  const payload = event.data ? event.data.json() : {}
  const title = payload.title || 'FinWiseAi'
  const options = {
    body: payload.body || 'У вас новое уведомление',
    data: payload.data || {},
  }
  event.waitUntil(self.registration.showNotification(title, options))
})
