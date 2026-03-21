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
    <h1>Тарифы (админ)</h1>
    <p v-if="admin.state.error" class="error-text">{{ admin.state.error }}</p>

    <button type="button" @click="admin.loadDashboard">Обновить</button>

    <table v-if="admin.state.dashboard?.tariffs?.length">
      <thead>
        <tr>
          <th>ID</th>
          <th>Название</th>
          <th>Длительность</th>
          <th>Цена RUB</th>
          <th>Активен</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in admin.state.dashboard.tariffs" :key="item.id">
          <td>{{ item.id }}</td>
          <td>{{ item.name }}</td>
          <td>{{ item.duration_days }}</td>
          <td>{{ item.price_rub }}</td>
          <td>{{ item.is_active ? 'да' : 'нет' }}</td>
        </tr>
      </tbody>
    </table>
    <p v-else>Тарифов пока нет.</p>
  </section>
</template>
