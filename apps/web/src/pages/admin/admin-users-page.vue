<script setup>
import { reactive, watch } from 'vue'
import { useAuth } from '../../app/providers/auth-provider'
import { useAdmin } from '../../features/admin/use-admin'

const auth = useAuth()
const admin = useAdmin()
const form = reactive({ userId: '', amount: '', description: '' })

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

async function submitAdjust() {
  await admin.adjustUserCredit(form.userId, form.amount, form.description)
  form.amount = ''
  form.description = ''
}
</script>

<template>
  <section class="card page">
    <h1>Пользователи (админ)</h1>
    <p v-if="admin.state.error" class="error-text">{{ admin.state.error }}</p>

    <form class="row" @submit.prevent="submitAdjust">
      <select v-model="form.userId" required>
        <option value="" disabled>Выберите пользователя</option>
        <option v-for="user in admin.state.users" :key="user.id" :value="String(user.id)">
          #{{ user.id }} {{ user.phone }}
        </option>
      </select>
      <input v-model="form.amount" type="number" step="0.01" placeholder="Сумма" required />
      <input v-model="form.description" placeholder="Описание" />
      <button type="submit">Скорректировать кредит</button>
    </form>

    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Телефон</th>
          <th>Эл. почта</th>
          <th>Роль</th>
          <th>Создано</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="user in admin.state.users" :key="user.id">
          <td>{{ user.id }}</td>
          <td>{{ user.phone }}</td>
          <td>{{ user.email }}</td>
          <td>{{ user.role }}</td>
          <td>{{ user.created_at }}</td>
        </tr>
      </tbody>
    </table>
  </section>
</template>
