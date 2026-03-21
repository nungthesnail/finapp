<script setup>
import { watch } from 'vue'
import { useAuth } from '../../app/providers/auth-provider'
import { useNotifications } from '../../features/notifications/use-notifications'

const auth = useAuth()
const notifications = useNotifications()

watch(
  () => auth.state.user,
  async (user) => {
    try {
      await notifications.ensureNotificationsLoaded(user)
    } catch (error) {
      // state.error is reactive
    }
  },
  { immediate: true }
)

async function onMarkRead(id) {
  await notifications.markRead(id)
}

async function onSubscribePush() {
  try {
    await notifications.subscribePush()
  } catch (error) {
    // state.error is reactive
  }
}
</script>

<template>
  <section class="card page">
    <h1>Уведомления</h1>
    <p>{{ notifications.state.status }}</p>
    <p v-if="notifications.state.error" class="error-text">{{ notifications.state.error }}</p>
    <p v-if="notifications.state.pushStatus">{{ notifications.state.pushStatus }}</p>

    <div class="row">
      <button type="button" @click="notifications.loadNotifications">Обновить</button>
      <button type="button" @click="onSubscribePush">Включить push</button>
    </div>

    <p v-if="notifications.state.loading">Загрузка...</p>
    <p v-else-if="notifications.state.items.length === 0">Уведомлений пока нет.</p>

    <table v-else>
      <thead>
        <tr>
          <th>ID</th>
          <th>Заголовок</th>
          <th>Статус</th>
          <th>Создано</th>
          <th>Действие</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in notifications.state.items" :key="item.id">
          <td>{{ item.id }}</td>
          <td>{{ item.title }}</td>
          <td>{{ item.read_at ? 'прочитано' : 'не прочитано' }}</td>
          <td>{{ item.created_at }}</td>
          <td>
            <button v-if="!item.read_at" type="button" @click="onMarkRead(item.id)">Отметить прочитанным</button>
          </td>
        </tr>
      </tbody>
    </table>
  </section>
</template>
