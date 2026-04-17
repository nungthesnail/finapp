<script setup>
import { computed, watch } from 'vue'
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

const stats = computed(() => admin.state.dashboard?.stats || null)
</script>

<template>
  <section class="card page">
    <h1>Админ-панель</h1>
    <p v-if="admin.state.error" class="error-text">{{ admin.state.error }}</p>

    <button type="button" @click="admin.loadDashboard">Обновить</button>

    <p v-if="admin.state.loading">Загрузка...</p>
    <div v-else-if="stats" class="row">
      <article class="card"><strong>Пользователи</strong><div>{{ stats.users_total }}</div></article>
      <!--<article class="card"><strong>Тарифы</strong><div>{{ stats.tariffs_total }}</div></article>
      <article class="card"><strong>Активные подписки</strong><div>{{ stats.subscriptions_active }}</div></article>
      <article class="card"><strong>Успешные платежи</strong><div>{{ stats.payments_succeeded }}</div></article>
      <article class="card"><strong>Сумма кредитов</strong><div>{{ stats.credits_total }}</div></article> -->
    </div>
    <p v-else>Данных админ-панели пока нет.</p>
  </section>
</template>
