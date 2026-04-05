<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useAuth } from '../../app/providers/auth-provider'
import { useFinance } from '../../features/finance/use-finance'

const auth = useAuth()
const finance = useFinance()

const filters = reactive({ date_from: '', date_to: '' })
const chartMode = ref('bars')
const hoveredSeries = ref('')
const hoverPoint = ref(null)
const visibleSeries = reactive({
  income: true,
  expense: true,
  balance: true,
})

const seriesConfig = {
  income: { key: 'income', label: 'Доход', className: 'income' },
  expense: { key: 'expense', label: 'Расход', className: 'expense' },
  balance: { key: 'balance', label: 'Общий остаток', className: 'balance' },
}

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
    { label: 'Счета', value: finance.state.accounts.length }
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

const chartRows = computed(() => {
  let balance = 0
  return timeseriesByDate.value.map((item) => {
    balance += Number(item.income || 0) - Number(item.expense || 0)
    return {
      ...item,
      balance,
    }
  })
})

const maxTimeseriesAmount = computed(() =>
  Math.max(
    1,
    ...timeseriesByDate.value.flatMap((item) => [item.income, item.expense])
  )
)

const lineChart = computed(() => {
  const rows = chartRows.value
  if (rows.length === 0) {
    return null
  }

  const width = 860
  const height = 280
  const pad = 24
  const activeSeriesKeys = Object.keys(seriesConfig).filter((key) => visibleSeries[key])
  const keysForScale = activeSeriesKeys.length > 0 ? activeSeriesKeys : Object.keys(seriesConfig)
  const values = rows.flatMap((item) => keysForScale.map((key) => Number(item[key] || 0)))
  const min = Math.min(0, ...values)
  const max = Math.max(1, ...values)
  const range = max - min || 1
  const xStep = rows.length === 1 ? 0 : (width - pad * 2) / (rows.length - 1)

  const toY = (value) => {
    const ratio = (Number(value) - min) / range
    return height - pad - ratio * (height - pad * 2)
  }

  const baselineY = toY(0)
  const buildSeries = (key) => {
    const points = rows.map((item, index) => ({
      x: pad + index * xStep,
      y: toY(item[key]),
      value: Number(item[key] || 0),
      date: item.date,
      index,
    }))

    const polyline = points.map((p) => `${p.x.toFixed(2)},${p.y.toFixed(2)}`).join(' ')
    const areaPath =
      points.length === 0
        ? ''
        : `M ${points[0].x.toFixed(2)} ${baselineY.toFixed(2)} L ${polyline.replace(/,/g, ' ')} L ${points[points.length - 1].x.toFixed(2)} ${baselineY.toFixed(2)} Z`

    return { points, polyline, areaPath }
  }

  return {
    width,
    height,
    pad,
    xStep,
    yMin: min,
    yMid: min + range / 2,
    yMax: max,
    yMinPos: toY(min),
    yMidPos: toY(min + range / 2),
    yMaxPos: toY(max),
    series: {
      income: buildSeries('income'),
      expense: buildSeries('expense'),
      balance: buildSeries('balance'),
    },
    xLabels: [rows[0], rows[Math.floor((rows.length - 1) / 2)], rows[rows.length - 1]],
  }
})

const filteredTransactions = computed(() => finance.state.transactions)
const incomeCategoryById = computed(() => new Map(finance.state.incomeCategories.map((item) => [String(item.id), item.name])))
const expenseCategoryById = computed(() => new Map(finance.state.expenseCategories.map((item) => [String(item.id), item.name])))

const topExpenseCategories = computed(() => {
  const categoriesById = new Map(finance.state.expenseCategories.map((item) => [String(item.id), item.name]))

  return finance.state.analyticsCategories.map((row) => ({
    ...row,
    category_name: categoriesById.get(String(row.category_id)) || `Категория #${row.category_id}`,
  }))
})

function amountStyle(value) {
  const width = Math.max(4, Math.round((Number(value || 0) / maxTimeseriesAmount.value) * 100))
  return { width: `${width}%` }
}

function formatNumber(value) {
  return Number(value || 0).toFixed(2)
}

function signedAmount(item) {
  const value = Number(item?.amount || 0)
  const sign = item?.type === 'income' ? '+' : '-'
  return `${sign}${value.toFixed(2)}`
}

function amountClass(item) {
  return item?.type === 'income' ? 'txn-amount income' : 'txn-amount expense'
}

