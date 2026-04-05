<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useAuth } from '../../app/providers/auth-provider'
import { useFinance } from '../../features/finance/use-finance'

const auth = useAuth()
const finance = useFinance()

const accountForm = reactive({ name: '', type: '', currency: 'RUB', balance: 0 })
const editForm = reactive({ name: '', type: '', currency: 'RUB', balance: 0 })
const editingId = ref('')
const uiError = ref('')

watch(
  () => auth.state.user,
  async (user) => {
    uiError.value = ''
    try {
      await finance.ensureFinanceLoaded(user)
    } catch (error) {
      uiError.value = error.message || 'Не удалось загрузить счета'
    }
  },
  { immediate: true }
)

const isEmpty = computed(() => !finance.state.loading && finance.state.accounts.length === 0)

async function submitCreate() {
  uiError.value = ''
  try {
    await finance.createAccount(accountForm)
    accountForm.name = ''
    accountForm.type = ''
    accountForm.currency = 'RUB'
    accountForm.balance = 0
  } catch (error) {
    uiError.value = error.message || 'Не удалось создать счет'
  }
}

function startEdit(item) {
  editingId.value = String(item.id)
  editForm.name = item.name
  editForm.type = item.type
  editForm.currency = item.currency
  editForm.balance = Number(item.balance || 0)
}

function cancelEdit() {
  editingId.value = ''
}

async function saveEdit(id) {
  uiError.value = ''
  try {
    await finance.updateAccount(id, editForm)
    editingId.value = ''
  } catch (error) {
    uiError.value = error.message || 'Не удалось обновить счет'
  }
}

async function remove(id) {
  uiError.value = ''
  try {
    await finance.deleteAccount(id)
    if (editingId.value === String(id)) editingId.value = ''
  } catch (error) {
    uiError.value = error.message || 'Не удалось удалить счет'
  }
}
</script>

<template>
  <section class="card page">
    <h1>Счета</h1>
    <p v-if="uiError" class="error-text">{{ uiError }}</p>

    <form class="row" @submit.prevent="submitCreate">
      <input v-model="accountForm.name" placeholder="Название" required />
      <input v-model="accountForm.type" placeholder="Тип" required />
      <input v-model="accountForm.currency" placeholder="RUB" required />
      <input v-model="accountForm.balance" type="number" step="0.01" required />
      <button :disabled="finance.state.loading">Создать</button>
    </form>

    <p v-if="finance.state.loading">Загрузка...</p>
    <p v-else-if="isEmpty">Счетов пока нет.</p>

    <table v-else class="accounts-table">
      <thead>
        <tr>
          <th>ID</th>
          <th>Название</th>
          <th>Тип</th>
          <th>Валюта</th>
          <th>Баланс</th>
          <th>Действия</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in finance.state.accounts" :key="item.id">
          <td>{{ item.id }}</td>
          <td>
            <input v-if="editingId === String(item.id)" v-model="editForm.name" />
            <span v-else>{{ item.name }}</span>
          </td>
          <td>
            <input v-if="editingId === String(item.id)" v-model="editForm.type" />
            <span v-else>{{ item.type }}</span>
          </td>
          <td>
            <input v-if="editingId === String(item.id)" v-model="editForm.currency" />
            <span v-else>{{ item.currency }}</span>
          </td>
          <td>
            <input v-if="editingId === String(item.id)" v-model="editForm.balance" type="number" step="0.01" />
            <span v-else>{{ item.balance }}</span>
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

