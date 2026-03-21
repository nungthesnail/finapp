<script setup>
import { computed, reactive, watch } from 'vue'
import { useAuth } from '../../app/providers/auth-provider'
import { useFinance } from '../../features/finance/use-finance'

const auth = useAuth()
const finance = useFinance()
const filters = reactive({ date_from: '', date_to: '' })

watch(
  () => auth.state.user,
  async (user) => {
    await finance.ensureFinanceLoaded(user)
  },
  { immediate: true }
)

const summaryCards = computed(() => {
  const summary = finance.state.summary || {}
  return [
    { label: 'Доход', value: summary.income_total ?? 0 },
    { label: 'Расход', value: summary.expense_total ?? 0 },
    { label: 'Итог', value: summary.net_total ?? 0 },
  ]
})

const timeseriesByDate = computed(() => {
  const byDate = {}
  for (const row of finance.state.analyticsTimeseries) {
    const date = String(row.d)
    if (!byDate[date]) byDate[date] = { date, income: 0, expense: 0 }
    if (row.type === 'income') byDate[date].income = Number(row.total || 0)
    if (row.type === 'expense') byDate[date].expense = Number(row.total || 0)
  }
  return Object.values(byDate).sort((a, b) => a.date.localeCompare(b.date))
})

const maxTimeseriesAmount = computed(() =>
  Math.max(
    1,
    ...timeseriesByDate.value.flatMap((item) => [item.income, item.expense])
  )
)

function amountStyle(value) {
  const width = Math.max(4, Math.round((Number(value || 0) / maxTimeseriesAmount.value) * 100))
  return { width: `${width}%` }
}

const topCategories = computed(() => {
  const categoriesById = new Map(finance.state.expenseCategories.map((item) => [String(item.id), item.name]))
  return finance.state.analyticsCategories.map((row) => ({
    ...row,
    category_name: categoriesById.get(String(row.category_id)) || `Категория #${row.category_id}`,
  }))
})

async function applySummaryFilters() {
  await finance.loadSummary(filters)
}
</script>

<template>
  <section class="card page">
    <h1>Аналитика</h1>
    <form class="row" @submit.prevent="applySummaryFilters">
      <input v-model="filters.date_from" type="date" aria-label="Дата от" />
      <input v-model="filters.date_to" type="date" aria-label="Дата до" />
      <button type="submit">Применить период</button>
      <button type="button" @click="finance.loadSummary">Сбросить период сводки</button>
      <button type="button" @click="finance.loadAnalyticsTimeseries">Обновить динамику</button>
      <button type="button" @click="finance.loadAnalyticsCategories">Обновить категории</button>
    </form>

    <section class="row">
      <article v-for="item in summaryCards" :key="item.label" class="card">
        <strong>{{ item.label }}</strong>
        <div>{{ item.value }}</div>
      </article>
    </section>

    <section class="card page">
      <h2>Динамика</h2>
      <p v-if="timeseriesByDate.length === 0">Нет данных динамики.</p>
      <div v-else class="chart-list">
        <div v-for="point in timeseriesByDate" :key="point.date" class="chart-row">
          <div class="chart-date">{{ point.date }}</div>
          <div class="chart-bars">
            <div class="chart-bar income" :style="amountStyle(point.income)">+{{ point.income }}</div>
            <div class="chart-bar expense" :style="amountStyle(point.expense)">-{{ point.expense }}</div>
          </div>
        </div>
      </div>
    </section>

    <section class="card page">
      <h2>Топ категорий расходов</h2>
      <p v-if="topCategories.length === 0">Данных по категориям пока нет.</p>
      <table v-else>
        <thead>
          <tr>
            <th>Категория</th>
            <th>Сумма расходов</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in topCategories" :key="item.category_id">
            <td>{{ item.category_name }}</td>
            <td>{{ item.total }}</td>
          </tr>
        </tbody>
      </table>
    </section>
  </section>
</template>
