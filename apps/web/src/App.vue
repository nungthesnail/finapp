<script setup>
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue'

const me = ref(null)
const status = ref('')
const adminUsers = ref([])
const adminDashboard = ref(null)

const accounts = ref([])
const transactions = ref([])
const incomeCategories = ref([])
const expenseCategories = ref([])
const recurring = ref([])
const budgetPlans = ref([])
const summary = ref(null)

const chats = ref([])
const selectedChatId = ref('')
const chatMessages = ref([])
const chatInput = ref('')
const chatStreaming = ref('')

const notifications = ref([])
const supportChats = ref([])
const selectedSupportChatId = ref('')
const supportMessages = ref([])
const supportInput = ref('')
const canInstallPwa = ref(false)
const deferredInstallPrompt = ref(null)

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

function setStatus(message) {
  status.value = message
}

async function loadMe() {
  try {
    const data = await api('/me')
    me.value = data.user
    setStatus(`Authorized: ${data.user.phone}`)
    await Promise.all([
      loadCategories(),
      loadAccounts(),
      loadTransactions(),
      loadRecurring(),
      loadPlans(),
      loadSummary(),
      loadChats(),
      loadNotifications(),
      loadSupportChats(),
    ])
    if (me.value?.role === 'ADMIN') {
      await Promise.all([loadAdminUsers(), loadAdminDashboard()])
    }
  } catch {
    me.value = null
    setStatus('Unauthorized')
  }
}

async function register() {
  await api('/auth/register', { method: 'POST', body: JSON.stringify(registerForm) })
  registerForm.phone = ''
  registerForm.email = ''
  registerForm.password = ''
  await loadMe()
}

async function login() {
  await api('/auth/login', { method: 'POST', body: JSON.stringify(loginForm) })
  loginForm.phone = ''
  loginForm.password = ''
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
  await api('/users/category-defaults', {
    method: 'PUT',
    body: JSON.stringify({
      income_category_id: Number(defaults.income_category_id),
      expense_category_id: Number(defaults.expense_category_id),
    }),
  })
}

async function loadAccounts() {
  const data = await api('/accounts')
  accounts.value = data.items
  if (!transactionForm.account_id && accounts.value.length) transactionForm.account_id = String(accounts.value[0].id)
  if (!recurringForm.account_id && accounts.value.length) recurringForm.account_id = String(accounts.value[0].id)
}

async function createAccount() {
  await api('/accounts', { method: 'POST', body: JSON.stringify({ ...accountForm, balance: Number(accountForm.balance || 0) }) })
  accountForm.name = ''
  accountForm.type = ''
  accountForm.currency = 'RUB'
  accountForm.balance = 0
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
  await api('/transactions', {
    method: 'POST',
    body: JSON.stringify({
      type: transactionForm.type,
      account_id: Number(transactionForm.account_id),
      amount: Number(transactionForm.amount),
      description: transactionForm.description || '',
    }),
  })
  transactionForm.amount = ''
  transactionForm.description = ''
  await Promise.all([loadTransactions(), loadSummary()])
}

async function loadRecurring() {
  const data = await api('/recurring-transactions')
  recurring.value = data.items
}

async function createRecurring() {
  await api('/recurring-transactions', {
    method: 'POST',
    body: JSON.stringify({
      type: recurringForm.type,
      account_id: Number(recurringForm.account_id),
      category_id: Number(recurringForm.category_id),
      amount: Number(recurringForm.amount),
      frequency: recurringForm.frequency,
      start_at: new Date(recurringForm.start_at).toISOString(),
    }),
  })
  recurringForm.amount = ''
  await loadRecurring()
}

async function loadPlans() {
  const data = await api('/budget-plans')
  budgetPlans.value = data.items
}

async function createPlan() {
  await api('/budget-plans', {
    method: 'POST',
    body: JSON.stringify({
      ...planForm,
      goal_amount: planForm.goal_amount ? Number(planForm.goal_amount) : null,
      categories: [],
    }),
  })
  planForm.name = ''
  planForm.period_from = ''
  planForm.period_to = ''
  planForm.goal_amount = ''
  await loadPlans()
}

