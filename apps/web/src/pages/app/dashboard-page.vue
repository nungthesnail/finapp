<script setup>
import { computed, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { useAuth } from '../../app/providers/auth-provider'
import { useFinance } from '../../features/finance/use-finance'

const auth = useAuth()
const finance = useFinance()

watch(
  () => auth.state.user,
  async (user) => {
    await finance.ensureFinanceLoaded(user)
  },
  { immediate: true }
)

const cards = computed(() => {
  const summary = finance.state.summary || {}
  const totalBalance = finance.state.accounts.reduce((acc, account) => acc + Number(account.balance || 0), 0)
  return [
    { label: 'Доход', value: summary.income_total ?? 0 },
    { label: 'Расход', value: summary.expense_total ?? 0 },
    { label: 'Итог', value: summary.net_total ?? 0 },
    { label: 'Баланс', value: totalBalance },
    { label: 'Счета', value: finance.state.accounts.length },
    { label: 'Активные планы', value: finance.state.budgetPlans.length },
  ]
})

const latestTransactions = computed(() => finance.state.transactions.slice(0, 5))

const timeseriesPreview = computed(() => {
  const byDate = {}
  for (const row of finance.state.analyticsTimeseries) {
    const date = String(row.d)
    if (!byDate[date]) byDate[date] = { date, income: 0, expense: 0 }
    if (row.type === 'income') byDate[date].income = Number(row.total || 0)
    if (row.type === 'expense') byDate[date].expense = Number(row.total || 0)
  }

  return Object.values(byDate)
    .sort((a, b) => a.date.localeCompare(b.date))
    .slice(-7)
})

const maxPreviewAmount = computed(() =>
  Math.max(
    1,
    ...timeseriesPreview.value.flatMap((item) => [item.income, item.expense])
  )
)

function amountStyle(value) {
  const width = Math.max(4, Math.round((Number(value || 0) / maxPreviewAmount.value) * 100))
  return { width: `${width}%` }
}
</script>

<template>
  <main class="page">
    <section class="card">
      <h1>Обзор</h1>
      <p>{{ finance.state.status }}</p>
    </section>

    <section class="row">
      <article v-for="card in cards" :key="card.label" class="card">
        <strong>{{ card.label }}</strong>
        <div>{{ card.value }}</div>
      </article>
    </section>

    <section class="card page">
      <h2>Денежный поток за 7 дней</h2>
      <p v-if="timeseriesPreview.length === 0">Данных аналитики пока нет.</p>
      <div v-else class="chart-list">
        <div v-for="point in timeseriesPreview" :key="point.date" class="chart-row">
          <div class="chart-date">{{ point.date }}</div>
          <div class="chart-bars">
            <div class="chart-bar income" :style="amountStyle(point.income)">+{{ point.income }}</div>
            <div class="chart-bar expense" :style="amountStyle(point.expense)">-{{ point.expense }}</div>
          </div>
        </div>
      </div>
    </section>

    <section class="card page">
      <h2>Последние операции</h2>
      <p v-if="latestTransactions.length === 0">Операций пока нет.</p>
      <table v-else>
        <thead>
          <tr>
            <th>ID</th>
            <th>Тип</th>
            <th>Сумма</th>
            <th>Описание</th>
            <th>Дата</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in latestTransactions" :key="item.id">
            <td>{{ item.id }}</td>
            <td>{{ item.type }}</td>
            <td>{{ item.amount }}</td>
            <td>{{ item.description }}</td>
            <td>{{ item.occurred_at }}</td>
          </tr>
        </tbody>
      </table>
    </section>

    <section class="card row">
      <RouterLink to="/app/accounts">Счета</RouterLink>
      <RouterLink to="/app/transactions">Операции</RouterLink>
      <RouterLink to="/app/recurring">Периодика</RouterLink>
      <RouterLink to="/app/plans">Планы</RouterLink>
      <RouterLink to="/app/analytics">Аналитика</RouterLink>
      <RouterLink to="/app/settings">Настройки</RouterLink>
    </section>
  </main>
</template>
