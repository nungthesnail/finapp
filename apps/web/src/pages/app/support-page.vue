<script setup>
import { reactive, ref, watch } from 'vue'
import { useAuth } from '../../app/providers/auth-provider'
import { useSupport } from '../../features/support/use-support'

const auth = useAuth()
const support = useSupport()
const selectedChatId = ref('')

const form = reactive({ newChatSubject: '', message: '' })

watch(
  () => auth.state.user,
  async (user) => {
    try {
      await support.ensureSupportLoaded(user)
      selectedChatId.value = support.state.selectedChatId
    } catch (error) {
      // state.error is reactive
    }
  },
  { immediate: true }
)

watch(
  () => support.state.selectedChatId,
  (value) => {
    selectedChatId.value = value
  }
)

async function onCreateChat() {
  if (!form.newChatSubject.trim()) return
  await support.createChat(form.newChatSubject.trim())
  form.newChatSubject = ''
}

async function onSendMessage() {
  if (!form.message.trim()) return
  await support.sendMessage(form.message)
  form.message = ''
}

async function onSelectChat() {
  if (!selectedChatId.value) return
  support.setSelectedChatId(selectedChatId.value)
  await support.selectChat(selectedChatId.value)
}
</script>

<template>
  <section class="card page">
    <h1>Поддержка</h1>
    <p>{{ support.state.status }}</p>
    <p v-if="support.state.error" class="error-text">{{ support.state.error }}</p>

    <form class="row" @submit.prevent="onCreateChat">
      <input v-model="form.newChatSubject" placeholder="Тема тикета/заявки" />
      <button type="submit">Создать тикет</button>
    </form>

    <div class="row">
      <select v-model="selectedChatId" @change="onSelectChat">
        <option value="" disabled>Выберите чат поддержки</option>
        <option v-for="chat in support.state.chats" :key="chat.id" :value="String(chat.id)">
          #{{ chat.id }} {{ chat.status }} · {{ chat.subject || 'Без темы' }}
        </option>
      </select>
      <button type="button" @click="support.loadChats">Обновить чаты</button>
    </div>

    <p v-if="support.state.loading">Загрузка...</p>
    <p v-else-if="support.state.chats.length === 0">Чатов поддержки пока нет.</p>

    <div class="log" aria-live="polite">
      <div v-for="message in support.state.messages" :key="message.id">
        <strong>{{ message.sender_role }}:</strong> {{ message.content }}
      </div>
    </div>

    <form class="row" @submit.prevent="onSendMessage">
      <input v-model="form.message" placeholder="Напишите сообщение в поддержку" />
      <button type="submit">Отправить</button>
    </form>
  </section>
</template>

