<script setup>
import { computed, ref } from 'vue'
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { useAuth } from '../providers/auth-provider'

const router = useRouter()
const auth = useAuth()
const mobileMenuOpen = ref(false)

const user = computed(() => auth.state.user)

const navItems = [
  { to: '/app', label: 'Обзор' },
  { to: '/app/accounts', label: 'Счета' },
  { to: '/app/transactions', label: 'Операции' },
  /* { to: '/app/recurring', label: 'Периодика' }, 
  { to: '/app/plans', label: 'Планы' },
  { to: '/app/analytics', label: 'Аналитика' },
  { to: '/app/ai', label: 'AI-чат' },
  { to: '/app/subscription', label: 'Подписка' },
  { to: '/app/notifications', label: 'Уведомления' }, */
  { to: '/app/support', label: 'Поддержка' },
  { to: '/app/settings', label: 'Настройки' },
]

async function handleLogout() {
  await auth.logout()
  await router.push('/landing')
}
</script>

<template>
  <div class="app-shell">
    <header class="shell-header card">
      <div class="header-main">
        <strong>FinWiseAi</strong>
        <span v-if="user">{{ user.phone }}</span>
      </div>
      <div class="header-actions">
        <RouterLink to="/landing">Главная</RouterLink>
        <RouterLink v-if="user?.role === 'ADMIN'" to="/admin">Админ</RouterLink>
        <button type="button" @click="handleLogout">Выйти</button>
      </div>
    </header>

    <main class="shell-content">
      <RouterView />
    </main>

    <nav class="mobile-bottom-nav card">
      <RouterLink to="/app" class="nav-link">Дашборд</RouterLink>
      <RouterLink to="/app/accounts" class="nav-link">Счета</RouterLink>
      <RouterLink to="/app/transactions" class="nav-link">Операции</RouterLink>
      <RouterLink to="/app/support" class="nav-link">Поддержка</RouterLink>
      <RouterLink to="/app/settings" class="nav-link">Настройки</RouterLink>
    </nav>
  </div>
</template>
