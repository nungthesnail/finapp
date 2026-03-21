import { reactive, readonly } from 'vue'
import { apiFetch } from '../../shared/api/client'

const state = reactive({
  loading: false,
  loadedUserId: null,
  error: '',
  dashboard: null,
  users: [],
  auditLogs: [],
  aiModels: [],
  supportChats: [],
  selectedSupportChatId: '',
  supportMessages: [],
})

function resetForUser(user) {
  if (!user || user.role !== 'ADMIN') {
    state.loading = false
    state.loadedUserId = null
    state.error = ''
    state.dashboard = null
    state.users = []
    state.auditLogs = []
    state.aiModels = []
    state.supportChats = []
    state.selectedSupportChatId = ''
    state.supportMessages = []
  }
}

async function loadDashboard() {
  state.dashboard = await apiFetch('/admin/dashboard')
}

async function loadUsers() {
  const data = await apiFetch('/admin/users')
  state.users = data.items || []
}

async function adjustUserCredit(userId, amount, description = '') {
  await apiFetch(`/admin/users/${userId}/credit-adjustment`, {
    method: 'POST',
    body: JSON.stringify({ amount: Number(amount), description }),
  })
  await loadDashboard()
}

async function cancelSubscription(subscriptionId) {
  await apiFetch(`/admin/subscriptions/${subscriptionId}/cancel`, { method: 'POST' })
  await loadDashboard()
}

async function loadAuditLogs() {
  const data = await apiFetch('/admin/audit-logs')
  state.auditLogs = data.items || []
}

async function loadAiModels() {
  const data = await apiFetch('/admin/ai/models')
  state.aiModels = data.items || []
}

async function createAiModel(payload) {
  await apiFetch('/admin/ai/models', {
    method: 'POST',
    body: JSON.stringify(payload),
  })
  await loadAiModels()
}

async function updateAiModel(id, payload) {
  await apiFetch(`/admin/ai/models/${id}`, {
    method: 'PUT',
    body: JSON.stringify(payload),
  })
  await loadAiModels()
}

async function loadSupportChats() {
  const data = await apiFetch('/admin/support/chats')
  state.supportChats = data.items || []
}

async function selectSupportChat(chatId) {
  state.selectedSupportChatId = String(chatId)
  const data = await apiFetch(`/admin/support/chats/${chatId}/messages`)
  state.supportMessages = data.items || []
}

async function sendSupportMessage(content, status = '') {
  if (!state.selectedSupportChatId) {
    return
  }

  await apiFetch(`/admin/support/chats/${state.selectedSupportChatId}/messages`, {
    method: 'POST',
    body: JSON.stringify({ content, status: status || null }),
  })

  await selectSupportChat(state.selectedSupportChatId)
  await loadSupportChats()
}

async function ensureAdminLoaded(user) {
  if (!user || user.role !== 'ADMIN') {
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

  try {
    await Promise.all([loadDashboard(), loadUsers(), loadAuditLogs(), loadAiModels(), loadSupportChats()])
    if (state.supportChats.length > 0) {
      await selectSupportChat(state.supportChats[0].id)
    }
    state.loadedUserId = user.id
  } catch (error) {
    state.error = error.message || 'Не удалось загрузить данные админки'
    throw error
  } finally {
    state.loading = false
  }
}

export function useAdmin() {
  return {
    state: readonly(state),
    ensureAdminLoaded,
    loadDashboard,
    loadUsers,
    adjustUserCredit,
    cancelSubscription,
    loadAuditLogs,
    loadAiModels,
    createAiModel,
    updateAiModel,
    loadSupportChats,
    selectSupportChat,
    sendSupportMessage,
  }
}
