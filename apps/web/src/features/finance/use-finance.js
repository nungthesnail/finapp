import { reactive, readonly } from 'vue'
import { apiFetch } from '../../shared/api/client'

const state = reactive({
  status: '',
  loading: false,
  loadedUserId: null,

  accounts: [],
  transactions: [],
  incomeCategories: [],
  expenseCategories: [],
  recurring: [],
  budgetPlans: [],
  summary: null,
  analyticsTimeseries: [],
  analyticsCategories: [],

  defaults: {
    income_category_id: '',
    expense_category_id: '',
  },
})

function setStatus(message) {
  state.status = message
}

function resetForUser(user) {
  if (!user) {
    state.loadedUserId = null
    state.accounts = []
    state.transactions = []
    state.incomeCategories = []
    state.expenseCategories = []
    state.recurring = []
    state.budgetPlans = []
    state.summary = null
    state.analyticsTimeseries = []
    state.analyticsCategories = []
    state.defaults.income_category_id = ''
    state.defaults.expense_category_id = ''
    setStatus('Не авторизован')
    return
  }

  setStatus(`Авторизован: ${user.phone}`)
}

async function loadCategories() {
  const [income, expense, defaults] = await Promise.all([
    apiFetch('/income-categories'),
    apiFetch('/expense-categories'),
    apiFetch('/users/category-defaults'),
  ])

  state.incomeCategories = income.items
  state.expenseCategories = expense.items
  state.defaults.income_category_id = String(defaults.item.income_category_id || '')
  state.defaults.expense_category_id = String(defaults.item.expense_category_id || '')
}

async function saveDefaults(payload = null) {
  const incomeId = payload?.income_category_id ?? state.defaults.income_category_id
  const expenseId = payload?.expense_category_id ?? state.defaults.expense_category_id

  await apiFetch('/users/category-defaults', {
    method: 'PUT',
    body: JSON.stringify({
      income_category_id: Number(incomeId),
      expense_category_id: Number(expenseId),
    }),
  })

  state.defaults.income_category_id = String(incomeId || '')
  state.defaults.expense_category_id = String(expenseId || '')
}

async function createIncomeCategory(name) {
  await apiFetch('/income-categories', {
    method: 'POST',
    body: JSON.stringify({ name }),
  })
  await loadCategories()
}

async function updateIncomeCategory(id, name) {
  await apiFetch(`/income-categories/${id}`, {
    method: 'PUT',
    body: JSON.stringify({ name }),
  })
  await loadCategories()
}

async function deleteIncomeCategory(id) {
  await apiFetch(`/income-categories/${id}`, { method: 'DELETE' })
  await loadCategories()
}

async function createExpenseCategory(name) {
  await apiFetch('/expense-categories', {
    method: 'POST',
    body: JSON.stringify({ name }),
  })
  await loadCategories()
}

async function updateExpenseCategory(id, name) {
  await apiFetch(`/expense-categories/${id}`, {
    method: 'PUT',
    body: JSON.stringify({ name }),
  })
  await loadCategories()
}

async function deleteExpenseCategory(id) {
  await apiFetch(`/expense-categories/${id}`, { method: 'DELETE' })
  await loadCategories()
}

async function loadAccounts() {
  const data = await apiFetch('/accounts')
  state.accounts = data.items
}

async function createAccount(payload) {
  await apiFetch('/accounts', {
    method: 'POST',
    body: JSON.stringify({
      name: payload.name,
      type: payload.type,
      currency: payload.currency,
      balance: Number(payload.balance || 0),
    }),
  })

  await loadAccounts()
}

async function updateAccount(id, payload) {
  await apiFetch(`/accounts/${id}`, {
    method: 'PUT',
    body: JSON.stringify({
      name: payload.name,
      type: payload.type,
      currency: payload.currency,
    }),
  })

  await loadAccounts()
}

async function deleteAccount(id) {
  await apiFetch(`/accounts/${id}`, { method: 'DELETE' })
  await loadAccounts()
}

function buildTransactionsQuery(filters) {
  const query = new URLSearchParams()

  if (filters.type) {
    query.set('type', filters.type)
  }
  if (filters.account_id) {
    query.set('account_id', filters.account_id)
  }
  if (filters.date_from) {
    query.set('date_from', filters.date_from)
  }
  if (filters.date_to) {
    query.set('date_to', filters.date_to)
  }

  return query.toString() ? `?${query.toString()}` : ''
}

async function loadTransactions(filters = {}) {
  const qs = buildTransactionsQuery(filters)
  const data = await apiFetch(`/transactions${qs}`)
  console.log(data)
  state.transactions = data.items
}

async function createTransaction(payload, filters = {}) {
  await apiFetch('/transactions', {
    method: 'POST',
    body: JSON.stringify({
      type: payload.type,
      account_id: Number(payload.account_id),
      category_id: payload.category_id ? Number(payload.category_id) : undefined,
      amount: Number(payload.amount),
      description: payload.description || '',
    }),
  })

  await Promise.all([loadTransactions(filters), loadSummary()])
}

