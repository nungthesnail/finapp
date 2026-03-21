<script setup>
import { onMounted } from 'vue'
import { RouterView } from 'vue-router'
import { useAuth } from './app/providers/auth-provider'

const auth = useAuth()

onMounted(() => {
  auth.ensureInitialized()
})
</script>

<template>
  <RouterView v-if="auth.state.initialized" />
  <main v-else class="page">
    <section class="card">
      <h1>FinWiseAi</h1>
      <p>Восстановление сессии...</p>
    </section>
  </main>
</template>
