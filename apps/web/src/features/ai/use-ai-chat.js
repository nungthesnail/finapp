import { reactive, readonly } from 'vue'
import { apiFetch, apiStream } from '../../shared/api/client'

const state = reactive({
  loading: false,
  loadedUserId: null,
  status: '',
  chats: [],
  models: [],
  selectedChatId: '',
  selectedModel: '',
  messages: [],
  streamingText: '',
  streaming: false,
  lastUsage: null,
  error: '',
})

function setStatus(message) {
  state.status = message
}

function resetForUser(user) {
  if (!user) {
    state.loading = false
    state.loadedUserId = null
    state.status = 'Не авторизован'
    state.chats = []
    state.models = []
    state.selectedChatId = ''
    state.selectedModel = ''
    state.messages = []
    state.streamingText = ''
    state.streaming = false
    state.lastUsage = null
    state.error = ''
    return
  }

  setStatus(`Авторизован: ${user.phone}`)
}

function resolveDefaultModel() {
  return state.models[0]?.code || 'gpt-4.1-mini'
}

function syncSelectedModelByChat(chatId) {
  const selectedChat = state.chats.find((item) => String(item.id) === String(chatId))
  if (selectedChat?.selected_model) {
    state.selectedModel = selectedChat.selected_model
    return
  }

  if (!state.selectedModel) {
    state.selectedModel = resolveDefaultModel()
  }
}

function setSelectedModel(modelCode) {
  state.selectedModel = modelCode || resolveDefaultModel()
}

async function loadModels() {
  const data = await apiFetch('/ai/models')
  state.models = data.items || []
  if (!state.selectedModel) {
    state.selectedModel = resolveDefaultModel()
  }
}

async function loadChats() {
  const list = await apiFetch('/ai/chats')
  state.chats = list.items || []
}

async function loadLastActiveChat() {
  const data = await apiFetch('/ai/chats/last-active')
  state.selectedChatId = String(data.item.id)
  state.messages = data.messages || []
  if (data.item?.selected_model) {
    state.selectedModel = data.item.selected_model
  }

  await loadChats()
}

async function selectChat(chatId) {
  state.selectedChatId = String(chatId)
  const data = await apiFetch(`/ai/chats/${chatId}/messages`)
  state.messages = data.items || []
  syncSelectedModelByChat(chatId)
}

async function createChat(title = '') {
  const payload = {
    title: title || `Чат ${new Date().toLocaleTimeString()}`,
    selected_model: state.selectedModel || resolveDefaultModel(),
  }

  const created = await apiFetch('/ai/chats', {
    method: 'POST',
    body: JSON.stringify(payload),
  })

  state.chats.unshift(created.item)
  await selectChat(created.item.id)
}

async function sendMessage(text) {
  const content = text.trim()
  if (!content || !state.selectedChatId) {
    return
  }

  state.error = ''
  state.streaming = true
  state.streamingText = ''
  state.lastUsage = null

  state.messages.push({
    id: `tmp-user-${Date.now()}`,
    role: 'user',
    content,
  })

  const response = await apiStream(`/ai/chats/${state.selectedChatId}/messages/stream`, {
    method: 'POST',
    body: JSON.stringify({
      message: content,
      model: state.selectedModel || resolveDefaultModel(),
    }),
  })

  const reader = response.body.getReader()
  const decoder = new TextDecoder()
  let buffer = ''

  while (true) {
    const { done, value } = await reader.read()
    if (done) break

    buffer += decoder.decode(value, { stream: true })
    const blocks = buffer.split('\n\n')
    buffer = blocks.pop() || ''

    for (const block of blocks) {
      const lines = block.split('\n')
      const eventLine = lines.find((line) => line.startsWith('event:'))
      const dataLine = lines.find((line) => line.startsWith('data:'))
      if (!eventLine || !dataLine) continue

      const event = eventLine.replace('event:', '').trim()
      const payload = JSON.parse(dataLine.replace('data:', '').trim())

      if (event === 'chunk') {
        state.streamingText += payload.text || ''
      }

      if (event === 'done') {
        if (payload.message) {
          state.messages.push(payload.message)
        }
        state.lastUsage = payload.usage || null
        state.streamingText = ''
      }
    }
  }

  state.streaming = false
  await loadChats()
  syncSelectedModelByChat(state.selectedChatId)
}

async function ensureAiLoaded(user) {
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
    await Promise.all([loadModels(), loadChats()])
    await loadLastActiveChat()
    state.loadedUserId = user.id
  } catch (error) {
    state.error = error.message || 'Не удалось загрузить состояние AI-чата'
    throw error
  } finally {
    state.loading = false
  }
}

export function useAiChat() {
  return {
    state: readonly(state),
    setStatus,
    resetForUser,
    ensureAiLoaded,
    loadModels,
    loadChats,
    loadLastActiveChat,
    setSelectedModel,
    selectChat,
    createChat,
    sendMessage,
  }
}