async function loadSummary() {
  summary.value = await api('/analytics/summary')
}

async function loadChats() {
  const list = await api('/ai/chats')
  chats.value = list.items

  const active = await api('/ai/chats/last-active')
  selectedChatId.value = String(active.item.id)
  chatMessages.value = active.messages
}

async function createChat() {
  const created = await api('/ai/chats', {
    method: 'POST',
    body: JSON.stringify({ title: `Chat ${new Date().toLocaleTimeString()}` }),
  })
  chats.value.unshift(created.item)
  await selectChat(String(created.item.id))
}

async function selectChat(id) {
  selectedChatId.value = id
  const data = await api(`/ai/chats/${id}/messages`)
  chatMessages.value = data.items
}

async function sendChatMessage() {
  const text = chatInput.value.trim()
  if (!text || !selectedChatId.value) return

  chatMessages.value.push({ id: `tmp-u-${Date.now()}`, role: 'user', content: text })
  chatInput.value = ''
  chatStreaming.value = ''

  const response = await fetch(`/api/ai/chats/${selectedChatId.value}/messages/stream`, {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ message: text }),
  })
  if (!response.ok || !response.body) throw new Error('Chat stream failed')

  const reader = response.body.getReader()
  const decoder = new TextDecoder()
  let buffer = ''

  while (true) {
    const { done, value } = await reader.read()
    if (done) break
    buffer += decoder.decode(value, { stream: true })
    const parts = buffer.split('\n\n')
    buffer = parts.pop() || ''

    for (const part of parts) {
      const lines = part.split('\n')
      const eventLine = lines.find((line) => line.startsWith('event:'))
      const dataLine = lines.find((line) => line.startsWith('data:'))
      if (!eventLine || !dataLine) continue
      const event = eventLine.replace('event:', '').trim()
      const payload = JSON.parse(dataLine.replace('data:', '').trim())

      if (event === 'chunk') chatStreaming.value += payload.text || ''
      if (event === 'done') {
        chatMessages.value.push(payload.message)
        chatStreaming.value = ''
      }
    }
  }

  await loadChats()
}

async function loadNotifications() {
  const data = await api('/notifications')
  notifications.value = data.items
}

async function markNotificationRead(id) {
  await api(`/notifications/${id}/read`, { method: 'PATCH' })
  await loadNotifications()
}

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')
  const rawData = atob(base64)
  const outputArray = new Uint8Array(rawData.length)
  for (let i = 0; i < rawData.length; i += 1) outputArray[i] = rawData.charCodeAt(i)
  return outputArray
}

async function subscribePush() {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
    throw new Error('Push API is not supported in this browser')
  }

  const registration = await navigator.serviceWorker.ready
  const permission = await window.Notification.requestPermission()
  if (permission !== 'granted') throw new Error('Push permission denied')

  const vapidKey = import.meta.env.VITE_VAPID_PUBLIC_KEY || ''
  const options = vapidKey
    ? { userVisibleOnly: true, applicationServerKey: urlBase64ToUint8Array(vapidKey) }
    : { userVisibleOnly: true }

  const subscription = await registration.pushManager.subscribe(options)
  await api('/push/subscriptions', { method: 'POST', body: JSON.stringify(subscription.toJSON()) })
  setStatus('Push subscription saved')
}

async function loadSupportChats() {
  const data = await api('/support/chats')
  supportChats.value = data.items
  if (!selectedSupportChatId.value && supportChats.value.length) {
    await selectSupportChat(String(supportChats.value[0].id))
  }
}

async function createSupportChat() {
  const created = await api('/support/chats', {
    method: 'POST',
    body: JSON.stringify({ message: 'Support request created' }),
  })
  await loadSupportChats()
  await selectSupportChat(String(created.item.id))
}

async function selectSupportChat(chatId) {
  selectedSupportChatId.value = String(chatId)
  const data = await api(`/support/chats/${chatId}/messages`)
  supportMessages.value = data.items
}

