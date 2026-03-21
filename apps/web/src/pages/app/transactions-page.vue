<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useAuth } from '../../app/providers/auth-provider'
import { useFinance } from '../../features/finance/use-finance'

const auth = useAuth()
const finance = useFinance()

const filters = reactive({ type: '', account_id: '' })
const transactionForm = reactive({ type: 'expense', account_id: '', amount: '', description: '' })
const editForm = reactive({ amount: '', description: '', occurred_at: '' })
const editingId = ref('')
const uiError = ref('')

watch(
  () => auth.state.user,
  async (user) => {
    uiError.value = ''
    try {
      await finance.ensureFinanceLoaded(user)
      if (!transactionForm.account_id && finance.state.accounts.length) {
        transactionForm.account_id = String(finance.state.accounts[0].id)
      }
    } catch (error) {
      uiError.value = error.message || 'Не удалось загрузить операции'
    }
  },
  { immediate: true }
)

const rows = computed(() => finance.state.transactions)
const isEmpty = computed(() => !finance.state.loading && rows.value.length === 0)

function toInputDateTime(value) {
  if (!value) return ''
  const dt = new Date(value)
  if (Number.isNaN(dt.getTime())) return ''
  const pad = (n) => String(n).padStart(2, '0')
  return `${dt.getFullYear()}-${pad(dt.getMonth() + 1)}-${pad(dt.getDate())}T${pad(dt.getHours())}:${pad(dt.getMinutes())}`
}

async function applyFilters() {
  uiError.value = ''
  try {
    await finance.loadTransactions(filters)
  } catch (error) {
    uiError.value = error.message || 'Не удалось применить фильтры'
  }
}

async function submitCreate() {
  uiError.value = ''
  try {
    await finance.createTransaction(transactionForm, filters)
    transactionForm.amount = ''
    transactionForm.description = ''
  } catch (error) {
    uiError.value = error.message || 'Не удалось создать операцию'
  }
}

function startEdit(item) {
  editingId.value = String(item.id)
  editForm.amount = String(item.amount)
  editForm.description = item.description || ''
  editForm.occurred_at = toInputDateTime(item.occurred_at)
}

function cancelEdit() {
  editingId.value = ''
}

async function saveEdit(id) {
  uiError.value = ''
  try {
    await finance.updateTransaction(id, editForm, filters)
    editingId.value = ''
  } catch (error) {
    uiError.value = error.message || 'Не удалось обновить операцию'
  }
}

async function remove(id) {
  uiError.value = ''
  try {
    await finance.deleteTransaction(id, filters)
    if (editingId.value === String(id)) editingId.value = ''
  } catch (error) {
    uiError.value = error.message || 'Не удалось удалить операцию'
  }
}
</script>

<template>
  <section class="card page">
    <h1>Операции</h1>
    <p v-if="uiError" class="error-text">{{ uiError }}</p>

    <form class="row" @submit.prevent="submitCreate">
      <select v-model="transactionForm.type">
        <option value="expense">расход</option>
        <option value="income">доход</option>
      </select>
      <select v-model="transactionForm.account_id">
        <option v-for="account in finance.state.accounts" :key="account.id" :value="String(account.id)">
          {{ account.name }}
        </option>
      </select>
      <input v-model="transactionForm.amount" type="number" step="0.01" required />
      <input v-model="transactionForm.description" placeholder="Описание" />
      <button :disabled="finance.state.loading">Добавить</button>
    </form>

    <div class="row">
      <select v-model="filters.type">
        <option value="">All</option>
        <option value="income">доход</option>
        <option value="expense">расход</option>
      </select>
      <select v-model="filters.account_id">
        <option value="">Все счета</option>
        <option v-for="account in finance.state.accounts" :key="account.id" :value="String(account.id)">
          {{ account.name }}
        </option>
      </select>
      <button type="button" @click="applyFilters">Применить</button>
    </div>

    <p v-if="finance.state.loading">Загрузка...</p>
    <p v-else-if="isEmpty">Операций нет.</p>

    <table v-else>
      <thead>
        <tr>
          <th>ID</th>
          <th>Тип</th>
          <th>Счет</th>
          <th>Сумма</th>
          <th>Описание</th>
          <th>Дата</th>
          <th>Действия</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in rows" :key="item.id">
          <td>{{ item.id }}</td>
          <td>{{ item.type }}</td>
          <td>{{ item.account_id }}</td>
          <td>
            <input v-if="editingId === String(item.id)" v-model="editForm.amount" type="number" step="0.01" />
            <span v-else>{{ item.amount }}</span>
          </td>
          <td>
            <input v-if="editingId === String(item.id)" v-model="editForm.description" />
            <span v-else>{{ item.description }}</span>
          </td>
          <td>
            <input v-if="editingId === String(item.id)" v-model="editForm.occurred_at" type="datetime-local" />
            <span v-else>{{ item.occurred_at }}</span>
          </td>
          <td class="table-actions">
            <button v-if="editingId !== String(item.id)" type="button" @click="startEdit(item)">Изменить</button>
            <button v-if="editingId === String(item.id)" type="button" @click="saveEdit(item.id)">Сохранить</button>
            <button v-if="editingId === String(item.id)" type="button" @click="cancelEdit">Отмена</button>
            <button type="button" @click="remove(item.id)">Удалить</button>
          </td>
        </tr>
      </tbody>
    </table>
  </section>
</template>