async function updateTransaction(id, payload, filters = {}) {
  await apiFetch(`/transactions/${id}`, {
    method: 'PUT',
    body: JSON.stringify({
      amount: Number(payload.amount),
      description: payload.description || null,
      occurred_at: payload.occurred_at || undefined,
    }),
  })

  await Promise.all([loadTransactions(filters), loadSummary()])
}

async function deleteTransaction(id, filters = {}) {
  await apiFetch(`/transactions/${id}`, { method: 'DELETE' })
  await Promise.all([loadTransactions(filters), loadSummary()])
}

async function loadRecurring() {
  const data = await apiFetch('/recurring-transactions')
  state.recurring = data.items
}

async function createRecurring(payload) {
  await apiFetch('/recurring-transactions', {
    method: 'POST',
    body: JSON.stringify({
      type: payload.type,
      account_id: Number(payload.account_id),
      category_id: Number(payload.category_id),
      amount: Number(payload.amount),
      frequency: payload.frequency,
      start_at: new Date(payload.start_at).toISOString(),
    }),
  })

  await loadRecurring()
}

async function updateRecurring(id, payload) {
  await apiFetch(`/recurring-transactions/${id}`, {
    method: 'PUT',
    body: JSON.stringify({
      amount: Number(payload.amount),
      description: payload.description || null,
      is_active: Boolean(payload.is_active),
      end_at: payload.end_at || null,
      next_run_at: payload.next_run_at || undefined,
    }),
  })

  await loadRecurring()
}

async function deleteRecurring(id) {
  await apiFetch(`/recurring-transactions/${id}`, { method: 'DELETE' })
  await loadRecurring()
}

async function loadPlans() {
  const data = await apiFetch('/budget-plans')
  state.budgetPlans = data.items
}

async function createPlan(payload) {
  await apiFetch('/budget-plans', {
    method: 'POST',
    body: JSON.stringify({
      name: payload.name,
      period_from: payload.period_from,
      period_to: payload.period_to,
      goal_type: payload.goal_type,
      goal_amount: payload.goal_amount ? Number(payload.goal_amount) : null,
      categories: [],
    }),
  })

  await loadPlans()
}

async function updatePlan(id, payload) {
  await apiFetch(`/budget-plans/${id}`, {
    method: 'PUT',
    body: JSON.stringify({
      name: payload.name,
      period_from: payload.period_from,
      period_to: payload.period_to,
      goal_type: payload.goal_type,
      goal_amount: payload.goal_amount ? Number(payload.goal_amount) : null,
    }),
  })

  await loadPlans()
}

async function deletePlan(id) {
  await apiFetch(`/budget-plans/${id}`, { method: 'DELETE' })
  await loadPlans()
}

function buildDateQuery(filters = {}) {
  const query = new URLSearchParams()

  if (filters.date_from) {
    query.set('date_from', filters.date_from)
  }
  if (filters.date_to) {
    query.set('date_to', filters.date_to)
  }

  return query.toString() ? `?${query.toString()}` : ''
}

async function loadSummary(filters = {}) {
  const qs = buildDateQuery(filters)
  state.summary = await apiFetch(`/analytics/summary${qs}`)
}

async function loadAnalyticsTimeseries(filters = {}) {
  const qs = buildDateQuery(filters)
  const data = await apiFetch(`/analytics/timeseries${qs}`)
  state.analyticsTimeseries = data.items || []
}

async function loadAnalyticsCategories(filters = {}) {
  const qs = buildDateQuery(filters)
  const data = await apiFetch(`/analytics/categories${qs}`)
  state.analyticsCategories = data.items || []
}

async function ensureFinanceLoaded(user) {
  if (!user) {
    resetForUser(user)
    return
  }

  if (state.loading) {
    return
  }

  if (state.loadedUserId === user.id) {
    return
  }

  state.loading = true
  resetForUser(user)

  try {
    await Promise.all([
      loadCategories(),
      loadAccounts(),
      loadTransactions(),
      loadRecurring(),
      loadPlans(),
      loadSummary(),
      loadAnalyticsTimeseries(),
      loadAnalyticsCategories(),
    ])
    state.loadedUserId = user.id
  } finally {
    state.loading = false
  }
}

export function useFinance() {
  return {
    state: readonly(state),
    setStatus,
    resetForUser,
    ensureFinanceLoaded,
    loadCategories,
    saveDefaults,
    createIncomeCategory,
    updateIncomeCategory,
    deleteIncomeCategory,
    createExpenseCategory,
    updateExpenseCategory,
    deleteExpenseCategory,
    loadAccounts,
    createAccount,
    updateAccount,
    deleteAccount,
    loadTransactions,
    createTransaction,
    updateTransaction,
    deleteTransaction,
    loadRecurring,
    createRecurring,
    updateRecurring,
    deleteRecurring,
    loadPlans,
    createPlan,
    updatePlan,
    deletePlan,
    loadSummary,
    loadAnalyticsTimeseries,
    loadAnalyticsCategories,
  }
}