function categoryName(item) {
  const id = String(item.category_id || '')
  if (!id) {
    return '-'
  }
  if (item.type === 'income') {
    return incomeCategoryById.value.get(id) || `Категория #${id}`
  }
  return expenseCategoryById.value.get(id) || `Категория #${id}`
}

function isSeriesVisible(key) {
  if (!visibleSeries[key]) {
    return false
  }
  return !hoveredSeries.value || hoveredSeries.value === key
}

function clearLineHover() {
  hoveredSeries.value = ''
  hoverPoint.value = null
}

function toggleSeries(key) {
  visibleSeries[key] = !visibleSeries[key]
  if (!visibleSeries[key] && hoveredSeries.value === key) {
    clearLineHover()
  }
}

function updateLineHover(event, key) {
  const chart = lineChart.value
  if (!chart || !visibleSeries[key]) {
    return
  }

  hoveredSeries.value = key

  const svg = event.currentTarget.ownerSVGElement || event.currentTarget
  const rect = svg.getBoundingClientRect()
  const relativeX = ((event.clientX - rect.left) / rect.width) * chart.width
  const indexRaw = chart.xStep > 0 ? Math.round((relativeX - chart.pad) / chart.xStep) : 0
  const index = Math.max(0, Math.min(chartRows.value.length - 1, indexRaw))

  const point = chart.series[key].points[index]
  if (!point) {
    return
  }

  hoverPoint.value = {
    key,
    label: seriesConfig[key].label,
    value: point.value,
    date: point.date,
    x: point.x,
    y: point.y,
    className: seriesConfig[key].className,
  }
}

async function applyFilters() {
  const period = { date_from: filters.date_from, date_to: filters.date_to }
  await Promise.all([
    finance.loadSummary(period),
    finance.loadAnalyticsTimeseries(period),
    finance.loadAnalyticsCategories(period),
    finance.loadTransactions(period),
  ])
  clearLineHover()
}

async function resetFilters() {
  filters.date_from = ''
  filters.date_to = ''

  await Promise.all([
    finance.loadSummary(),
    finance.loadAnalyticsTimeseries(),
    finance.loadAnalyticsCategories(),
    finance.loadTransactions(),
  ])
  clearLineHover()
}
</script>

