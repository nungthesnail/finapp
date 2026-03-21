<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useAuth } from '../../app/providers/auth-provider'
import { useAiChat } from '../../features/ai/use-ai-chat'

const auth = useAuth()
const ai = useAiChat()

const newChat = reactive({ title: '' })
const messageInput = ref('')
const selectedChatId = ref('')
const selectedModel = ref('')

watch(
  () => auth.state.user,
  async (user) => {
    try {
      await ai.ensureAiLoaded(user)
      selectedChatId.value = ai.state.selectedChatId
      selectedModel.value = ai.state.selectedModel
    } catch (error) {
      // state.error already set in feature
    }
  },
  { immediate: true }
)

watch(
  () => ai.state.selectedChatId,
  (value) => {
    selectedChatId.value = value
  }
)

watch(
  () => ai.state.selectedModel,
  (value) => {
    selectedModel.value = value
  }
)

const usage = computed(() => ai.state.lastUsage || null)

async function onSelectChat() {
  if (!selectedChatId.value) return
  await ai.selectChat(selectedChatId.value)
}

function onSelectModel() {
  ai.setSelectedModel(selectedModel.value)
}

async function onCreateChat() {
  await ai.createChat(newChat.title)
  newChat.title = ''
}

async function onSendMessage() {
  if (!messageInput.value.trim()) return

  try {
    await ai.sendMessage(messageInput.value)
    messageInput.value = ''
  } catch (error) {
    // state.error already available
  }
}
</script>

<template>
  <section class="card page">
    <h1>AI-чат</h1>
    <p>{{ ai.state.status }}</p>
    <p v-if="ai.state.error" class="error-text">{{ ai.state.error }}</p>

    <div class="row">
      <select v-model="selectedChatId" @change="onSelectChat">
        <option value="" disabled>Выберите чат</option>
        <option v-for="chat in ai.state.chats" :key="chat.id" :value="String(chat.id)">
          #{{ chat.id }} {{ chat.title }}
        </option>
      </select>

      <select v-model="selectedModel" @change="onSelectModel">
        <option v-for="model in ai.state.models" :key="model.code" :value="model.code">
          {{ model.name }} ({{ model.code }})
        </option>
      </select>
    </div>

    <form class="row" @submit.prevent="onCreateChat">
      <input v-model="newChat.title" placeholder="Название нового чата" />
      <button type="submit">Создать чат</button>
    </form>

    <div class="log">
      <div v-for="message in ai.state.messages" :key="message.id">
        <strong>{{ message.role }}:</strong>
        <span>{{ message.content }}</span>
        <small v-if="message.model"> ({{ message.model }})</small>
      </div>
      <div v-if="ai.state.streamingText"><strong>ассистент:</strong> {{ ai.state.streamingText }}</div>
    </div>

    <form class="row" @submit.prevent="onSendMessage">
      <input v-model="messageInput" placeholder="Спросить AI" :disabled="ai.state.streaming" />
      <button type="submit" :disabled="ai.state.streaming">Отправить</button>
    </form>

    <section v-if="usage" class="card row">
      <div><strong>Входные токены:</strong> {{ usage.input_tokens }}</div>
      <div><strong>Выходные токены:</strong> {{ usage.output_tokens }}</div>
      <div><strong>Кэш:</strong> {{ usage.cached_input_tokens }}</div>
      <div><strong>Стоимость (RUB):</strong> {{ usage.total_cost_rub }}</div>
      <div><strong>Баланс после:</strong> {{ usage.balance_after_rub }}</div>
    </section>
  </section>
</template>
