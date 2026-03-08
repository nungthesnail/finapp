<script setup>
import { onMounted, reactive, ref } from 'vue'

const me = ref(null)
const status = ref('')
const adminUsers = ref([])
const accounts = ref([])
const transactions = ref([])
const incomeCategories = ref([])
const expenseCategories = ref([])
const recurring = ref([])
const budgetPlans = ref([])
const summary = ref(null)

const loginForm = reactive({ phone: '', password: '' })
const registerForm = reactive({ phone: '', email: '', password: '' })
const accountForm = reactive({ name: '', type: '', currency: 'RUB', balance: 0 })
const transactionForm = reactive({ type: 'expense', account_id: '', amount: '', description: '' })
const recurringForm = reactive({ type: 'expense', account_id: '', category_id: '', amount: '', frequency: 'monthly', start_at: '' })
const planForm = reactive({ name: '', period_from: '', period_to: '', goal_type: 'savings', goal_amount: '' })
const filters = reactive({ type: '', account_id: '' })
const defaults = reactive({ income_category_id: '', expense_category_id: '' })

async function api(path, options = {}) {
  const res = await fetch(`/api${path}`, {
    credentials: 'include',
    headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
    ...options,
  })
  const data = await res.json().catch(() => ({}))
  if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`)
  return data
}

function setStatus(msg) { status.value = msg }

async function loadMe() {
  try {
    const data = await api('/me')
    me.value = data.user
    setStatus(`Авторизован: ${data.user.phone}`)
    await Promise.all([loadCategories(), loadAccounts(), loadTransactions(), loadRecurring(), loadPlans(), loadSummary()])
  } catch {
    me.value = null
    setStatus('Не авторизован')
  }
}

async function register() {
  await api('/auth/register', { method: 'POST', body: JSON.stringify(registerForm) })
  registerForm.phone = ''; registerForm.email = ''; registerForm.password = ''
  await loadMe()
}

async function login() {
  await api('/auth/login', { method: 'POST', body: JSON.stringify(loginForm) })
  loginForm.phone = ''; loginForm.password = ''
  await loadMe()
}

async function logout() {
  await api('/auth/logout', { method: 'POST' })
  await loadMe()
}

async function loadCategories() {
  const [i, e, d] = await Promise.all([api('/income-categories'), api('/expense-categories'), api('/users/category-defaults')])
  incomeCategories.value = i.items
  expenseCategories.value = e.items
  defaults.income_category_id = String(d.item.income_category_id)
  defaults.expense_category_id = String(d.item.expense_category_id)
  if (!recurringForm.category_id && expenseCategories.value.length) {
    recurringForm.category_id = String(expenseCategories.value[0].id)
  }
}

async function saveDefaults() {
  await api('/users/category-defaults', { method: 'PUT', body: JSON.stringify({
    income_category_id: Number(defaults.income_category_id),
    expense_category_id: Number(defaults.expense_category_id),
  }) })
  setStatus('Default категории сохранены')
}

async function addIncomeCategory(name) {
  if (!name) return
  await api('/income-categories', { method: 'POST', body: JSON.stringify({ name }) })
  await loadCategories()
}

async function addExpenseCategory(name) {
  if (!name) return
  await api('/expense-categories', { method: 'POST', body: JSON.stringify({ name }) })
  await loadCategories()
}

async function loadAccounts() {
  const data = await api('/accounts')
  accounts.value = data.items
  if (!transactionForm.account_id && accounts.value.length) transactionForm.account_id = String(accounts.value[0].id)
  if (!recurringForm.account_id && accounts.value.length) recurringForm.account_id = String(accounts.value[0].id)
}

async function createAccount() {
  await api('/accounts', { method: 'POST', body: JSON.stringify({
    ...accountForm,
    balance: Number(accountForm.balance || 0),
  }) })
  accountForm.name = ''; accountForm.type = ''; accountForm.currency = 'RUB'; accountForm.balance = 0
  await loadAccounts()
}

async function deleteAccount(id) {
  await api(`/accounts/${id}`, { method: 'DELETE' })
  await loadAccounts()
}

async function loadTransactions() {
  const q = new URLSearchParams()
  if (filters.type) q.set('type', filters.type)
  if (filters.account_id) q.set('account_id', filters.account_id)
  const data = await api(`/transactions${q.toString() ? `?${q}` : ''}`)
  transactions.value = data.items
}

async function createTransaction() {
  await api('/transactions', { method: 'POST', body: JSON.stringify({
    type: transactionForm.type,
    account_id: Number(transactionForm.account_id),
    amount: Number(transactionForm.amount),
    description: transactionForm.description || '',
  }) })
  transactionForm.amount = ''; transactionForm.description = ''
  await Promise.all([loadTransactions(), loadSummary()])
}

async function deleteTransaction(id) {
  await api(`/transactions/${id}`, { method: 'DELETE' })
  await Promise.all([loadTransactions(), loadSummary()])
}

async function loadRecurring() {
  const data = await api('/recurring-transactions')
  recurring.value = data.items
}

async function createRecurring() {
  await api('/recurring-transactions', { method: 'POST', body: JSON.stringify({
    type: recurringForm.type,
    account_id: Number(recurringForm.account_id),
    category_id: Number(recurringForm.category_id),
    amount: Number(recurringForm.amount),
    frequency: recurringForm.frequency,
    start_at: new Date(recurringForm.start_at).toISOString(),
  }) })
  recurringForm.amount = ''
  await loadRecurring()
}

async function deleteRecurring(id) {
  await api(`/recurring-transactions/${id}`, { method: 'DELETE' })
  await loadRecurring()
}

async function loadPlans() {
  const data = await api('/budget-plans')
  budgetPlans.value = data.items
}

async function createPlan() {
  await api('/budget-plans', { method: 'POST', body: JSON.stringify({
    ...planForm,
    goal_amount: planForm.goal_amount ? Number(planForm.goal_amount) : null,
    categories: [],
  }) })
  planForm.name = ''; planForm.period_from = ''; planForm.period_to = ''; planForm.goal_amount = ''
  await loadPlans()
}

async function deletePlan(id) {
  await api(`/budget-plans/${id}`, { method: 'DELETE' })
  await loadPlans()
}

async function loadSummary() {
  summary.value = await api('/analytics/summary')
}

async function loadAdminUsers() {
  const data = await api('/admin/users')
  adminUsers.value = data.items
}

onMounted(loadMe)
</script>

<template>
  <main class="page">
    <header class="top">
      <h1>FinWiseAi</h1>
      <p>{{ status }}</p>
    </header>

    <section v-if="!me" class="card grid2">
      <form @submit.prevent="register">
        <h2>Регистрация</h2>
        <input v-model="registerForm.phone" placeholder="+79991234567" required />
        <input v-model="registerForm.email" type="email" placeholder="email@example.com" required />
        <input v-model="registerForm.password" type="password" placeholder="Пароль" required />
        <button>Создать аккаунт</button>
      </form>
      <form @submit.prevent="login">
        <h2>Вход</h2>
        <input v-model="loginForm.phone" placeholder="+79990000000" required />
        <input v-model="loginForm.password" type="password" placeholder="Пароль" required />
        <button>Войти</button>
      </form>
    </section>

    <template v-else>
      <section class="card row">
        <div>
          <strong>{{ me.phone }}</strong>
          <div>{{ me.email }} | role: {{ me.role }}</div>
        </div>
        <button @click="loadMe">Обновить</button>
        <button @click="logout">Выйти</button>
      </section>

      <section class="card">
        <h2>Категории и default</h2>
        <div class="row">
          <input placeholder="Новая категория дохода" @keyup.enter="addIncomeCategory($event.target.value); $event.target.value=''" />
          <input placeholder="Новая категория расхода" @keyup.enter="addExpenseCategory($event.target.value); $event.target.value=''" />
        </div>
        <div class="row">
          <select v-model="defaults.income_category_id">
            <option v-for="c in incomeCategories" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
          </select>
          <select v-model="defaults.expense_category_id">
            <option v-for="c in expenseCategories" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
          </select>
          <button @click="saveDefaults">Сохранить defaults</button>
        </div>
      </section>

      <section class="card">
        <h2>Счета</h2>
        <form class="row" @submit.prevent="createAccount">
          <input v-model="accountForm.name" placeholder="Название" required />
          <input v-model="accountForm.type" placeholder="Тип" required />
          <input v-model="accountForm.currency" placeholder="RUB" required />
          <input v-model="accountForm.balance" type="number" step="0.01" placeholder="Баланс" required />
          <button>Создать</button>
        </form>
        <table>
          <thead><tr><th>ID</th><th>Название</th><th>Тип</th><th>Баланс</th><th></th></tr></thead>
          <tbody>
            <tr v-for="a in accounts" :key="a.id">
              <td>{{ a.id }}</td><td>{{ a.name }}</td><td>{{ a.type }}</td><td>{{ a.balance }}</td>
              <td><button @click="deleteAccount(a.id)">Удалить</button></td>
            </tr>
          </tbody>
        </table>
      </section>

      <section class="card">
        <h2>Операции + фильтры</h2>
        <form class="row" @submit.prevent="createTransaction">
          <select v-model="transactionForm.type"><option value="expense">expense</option><option value="income">income</option></select>
          <select v-model="transactionForm.account_id">
            <option v-for="a in accounts" :key="a.id" :value="String(a.id)">{{ a.name }}</option>
          </select>
          <input v-model="transactionForm.amount" type="number" step="0.01" placeholder="Сумма" required />
          <input v-model="transactionForm.description" placeholder="Описание" />
          <button>Добавить</button>
        </form>
        <div class="row">
          <select v-model="filters.type"><option value="">Все типы</option><option value="income">income</option><option value="expense">expense</option></select>
          <select v-model="filters.account_id"><option value="">Все счета</option><option v-for="a in accounts" :key="a.id" :value="String(a.id)">{{ a.name }}</option></select>
          <button @click="loadTransactions">Применить фильтры</button>
        </div>
        <table>
          <thead><tr><th>ID</th><th>Тип</th><th>Счет</th><th>Сумма</th><th>Дата</th><th></th></tr></thead>
          <tbody>
            <tr v-for="t in transactions" :key="t.id">
              <td>{{ t.id }}</td><td>{{ t.type }}</td><td>{{ t.account_id }}</td><td>{{ t.amount }}</td><td>{{ new Date(t.occurred_at).toLocaleString() }}</td>
              <td><button @click="deleteTransaction(t.id)">Удалить</button></td>
            </tr>
          </tbody>
        </table>
      </section>

      <section class="card">
        <h2>Этап 2: Периодические операции</h2>
        <form class="row" @submit.prevent="createRecurring">
          <select v-model="recurringForm.type"><option value="expense">expense</option><option value="income">income</option></select>
          <select v-model="recurringForm.account_id"><option v-for="a in accounts" :key="a.id" :value="String(a.id)">{{ a.name }}</option></select>
          <select v-model="recurringForm.category_id">
            <option v-for="c in recurringForm.type==='income' ? incomeCategories : expenseCategories" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
          </select>
          <input v-model="recurringForm.amount" type="number" step="0.01" placeholder="Сумма" required />
          <select v-model="recurringForm.frequency"><option value="daily">daily</option><option value="weekly">weekly</option><option value="monthly">monthly</option></select>
          <input v-model="recurringForm.start_at" type="datetime-local" required />
          <button>Добавить</button>
        </form>
        <ul>
          <li v-for="r in recurring" :key="r.id">
            #{{ r.id }} {{ r.type }} {{ r.amount }} {{ r.frequency }} next={{ new Date(r.next_run_at).toLocaleString() }}
            <button @click="deleteRecurring(r.id)">Удалить</button>
          </li>
        </ul>
      </section>

      <section class="card">
        <h2>Этап 2: Планы расходов</h2>
        <form class="row" @submit.prevent="createPlan">
          <input v-model="planForm.name" placeholder="Название плана" required />
          <input v-model="planForm.period_from" type="date" required />
          <input v-model="planForm.period_to" type="date" required />
          <select v-model="planForm.goal_type"><option value="savings">savings</option><option value="target_balance">target_balance</option></select>
          <input v-model="planForm.goal_amount" type="number" step="0.01" placeholder="Цель (опц.)" />
          <button>Создать план</button>
        </form>
        <ul>
          <li v-for="p in budgetPlans" :key="p.id">
            #{{ p.id }} {{ p.name }} {{ p.period_from }} - {{ p.period_to }} ({{ p.goal_type }})
            <button @click="deletePlan(p.id)">Удалить</button>
          </li>
        </ul>
      </section>

      <section class="card">
        <h2>Этап 2: Аналитика (summary)</h2>
        <button @click="loadSummary">Обновить</button>
        <pre>{{ summary }}</pre>
      </section>

      <section class="card">
        <h2>Admin</h2>
        <button @click="loadAdminUsers">Загрузить пользователей</button>
        <pre>{{ adminUsers }}</pre>
      </section>
    </template>
  </main>
</template>
