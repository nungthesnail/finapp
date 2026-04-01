<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useAuth } from '../../app/providers/auth-provider'
import { useFinance } from '../../features/finance/use-finance'

const auth = useAuth()
const finance = useFinance()

const profileForm = reactive({ email: '' })
const defaultsForm = reactive({ income_category_id: '', expense_category_id: '' })
const profileStatus = reactive({ message: '', error: '' })
const categoryStatus = reactive({ message: '', error: '' })

const newIncomeName = ref('')
const newExpenseName = ref('')
const incomeDrafts = reactive({})
const expenseDrafts = reactive({})

watch(
  () => auth.state.user,
  async (user) => {
    await finance.ensureFinanceLoaded(user)
    profileForm.email = user?.email || ''
    syncDefaultsForm()
    syncCategoryDrafts()
  },
  { immediate: true }
)

watch(
  [
    () => finance.state.defaults.income_category_id,
    () => finance.state.defaults.expense_category_id,
  ],
  () => {
    syncDefaultsForm()
  }
)

watch(
  [
    () => finance.state.incomeCategories.length,
    () => finance.state.expenseCategories.length,
  ],
  () => {
    syncCategoryDrafts()
  }
)

const user = computed(() => auth.state.user)

function syncDefaultsForm() {
  defaultsForm.income_category_id = finance.state.defaults.income_category_id
  defaultsForm.expense_category_id = finance.state.defaults.expense_category_id
}

function syncCategoryDrafts() {
  for (const item of finance.state.incomeCategories) {
    incomeDrafts[item.id] = item.name
  }
  for (const item of finance.state.expenseCategories) {
    expenseDrafts[item.id] = item.name
  }
}

function resetCategoryStatus() {
  categoryStatus.message = ''
  categoryStatus.error = ''
}

async function refreshSession() {
  const nextUser = await auth.refreshUser()
  await finance.ensureFinanceLoaded(nextUser)
}

async function saveDefaults() {
  resetCategoryStatus()
  try {
    await finance.saveDefaults(defaultsForm)
    categoryStatus.message = 'Категории по умолчанию обновлены'
  } catch (error) {
    categoryStatus.error = error.message || 'Не удалось обновить категории по умолчанию'
  }
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

async function addIncomeCategory() {
  resetCategoryStatus()
  const name = newIncomeName.value.trim()
  if (!name) return

  try {
    await finance.createIncomeCategory(name)
    newIncomeName.value = ''
    categoryStatus.message = 'Категория дохода добавлена'
  } catch (error) {
    categoryStatus.error = error.message || 'Не удалось добавить категорию дохода'
  }
}

async function updateIncomeCategory(item) {
  resetCategoryStatus()
  const name = String(incomeDrafts[item.id] || '').trim()
  if (!name) return

  try {
    await finance.updateIncomeCategory(item.id, name)
    categoryStatus.message = 'Категория дохода обновлена'
  } catch (error) {
    categoryStatus.error = error.message || 'Не удалось обновить категорию дохода'
  }
}

async function deleteIncomeCategory(item) {
  resetCategoryStatus()
  try {
    await finance.deleteIncomeCategory(item.id)
    categoryStatus.message = 'Категория дохода удалена'
    if (defaultsForm.income_category_id === String(item.id) && finance.state.incomeCategories.length) {
      defaultsForm.income_category_id = String(finance.state.incomeCategories[0].id)
      await finance.saveDefaults(defaultsForm)
    }
  } catch (error) {
    categoryStatus.error = error.message || 'Не удалось удалить категорию дохода'
  }
}

async function addExpenseCategory() {
  resetCategoryStatus()
  const name = newExpenseName.value.trim()
  if (!name) return

  try {
    await finance.createExpenseCategory(name)
    newExpenseName.value = ''
    categoryStatus.message = 'Категория расхода добавлена'
  } catch (error) {
    categoryStatus.error = error.message || 'Не удалось добавить категорию расхода'
  }
}

async function updateExpenseCategory(item) {
  resetCategoryStatus()
  const name = String(expenseDrafts[item.id] || '').trim()
  if (!name) return

  try {
    await finance.updateExpenseCategory(item.id, name)
    categoryStatus.message = 'Категория расхода обновлена'
  } catch (error) {
    categoryStatus.error = error.message || 'Не удалось обновить категорию расхода'
  }
}

async function deleteExpenseCategory(item) {
  resetCategoryStatus()
  try {
    await finance.deleteExpenseCategory(item.id)
    categoryStatus.message = 'Категория расхода удалена'
    if (defaultsForm.expense_category_id === String(item.id) && finance.state.expenseCategories.length) {
      defaultsForm.expense_category_id = String(finance.state.expenseCategories[0].id)
      await finance.saveDefaults(defaultsForm)
    }
  } catch (error) {
    categoryStatus.error = error.message || 'Не удалось удалить категорию расхода'
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
      <p v-if="categoryStatus.message">{{ categoryStatus.message }}</p>
      <p v-if="categoryStatus.error" class="error-text">{{ categoryStatus.error }}</p>
    </section>

    <section class="card">
      <h2>Категории доходов</h2>
      <form class="row" @submit.prevent="addIncomeCategory">
        <input v-model="newIncomeName" placeholder="Новая категория дохода" />
        <button type="submit">Добавить</button>
      </form>
      <div v-for="item in finance.state.incomeCategories" :key="item.id" class="row">
        <input v-model="incomeDrafts[item.id]" />
        <button type="button" @click="updateIncomeCategory(item)">Сохранить</button>
        <button type="button" @click="deleteIncomeCategory(item)">Удалить</button>
      </div>
    </section>

    <section class="card">
      <h2>Категории расходов</h2>
      <form class="row" @submit.prevent="addExpenseCategory">
        <input v-model="newExpenseName" placeholder="Новая категория расхода" />
        <button type="submit">Добавить</button>
      </form>
      <div v-for="item in finance.state.expenseCategories" :key="item.id" class="row">
        <input v-model="expenseDrafts[item.id]" />
        <button type="button" @click="updateExpenseCategory(item)">Сохранить</button>
        <button type="button" @click="deleteExpenseCategory(item)">Удалить</button>
      </div>
    </section>

    <div class="row">
      <button type="button" @click="refreshSession">Обновить сессию</button>
      <span>{{ finance.state.status }}</span>
    </div>
  </section>
</template>
