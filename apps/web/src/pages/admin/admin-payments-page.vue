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

async function onCancel(subscriptionId) {
  await admin.cancelSubscription(subscriptionId)
}
</script>

<template>
  <section class="card page">
    <h1>Платежи и подписки (админ)</h1>
    <p v-if="admin.state.error" class="error-text">{{ admin.state.error }}</p>

    <button type="button" @click="admin.loadDashboard">Обновить</button>

    <section class="card page">
      <h2>Подписки</h2>
      <table v-if="admin.state.dashboard?.subscriptions?.length">
        <thead>
          <tr>
            <th>ID</th>
            <th>Пользователь</th>
            <th>Тариф</th>
            <th>Статус</th>
            <th>Окончание</th>
            <th>Действие</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in admin.state.dashboard.subscriptions" :key="item.id">
            <td>{{ item.id }}</td>
            <td>{{ item.user_id }}</td>
            <td>{{ item.tariff_id }}</td>
            <td>{{ item.status }}</td>
            <td>{{ item.end_at }}</td>
            <td>
              <button v-if="item.status === 'active'" type="button" @click="onCancel(item.id)">Отменить</button>
            </td>
          </tr>
        </tbody>
      </table>
      <p v-else>Подписок нет.</p>
    </section>

    <section class="card page">
      <h2>Платежи</h2>
      <table v-if="admin.state.dashboard?.payments?.length">
        <thead>
          <tr>
            <th>ID</th>
            <th>Пользователь</th>
            <th>Статус</th>
            <th>Сумма</th>
            <th>Оплачено</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in admin.state.dashboard.payments" :key="item.id">
            <td>{{ item.id }}</td>
            <td>{{ item.user_id }}</td>
            <td>{{ item.status }}</td>
            <td>{{ item.amount_rub }}</td>
            <td>{{ item.paid_at }}</td>
          </tr>
        </tbody>
      </table>
      <p v-else>Платежей нет.</p>
    </section>
  </section>
</template>