<template>
  <main class="page">
    <section class="card">
      <h1>Дашборд</h1>
    </section>

    <section class="card">
      <form class="row" @submit.prevent="applyFilters">
        <input v-model="filters.date_from" type="date" aria-label="Дата от" />
        <input v-model="filters.date_to" type="date" aria-label="Дата до" />
        <button type="submit">Применить фильтр</button>
        <button type="button" @click="resetFilters">Сбросить</button>
      </form>
    </section>

    <section class="row">
      <article v-for="card in cards" :key="card.label" class="card">
        <strong>{{ card.label }}</strong>
        <div>{{ card.value }}</div>
      </article>
    </section>

    <section class="card page">
      <h2>Динамика</h2>

      <div class="chart-toolbar">
        <button type="button" :disabled="chartMode === 'bars'" @click="chartMode = 'bars'">Сравнение по дням</button>
        <button type="button" :disabled="chartMode === 'line'" @click="chartMode = 'line'">Временной график</button>
      </div>

      <p v-if="timeseriesByDate.length === 0">Нет данных по динамике.</p>

      <div v-else-if="chartMode === 'bars'" class="chart-list">
        <div v-for="point in timeseriesByDate" :key="point.date" class="chart-row">
          <div class="chart-date">{{ point.date }}</div>
          <div class="chart-bars">
            <div class="chart-bar-row">
              <span class="chart-bar-value income">+{{ formatNumber(point.income) }}</span>
              <div class="chart-bar-track">
                <div class="chart-bar income" :style="amountStyle(point.income)" />
              </div>
            </div>
            <div class="chart-bar-row">
              <span class="chart-bar-value expense">-{{ formatNumber(point.expense) }}</span>
              <div class="chart-bar-track">
                <div class="chart-bar expense" :style="amountStyle(point.expense)" />
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-else-if="lineChart" class="line-chart-wrap">
        <svg
          :viewBox="`0 0 ${lineChart.width} ${lineChart.height}`"
          class="line-chart"
          role="img"
          aria-label="График доходов, расходов и остатка"
          @mouseleave="clearLineHover"
        >
          <line :x1="lineChart.pad" :y1="lineChart.pad" :x2="lineChart.pad" :y2="lineChart.height - lineChart.pad" class="chart-axis" />
          <line :x1="lineChart.pad" :y1="lineChart.height - lineChart.pad" :x2="lineChart.width - lineChart.pad" :y2="lineChart.height - lineChart.pad" class="chart-axis" />

          <line x1="0" :y1="lineChart.yMaxPos" :x2="lineChart.width" :y2="lineChart.yMaxPos" class="chart-grid" />
          <line x1="0" :y1="lineChart.yMidPos" :x2="lineChart.width" :y2="lineChart.yMidPos" class="chart-grid" />
          <line x1="0" :y1="lineChart.yMinPos" :x2="lineChart.width" :y2="lineChart.yMinPos" class="chart-grid" />

          <text :x="6" :y="lineChart.yMaxPos + 4" class="axis-label">{{ formatNumber(lineChart.yMax) }}</text>
          <text :x="6" :y="lineChart.yMidPos + 4" class="axis-label">{{ formatNumber(lineChart.yMid) }}</text>
          <text :x="6" :y="lineChart.yMinPos + 4" class="axis-label">{{ formatNumber(lineChart.yMin) }}</text>

          <text :x="lineChart.pad" :y="lineChart.height - 6" class="axis-label">{{ lineChart.xLabels[0]?.date }}</text>
          <text :x="lineChart.width / 2" :y="lineChart.height - 6" text-anchor="middle" class="axis-label">{{ lineChart.xLabels[1]?.date }}</text>
          <text :x="lineChart.width - lineChart.pad" :y="lineChart.height - 6" text-anchor="end" class="axis-label">{{ lineChart.xLabels[2]?.date }}</text>

          <g
            v-for="(series, key) in lineChart.series"
            :key="key"
            :class="['series-group', { 'series-hidden': !isSeriesVisible(key) }]"
          >
            <path :d="series.areaPath" :class="['area', seriesConfig[key].className]" />
            <polyline :points="series.polyline" :class="['line', seriesConfig[key].className]" />
            <polyline
              :points="series.polyline"
              class="line-hit"
              @mouseenter="updateLineHover($event, key)"
              @mousemove="updateLineHover($event, key)"
            />
          </g>

          <circle
            v-if="hoverPoint"
            :cx="hoverPoint.x"
            :cy="hoverPoint.y"
            :class="['line-marker', hoverPoint.className]"
            r="4"
          />
        </svg>

        <div v-if="hoverPoint" class="line-hover-card">
          <strong>{{ hoverPoint.label }}</strong>
          <span>{{ hoverPoint.date }}</span>
          <span>{{ formatNumber(hoverPoint.value) }}</span>
        </div>

        <div class="line-chart-legend">
          <button
            type="button"
            class="legend-income"
            :class="{ 'legend-off': !visibleSeries.income }"
            @click="toggleSeries('income')"
          >
            Доход
          </button>
          <button
            type="button"
            class="legend-expense"
            :class="{ 'legend-off': !visibleSeries.expense }"
            @click="toggleSeries('expense')"
          >
            Расход
          </button>
          <button
            type="button"
            class="legend-balance"
            :class="{ 'legend-off': !visibleSeries.balance }"
            @click="toggleSeries('balance')"
          >
            Общий остаток
          </button>
        </div>
      </div>
    </section>

    <section class="card page">
      <h2>Операции</h2>
      <p v-if="filteredTransactions.length === 0">Операций по выбранному фильтру нет.</p>
      <table v-else class="dashboard-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Тип</th>
            <th>Категория</th>
            <th>Сумма</th>
            <th>Описание</th>
            <th>Дата</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in filteredTransactions" :key="item.id">
            <td>{{ item.id }}</td>
            <td>{{ item.type }}</td>
            <td>{{ categoryName(item) }}</td>
            <td>
              <span :class="amountClass(item)">{{ signedAmount(item) }}</span>
            </td>
            <td>{{ item.description }}</td>
            <td>{{ item.occurred_at }}</td>
          </tr>
        </tbody>
      </table>
    </section>

    <section class="card page">
      <h2>Топ категорий расходов</h2>
      <p v-if="topExpenseCategories.length === 0">Нет данных по категориям расходов.</p>
      <table v-else class="dashboard-table">
        <thead>
          <tr>
            <th>Категория</th>
            <th>Сумма расходов</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in topExpenseCategories" :key="item.category_id">
            <td>{{ item.category_name }}</td>
            <td>{{ item.total }}</td>
          </tr>
        </tbody>
      </table>
    </section>
  </main>
</template>
