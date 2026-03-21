import { reactive, readonly } from 'vue'
import { apiFetch } from '../../shared/api/client'

const state = reactive({
  loading: false,
  loadedUserId: null,
  status: '',
  error: '',
  items: [],
  pushStatus: '',
})

function resetForUser(user) {
  if (!user) {
    state.loading = false
    state.loadedUserId = null
    state.status = 'Не авторизован'
    state.error = ''
    state.items = []
    state.pushStatus = ''
    return
  }

  state.status = `Авторизован: ${user.phone}`
}

async function loadNotifications() {
  const data = await apiFetch('/notifications')
  state.items = data.items || []
}

async function markRead(id) {
  await apiFetch(`/notifications/${id}/read`, { method: 'PATCH' })
  await loadNotifications()
}

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')
  const rawData = atob(base64)
  const outputArray = new Uint8Array(rawData.length)
  for (let i = 0; i < rawData.length; i += 1) {
    outputArray[i] = rawData.charCodeAt(i)
  }
  return outputArray
}

async function subscribePush() {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
    throw new Error('Push API не поддерживается в этом браузере')
  }

  const registration = await navigator.serviceWorker.ready
  const permission = await window.Notification.requestPermission()
  if (permission !== 'granted') {
    throw new Error('Доступ к push-уведомлениям отклонен')
  }

  const vapidKey = import.meta.env.VITE_VAPID_PUBLIC_KEY || ''
  const options = vapidKey
    ? { userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(vapidKey) }
    : { userVisibleOnly: true }

  const subscription = await registration.pushManager.subscribe(options)
  await apiFetch('/push/subscriptions', {
    method: 'POST',
    body: JSON.stringify(subscription.toJSON()),
  })

  state.pushStatus = 'Подписка на push сохранена'
}

async function ensureNotificationsLoaded(user) {
  if (!user) {
    resetForUser(user)
    return
  }

  if (state.loading) {
    return
  }

  if (state.loadedUserId === user.id) {
    return
  }

  state.loading = true
  state.error = ''
  resetForUser(user)

  try {
    await loadNotifications()
    state.loadedUserId = user.id
  } catch (error) {
    state.error = error.message || 'Не удалось загрузить уведомления'
    throw error
  } finally {
    state.loading = false
  }
}

export function useNotifications() {
  return {
    state: readonly(state),
    ensureNotificationsLoaded,
    loadNotifications,
    markRead,
    subscribePush,
  }
}
