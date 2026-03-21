<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useAuth } from '../../app/providers/auth-provider'
import { useFinance } from '../../features/finance/use-finance'

const auth = useAuth()
const finance = useFinance()

const planForm = reactive({ name: '', period_from: '', period_to: '', goal_type: 'savings', goal_amount: '' })
const editForm = reactive({ name: '', period_from: '', period_to: '', goal_type: 'savings', goal_amount: '' })
const editingId = ref('')
const uiError = ref('')

watch(
  () => auth.state.user,
  async (user) => {
    uiError.value = ''
    try {
      await finance.ensureFinanceLoaded(user)
    } catch (error) {
      uiError.value = error.message || 'Не удалось загрузить планы'
    }
  },
  { immediate: true }
)

const isEmpty = computed(() => !finance.state.loading && finance.state.budgetPlans.length === 0)

async function submitCreate() {
  uiError.value = ''
  try {
    await finance.createPlan(planForm)
    planForm.name = ''
    planForm.period_from = ''
    planForm.period_to = ''
    planForm.goal_amount = ''
  } catch (error) {
    uiError.value = error.message || 'Не удалось создать план'
  }
}

function startEdit(item) {
  editingId.value = String(item.id)
  editForm.name = item.name
  editForm.period_from = item.period_from
  editForm.period_to = item.period_to
  editForm.goal_type = item.goal_type
  editForm.goal_amount = item.goal_amount || ''
}

function cancelEdit() {
  editingId.value = ''
}

async function saveEdit(id) {
  uiError.value = ''
  try {
    await finance.updatePlan(id, editForm)
    editingId.value = ''
  } catch (error) {
    uiError.value = error.message || 'Не удалось обновить план'
  }
}

async function remove(id) {
  uiError.value = ''
  try {
    await finance.deletePlan(id)
    if (editingId.value === String(id)) editingId.value = ''
  } catch (error) {
    uiError.value = error.message || 'Не удалось удалить план'
  }
}
</script>

<template>
  <section class="card page">
    <h1>Бюджетные планы</h1>
    <p v-if="uiError" class="error-text">{{ uiError }}</p>

    <form class="row" @submit.prevent="submitCreate">
      <input v-model="planForm.name" placeholder="Название плана" required />
      <input v-model="planForm.period_from" type="date" required />
      <input v-model="planForm.period_to" type="date" required />
      <select v-model="planForm.goal_type">
        <option value="savings">накопления</option>
        <option value="target_balance">целевой баланс</option>
      </select>
      <input v-model="planForm.goal_amount" type="number" step="0.01" placeholder="Цель" />
      <button :disabled="finance.state.loading">Создать</button>
    </form>

    <p v-if="finance.state.loading">Загрузка...</p>
    <p v-else-if="isEmpty">Планов пока нет.</p>

    <table v-else>
      <thead>
        <tr>
          <th>ID</th>
          <th>Название</th>
          <th>Период</th>
          <th>Тип цели</th>
          <th>Сумма цели</th>
          <th>Действия</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in finance.state.budgetPlans" :key="item.id">
          <td>{{ item.id }}</td>
          <td>
            <input v-if="editingId === String(item.id)" v-model="editForm.name" />
            <span v-else>{{ item.name }}</span>
          </td>
          <td>
            <div v-if="editingId === String(item.id)" class="row period-edit">
              <input v-model="editForm.period_from" type="date" />
              <input v-model="editForm.period_to" type="date" />
            </div>
            <span v-else>{{ item.period_from }} - {{ item.period_to }}</span>
          </td>
          <td>
            <select v-if="editingId === String(item.id)" v-model="editForm.goal_type">
              <option value="savings">накопления</option>
              <option value="target_balance">целевой баланс</option>
            </select>
            <span v-else>{{ item.goal_type }}</span>
          </td>
          <td>
            <input v-if="editingId === String(item.id)" v-model="editForm.goal_amount" type="number" step="0.01" />
            <span v-else>{{ item.goal_amount }}</span>
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

