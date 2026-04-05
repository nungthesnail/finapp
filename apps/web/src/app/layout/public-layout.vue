<script setup>
import { RouterLink, RouterView } from 'vue-router'
</script>

<template>
  <div class="public-shell">
    <header class="shell-header card">
      <div class="header-main">
        <strong>FinWiseAi</strong>
      </div>
      <div class="header-actions">
        <RouterLink to="/landing">Главная</RouterLink>
        <RouterLink to="/privacy">Конфиденциальность</RouterLink>
        <RouterLink to="/help">Справка</RouterLink>
        <RouterLink to="/auth/login">Вход</RouterLink>
        <RouterLink to="/auth/register">Регистрация</RouterLink>
      </div>
    </header>

    <main class="shell-content">
      <RouterView />
    </main>
  </div>
</template>
