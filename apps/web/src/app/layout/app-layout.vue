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
  { to: '/app/recurring', label: 'Периодика' },
  { to: '/app/plans', label: 'Планы' },
  { to: '/app/analytics', label: 'Аналитика' },
  { to: '/app/ai', label: 'AI-чат' },
  { to: '/app/subscription', label: 'Подписка' },
  { to: '/app/notifications', label: 'Уведомления' },
  { to: '/app/support', label: 'Поддержка' },
  { to: '/app/settings', label: 'Настройки' },
]

async function handleLogout() {
  await auth.logout()
  await router.push('/auth/login')
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
        <RouterLink to="/app">Приложение</RouterLink>
        <RouterLink v-if="user?.role === 'ADMIN'" to="/admin">Админ</RouterLink>
        <button type="button" @click="mobileMenuOpen = !mobileMenuOpen">Меню</button>
        <button type="button" @click="handleLogout">Выйти</button>
      </div>
    </header>

    <nav class="shell-nav card" :class="{ open: mobileMenuOpen }">
      <RouterLink v-for="item in navItems" :key="item.to" :to="item.to" @click="mobileMenuOpen = false">
        {{ item.label }}
      </RouterLink>
    </nav>

    <main class="shell-content">
      <RouterView />
    </main>

    <nav class="mobile-bottom-nav card">
      <RouterLink to="/app">Главная</RouterLink>
      <RouterLink to="/app/transactions">Деньги</RouterLink>
      <RouterLink to="/app/ai">AI</RouterLink>
      <RouterLink to="/app/notifications">Входящие</RouterLink>
      <RouterLink to="/app/settings">Профиль</RouterLink>
    </nav>
  </div>
</template>
