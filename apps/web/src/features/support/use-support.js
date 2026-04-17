import { reactive, readonly } from 'vue'
import { apiFetch } from '../../shared/api/client'

const state = reactive({
  loading: false,
  loadedUserId: null,
  status: '',
  error: '',
  chats: [],
  selectedChatId: '',
  messages: [],
})

function resetForUser(user) {
  if (!user) {
    state.loading = false
    state.loadedUserId = null
    state.status = 'Не авторизован'
    state.error = ''
    state.chats = []
    state.selectedChatId = ''
    state.messages = []
    return
  }

  state.status = `Авторизован: ${user.phone}`
}

async function loadChats() {
  const data = await apiFetch('/support/chats')
  state.chats = data.items || []
}

async function selectChat(chatId) {
  state.selectedChatId = String(chatId)
  const data = await apiFetch(`/support/chats/${chatId}/messages`)
  state.messages = data.items || []
}

function setSelectedChatId(chatId) {
  state.selectedChatId = String(chatId || '')
}

async function createChat(subject) {
  const data = await apiFetch('/support/chats', {
    method: 'POST',
    body: JSON.stringify({ subject }),
  })

  await loadChats()
  await selectChat(data.item.id)
}

async function sendMessage(content) {
  if (!state.selectedChatId) return

  await apiFetch(`/support/chats/${state.selectedChatId}/messages`, {
    method: 'POST',
    body: JSON.stringify({ content }),
  })

  await selectChat(state.selectedChatId)
  await loadChats()
}

async function ensureSupportLoaded(user) {
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
    await loadChats()
    if (state.chats.length > 0) {
      await selectChat(state.chats[0].id)
    }
    state.loadedUserId = user.id
  } catch (error) {
    state.error = error.message || 'Не удалось загрузить чат поддержки'
    throw error
  } finally {
    state.loading = false
  }
}

export function useSupport() {
  return {
    state: readonly(state),
    ensureSupportLoaded,
    loadChats,
    setSelectedChatId,
    selectChat,
    createChat,
    sendMessage,
  }
}
