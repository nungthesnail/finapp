<script setup>
import { reactive, ref, watch } from 'vue'
import { useAuth } from '../../app/providers/auth-provider'
import { useAdmin } from '../../features/admin/use-admin'

const auth = useAuth()
const admin = useAdmin()

const selectedChatId = ref('')
const form = reactive({ message: '', status: '' })

watch(
  () => auth.state.user,
  async (user) => {
    try {
      await admin.ensureAdminLoaded(user)
      selectedChatId.value = admin.state.selectedSupportChatId
    } catch (error) {
      // state.error is reactive
    }
  },
  { immediate: true }
)

watch(
  () => admin.state.selectedSupportChatId,
  (value) => {
    selectedChatId.value = value
  }
)

async function onSelectChat() {
  if (!selectedChatId.value) return
  await admin.selectSupportChat(selectedChatId.value)
}

async function onSend() {
  if (!form.message.trim()) return
  await admin.sendSupportMessage(form.message, form.status)
  form.message = ''
}
</script>

<template>
  <section class="card page">
    <h1>Поддержка (админ)</h1>
    <p v-if="admin.state.error" class="error-text">{{ admin.state.error }}</p>

    <div class="row">
      <select v-model="selectedChatId" @change="onSelectChat">
        <option value="" disabled>Выберите чат поддержки</option>
        <option v-for="chat in admin.state.supportChats" :key="chat.id" :value="String(chat.id)">
          #{{ chat.id }} пользователь:{{ chat.user_id }} {{ chat.status }}
        </option>
      </select>
      <button type="button" @click="admin.loadSupportChats">Обновить чаты</button>
    </div>

    <div class="log" aria-live="polite">
      <div v-for="message in admin.state.supportMessages" :key="message.id">
        <strong>{{ message.sender_role }}:</strong> {{ message.content }}
      </div>
    </div>

    <form class="row" @submit.prevent="onSend">
      <input v-model="form.message" placeholder="Ответ пользователю" required />
      <select v-model="form.status">
        <option value="">Оставить статус</option>
        <option value="open">открыт</option>
        <option value="closed">закрыт</option>
      </select>
      <button type="submit">Отправить</button>
    </form>
  </section>
</template>
