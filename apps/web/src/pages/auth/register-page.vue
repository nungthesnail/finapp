<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '../../app/providers/auth-provider'

const router = useRouter()
const auth = useAuth()

const form = reactive({ phone: '', email: '', password: '' })
const error = ref('')

async function submit() {
  error.value = ''
  try {
    await auth.register(form)
    await router.push('/app')
  } catch (submitError) {
    error.value = submitError.message || 'Ошибка регистрации'
  }
}
</script>

<template>
  <section class="card auth-card">
    <h1>Регистрация</h1>
    <form class="page" @submit.prevent="submit">
      <input v-model="form.phone" placeholder="+79991234567" required />
      <input v-model="form.email" type="email" placeholder="email@example.com" required />
      <input v-model="form.password" type="password" placeholder="Пароль" required />
      <button type="submit" :disabled="auth.state.loading">Создать аккаунт</button>
      <RouterLink to="login">Уже есть аккаунт? Войдите &#8594;</RouterLink>  
    </form>
    <p v-if="error" class="error-text">{{ error }}</p>
  </section>
</template>