async function sendSupportMessage() {
  const content = supportInput.value.trim()
  if (!content || !selectedSupportChatId.value) return

  await api(`/support/chats/${selectedSupportChatId.value}/messages`, {
    method: 'POST',
    body: JSON.stringify({ content }),
  })
  supportInput.value = ''
  await selectSupportChat(selectedSupportChatId.value)
  await loadSupportChats()
}

async function loadAdminUsers() {
  const data = await api('/admin/users')
  adminUsers.value = data.items
}

async function loadAdminDashboard() {
  adminDashboard.value = await api('/admin/dashboard')
}

async function installPwa() {
  if (!deferredInstallPrompt.value) return
  deferredInstallPrompt.value.prompt()
  await deferredInstallPrompt.value.userChoice
  deferredInstallPrompt.value = null
  canInstallPwa.value = false
}

function onBeforeInstallPrompt(event) {
  event.preventDefault()
  deferredInstallPrompt.value = event
  canInstallPwa.value = true
}

onMounted(() => {
  window.addEventListener('beforeinstallprompt', onBeforeInstallPrompt)
  loadMe()
})

onBeforeUnmount(() => {
  window.removeEventListener('beforeinstallprompt', onBeforeInstallPrompt)
})
</script>

<template>
  <main class="page">
    <h1>FinWiseAi</h1>
    <p>{{ status }}</p>
    <section class="card row">
      <a href="/landing.html">Landing</a>
      <a href="/privacy.html">Privacy</a>
      <a href="/help.html">Help</a>
      <button v-if="canInstallPwa" @click="installPwa">Install app</button>
    </section>

    <section v-if="!me" class="card grid2">
      <form @submit.prevent="register">
        <h2>Register</h2>
        <input v-model="registerForm.phone" placeholder="+79991234567" required />
        <input v-model="registerForm.email" type="email" placeholder="email@example.com" required />
        <input v-model="registerForm.password" type="password" placeholder="Password" required />
        <button>Create account</button>
      </form>
      <form @submit.prevent="login">
        <h2>Login</h2>
        <input v-model="loginForm.phone" placeholder="+79990000000" required />
        <input v-model="loginForm.password" type="password" placeholder="Password" required />
        <button>Login</button>
      </form>
    </section>

    <template v-else>
      <section class="card row">
        <div><strong>{{ me.phone }}</strong> | {{ me.email }} | role: {{ me.role }}</div>
        <button @click="loadMe">Refresh</button>
        <button @click="logout">Logout</button>
      </section>

      <section class="card">
        <h2>Defaults</h2>
        <div class="row">
          <select v-model="defaults.income_category_id">
            <option v-for="c in incomeCategories" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
          </select>
          <select v-model="defaults.expense_category_id">
            <option v-for="c in expenseCategories" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
          </select>
          <button @click="saveDefaults">Save defaults</button>
        </div>
      </section>

      <section class="card">
        <h2>Accounts</h2>
        <form class="row" @submit.prevent="createAccount">
          <input v-model="accountForm.name" placeholder="Name" required />
          <input v-model="accountForm.type" placeholder="Type" required />
          <input v-model="accountForm.currency" placeholder="RUB" required />
          <input v-model="accountForm.balance" type="number" step="0.01" required />
          <button>Create</button>
        </form>
      </section>

      <section class="card">
        <h2>Transactions</h2>
        <form class="row" @submit.prevent="createTransaction">
          <select v-model="transactionForm.type"><option value="expense">expense</option><option value="income">income</option></select>
          <select v-model="transactionForm.account_id"><option v-for="a in accounts" :key="a.id" :value="String(a.id)">{{ a.name }}</option></select>
          <input v-model="transactionForm.amount" type="number" step="0.01" required />
          <input v-model="transactionForm.description" placeholder="Description" />
          <button>Add</button>
        </form>
        <div class="row">
          <select v-model="filters.type"><option value="">All</option><option value="income">income</option><option value="expense">expense</option></select>
          <select v-model="filters.account_id"><option value="">All accounts</option><option v-for="a in accounts" :key="a.id" :value="String(a.id)">{{ a.name }}</option></select>
          <button @click="loadTransactions">Apply</button>
        </div>
      </section>

      <section class="card">
        <h2>Recurring</h2>
        <form class="row" @submit.prevent="createRecurring">
          <select v-model="recurringForm.type"><option value="expense">expense</option><option value="income">income</option></select>
          <select v-model="recurringForm.account_id"><option v-for="a in accounts" :key="a.id" :value="String(a.id)">{{ a.name }}</option></select>
          <select v-model="recurringForm.category_id"><option v-for="c in recurringForm.type==='income' ? incomeCategories : expenseCategories" :key="c.id" :value="String(c.id)">{{ c.name }}</option></select>
          <input v-model="recurringForm.amount" type="number" step="0.01" required />
          <select v-model="recurringForm.frequency"><option value="daily">daily</option><option value="weekly">weekly</option><option value="monthly">monthly</option></select>
          <input v-model="recurringForm.start_at" type="datetime-local" required />
          <button>Add</button>
        </form>
      </section>

      <section class="card">
        <h2>Budget Plans</h2>
        <form class="row" @submit.prevent="createPlan">
          <input v-model="planForm.name" placeholder="Plan name" required />
          <input v-model="planForm.period_from" type="date" required />
          <input v-model="planForm.period_to" type="date" required />
          <select v-model="planForm.goal_type"><option value="savings">savings</option><option value="target_balance">target_balance</option></select>
          <input v-model="planForm.goal_amount" type="number" step="0.01" placeholder="Goal" />
          <button>Create</button>
        </form>
      </section>

      <section class="card">
        <h2>Analytics</h2>
        <button @click="loadSummary">Refresh summary</button>
        <pre>{{ summary }}</pre>
      </section>

      <section class="card">
        <h2>AI Chat</h2>
        <div class="row">
          <select v-model="selectedChatId" @change="selectChat(selectedChatId)">
            <option v-for="c in chats" :key="c.id" :value="String(c.id)">#{{ c.id }} {{ c.title }}</option>
          </select>
          <button @click="createChat">New chat</button>
        </div>
        <div class="log">
          <div v-for="m in chatMessages" :key="m.id"><strong>{{ m.role }}:</strong> {{ m.content }}</div>
          <div v-if="chatStreaming"><strong>assistant:</strong> {{ chatStreaming }}</div>
        </div>
        <form class="row" @submit.prevent="sendChatMessage">
          <input v-model="chatInput" placeholder="Ask AI" />
          <button>Send</button>
        </form>
      </section>

      <section class="card">
        <h2>Notifications</h2>
        <div class="row">
          <button @click="loadNotifications">Refresh</button>
          <button @click="subscribePush">Enable push</button>
        </div>
        <ul>
          <li v-for="n in notifications" :key="n.id">
            <strong>{{ n.title }}</strong> | {{ n.read_at ? 'read' : 'unread' }}
            <button v-if="!n.read_at" @click="markNotificationRead(n.id)">Mark read</button>
          </li>
        </ul>
      </section>

      <section class="card">
        <h2>Support Chat</h2>
        <div class="row">
          <select v-model="selectedSupportChatId" @change="selectSupportChat(selectedSupportChatId)">
            <option v-for="c in supportChats" :key="c.id" :value="String(c.id)">#{{ c.id }} {{ c.status }}</option>
          </select>
          <button @click="createSupportChat">New ticket</button>
        </div>
        <div class="log">
          <div v-for="m in supportMessages" :key="m.id"><strong>{{ m.sender_role }}:</strong> {{ m.content }}</div>
        </div>
        <form class="row" @submit.prevent="sendSupportMessage">
          <input v-model="supportInput" placeholder="Message to support" />
          <button>Send</button>
        </form>
      </section>

      <section v-if="me.role === 'ADMIN'" class="card">
        <h2>Admin</h2>
        <button @click="loadAdminUsers">Load users</button>
        <button @click="loadAdminDashboard">Load dashboard</button>
        <pre>{{ adminUsers }}</pre>
        <pre>{{ adminDashboard }}</pre>
      </section>
    </template>
  </main>
</template>
