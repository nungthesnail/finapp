<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuth } from '../../app/providers/auth-provider'

const route = useRoute()
const router = useRouter()
const auth = useAuth()

const form = reactive({ phone: '', password: '' })
const error = ref('')

async function submit() {
  error.value = ''
  try {
    await auth.login(form)
    const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/app'
    await router.push(redirect)
  } catch (submitError) {
    error.value = submitError.message || 'Ошибка входа'
  }
}
</script>

<template>
  <section class="card auth-card">
    <h1>Вход</h1>
    <form class="page" @submit.prevent="submit">
      <input v-model="form.phone" placeholder="+79990000000" required />
      <input v-model="form.password" type="password" placeholder="Пароль" required />
      <button type="submit" :disabled="auth.state.loading">Войти</button>
      <RouterLink to="register">Регистрация</RouterLink>  
    </form>
    <p v-if="error" class="error-text">{{ error }}</p>
  </section>
</template>
