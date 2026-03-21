<script setup>
import { computed, reactive, watch } from 'vue'
import { useAuth } from '../../app/providers/auth-provider'
import { useFinance } from '../../features/finance/use-finance'

const auth = useAuth()
const finance = useFinance()
const profileForm = reactive({ email: '' })
const defaultsForm = reactive({ income_category_id: '', expense_category_id: '' })
const profileStatus = reactive({ message: '', error: '' })

watch(
  () => auth.state.user,
  async (user) => {
    await finance.ensureFinanceLoaded(user)
    profileForm.email = user?.email || ''
    defaultsForm.income_category_id = finance.state.defaults.income_category_id
    defaultsForm.expense_category_id = finance.state.defaults.expense_category_id
  },
  { immediate: true }
)

const user = computed(() => auth.state.user)

async function refreshSession() {
  const nextUser = await auth.refreshUser()
  await finance.ensureFinanceLoaded(nextUser)
}

async function saveDefaults() {
  await finance.saveDefaults(defaultsForm)
}

async function saveProfile() {
  profileStatus.message = ''
  profileStatus.error = ''

  try {
    await auth.updateProfile({ email: profileForm.email })
    profileStatus.message = 'Профиль обновлен'
  } catch (error) {
    profileStatus.error = error.message || 'Не удалось обновить профиль'
  }
}
</script>

<template>
  <section class="card page">
    <h1>Профиль и настройки</h1>

    <div class="row">
      <div class="card">
        <strong>Телефон</strong>
        <div>{{ user?.phone }}</div>
      </div>
      <div class="card">
        <strong>Эл. почта</strong>
        <div>{{ user?.email }}</div>
      </div>
      <div class="card">
        <strong>Роль</strong>
        <div>{{ user?.role }}</div>
      </div>
    </div>

    <section class="card">
      <h2>Профиль</h2>
      <form class="row" @submit.prevent="saveProfile">
        <input v-model="profileForm.email" type="email" placeholder="email@example.com" required />
        <button type="submit" :disabled="auth.state.loading">Сохранить почту</button>
      </form>
      <p v-if="profileStatus.message">{{ profileStatus.message }}</p>
      <p v-if="profileStatus.error" class="error-text">{{ profileStatus.error }}</p>
    </section>

    <section class="card">
      <h2>Категории по умолчанию</h2>
      <div class="row">
        <select v-model="defaultsForm.income_category_id">
          <option value="">Доход по умолчанию</option>
          <option v-for="item in finance.state.incomeCategories" :key="item.id" :value="String(item.id)">
            {{ item.name }}
          </option>
        </select>
        <select v-model="defaultsForm.expense_category_id">
          <option value="">Расход по умолчанию</option>
          <option v-for="item in finance.state.expenseCategories" :key="item.id" :value="String(item.id)">
            {{ item.name }}
          </option>
        </select>
        <button type="button" @click="saveDefaults">Сохранить значения</button>
      </div>
    </section>

    <div class="row">
      <button type="button" @click="refreshSession">Обновить сессию</button>
      <span>{{ finance.state.status }}</span>
    </div>
  </section>
</template>
