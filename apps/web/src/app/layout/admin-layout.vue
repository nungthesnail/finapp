<script setup>
import { computed } from 'vue'
import { RouterLink, RouterView } from 'vue-router'
import { useAuth } from '../providers/auth-provider'

const auth = useAuth()
const user = computed(() => auth.state.user)

const navItems = [
  { to: '/admin', label: 'Обзор' },
  { to: '/admin/users', label: 'Пользователи' },
  /* { to: '/admin/tariffs', label: 'Тарифы' },
  { to: '/admin/payments', label: 'Платежи' },
  { to: '/admin/ai-models', label: 'AI-модели' },
  { to: '/admin/audit', label: 'Аудит' }, */
  { to: '/admin/support', label: 'Поддержка' },
]
</script>

<template>
  <div class="app-shell">
    <header class="shell-header card">
      <div class="header-main">
        <strong>Админ-консоль</strong>
        <span>{{ user?.email }}</span>
      </div>
      <div class="header-actions">
        <RouterLink to="/app">Вернуться в приложение</RouterLink>
      </div>
    </header>

    <nav class="shell-nav card open">
      <RouterLink v-for="item in navItems" :key="item.to" :to="item.to">
        {{ item.label }}
      </RouterLink>
    </nav>

    <main class="shell-content">
      <RouterView />
    </main>
  </div>
</template>
