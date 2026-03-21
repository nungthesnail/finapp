<script setup>
import { watch } from 'vue'
import { useAuth } from '../../app/providers/auth-provider'
import { useAdmin } from '../../features/admin/use-admin'

const auth = useAuth()
const admin = useAdmin()

watch(
  () => auth.state.user,
  async (user) => {
    try {
      await admin.ensureAdminLoaded(user)
    } catch (error) {
      // state.error is reactive
    }
  },
  { immediate: true }
)
</script>

<template>
  <section class="card page">
    <h1>Журнал аудита (админ)</h1>
    <p v-if="admin.state.error" class="error-text">{{ admin.state.error }}</p>

    <button type="button" @click="admin.loadAuditLogs">Обновить</button>

    <table v-if="admin.state.auditLogs.length">
      <thead>
        <tr>
          <th>ID</th>
          <th>Кто</th>
          <th>Действие</th>
          <th>Сущность</th>
          <th>Данные</th>
          <th>Создано</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in admin.state.auditLogs" :key="item.id">
          <td>{{ item.id }}</td>
          <td>{{ item.actor_user_id }}</td>
          <td>{{ item.action }}</td>
          <td>{{ item.entity_type }} {{ item.entity_id }}</td>
          <td><pre>{{ item.payload }}</pre></td>
          <td>{{ item.created_at }}</td>
        </tr>
      </tbody>
    </table>
    <p v-else>Записей аудита нет.</p>
  </section>
</template>
