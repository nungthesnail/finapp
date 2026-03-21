<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useAuth } from '../../app/providers/auth-provider'
import { useFinance } from '../../features/finance/use-finance'

const auth = useAuth()
const finance = useFinance()

const recurringForm = reactive({
  type: 'expense',
  account_id: '',
  category_id: '',
  amount: '',
  frequency: 'monthly',
  start_at: '',
})
const editForm = reactive({ amount: '', description: '', is_active: true, end_at: '', next_run_at: '' })
const editingId = ref('')
const uiError = ref('')

watch(
  () => auth.state.user,
  async (user) => {
    uiError.value = ''
    try {
      await finance.ensureFinanceLoaded(user)
      if (!recurringForm.account_id && finance.state.accounts.length) recurringForm.account_id = String(finance.state.accounts[0].id)
      if (!recurringForm.category_id && finance.state.expenseCategories.length) recurringForm.category_id = String(finance.state.expenseCategories[0].id)
    } catch (error) {
      uiError.value = error.message || 'Не удалось загрузить периодические операции'
    }
  },
  { immediate: true }
)

const categoryOptions = computed(() =>
  recurringForm.type === 'income' ? finance.state.incomeCategories : finance.state.expenseCategories
)

const isEmpty = computed(() => !finance.state.loading && finance.state.recurring.length === 0)

function toInputDateTime(value) {
  if (!value) return ''
  const dt = new Date(value)
  if (Number.isNaN(dt.getTime())) return ''
  const pad = (n) => String(n).padStart(2, '0')
  return `${dt.getFullYear()}-${pad(dt.getMonth() + 1)}-${pad(dt.getDate())}T${pad(dt.getHours())}:${pad(dt.getMinutes())}`
}

async function submitCreate() {
  uiError.value = ''
  try {
    await finance.createRecurring(recurringForm)
    recurringForm.amount = ''
  } catch (error) {
    uiError.value = error.message || 'Не удалось создать периодическую операцию'
  }
}

function startEdit(item) {
  editingId.value = String(item.id)
  editForm.amount = String(item.amount)
  editForm.description = item.description || ''
  editForm.is_active = Boolean(item.is_active)
  editForm.end_at = item.end_at ? item.end_at.slice(0, 10) : ''
  editForm.next_run_at = toInputDateTime(item.next_run_at)
}

function cancelEdit() {
  editingId.value = ''
}

async function saveEdit(id) {
  uiError.value = ''
  try {
    await finance.updateRecurring(id, editForm)
    editingId.value = ''
  } catch (error) {
    uiError.value = error.message || 'Не удалось обновить периодическую операцию'
  }
}

async function remove(id) {
  uiError.value = ''
  try {
    await finance.deleteRecurring(id)
    if (editingId.value === String(id)) editingId.value = ''
  } catch (error) {
    uiError.value = error.message || 'Не удалось удалить периодическую операцию'
  }
}
</script>

<template>
  <section class="card page">
    <h1>Периодические операции</h1>
    <p v-if="uiError" class="error-text">{{ uiError }}</p>

    <form class="row" @submit.prevent="submitCreate">
      <select v-model="recurringForm.type">
        <option value="expense">расход</option>
        <option value="income">доход</option>
      </select>
      <select v-model="recurringForm.account_id">
        <option v-for="account in finance.state.accounts" :key="account.id" :value="String(account.id)">{{ account.name }}</option>
      </select>
      <select v-model="recurringForm.category_id">
        <option v-for="category in categoryOptions" :key="category.id" :value="String(category.id)">{{ category.name }}</option>
      </select>
      <input v-model="recurringForm.amount" type="number" step="0.01" required />
      <select v-model="recurringForm.frequency">
        <option value="daily">ежедневно</option>
        <option value="weekly">еженедельно</option>
        <option value="monthly">ежемесячно</option>
      </select>
      <input v-model="recurringForm.start_at" type="datetime-local" required />
      <button :disabled="finance.state.loading">Добавить</button>
    </form>

    <p v-if="finance.state.loading">Загрузка...</p>
    <p v-else-if="isEmpty">Периодических операций нет.</p>

    <table v-else>
      <thead>
        <tr>
          <th>ID</th>
          <th>Тип</th>
          <th>Сумма</th>
          <th>Частота</th>
          <th>Следующий запуск</th>
          <th>Активно</th>
          <th>Действия</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in finance.state.recurring" :key="item.id">
          <td>{{ item.id }}</td>
          <td>{{ item.type }}</td>
          <td>
            <input v-if="editingId === String(item.id)" v-model="editForm.amount" type="number" step="0.01" />
            <span v-else>{{ item.amount }}</span>
          </td>
          <td>{{ item.frequency }}</td>
          <td>
            <input v-if="editingId === String(item.id)" v-model="editForm.next_run_at" type="datetime-local" />
            <span v-else>{{ item.next_run_at }}</span>
          </td>
          <td>
            <input v-if="editingId === String(item.id)" v-model="editForm.is_active" type="checkbox" />
            <span v-else>{{ item.is_active ? 'да' : 'нет' }}</span>
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

