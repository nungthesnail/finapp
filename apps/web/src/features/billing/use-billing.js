import { reactive, readonly } from 'vue'
import { apiFetch } from '../../shared/api/client'

const state = reactive({
  loading: false,
  loadedUserId: null,
  status: '',
  error: '',
  tariffs: [],
  activeSubscription: null,
  creditBalanceRub: 0,
  ledger: [],
  payments: [],
  checkoutLoadingTariffId: null,
})

function resetForUser(user) {
  if (!user) {
    state.loading = false
    state.loadedUserId = null
    state.status = 'Не авторизован'
    state.error = ''
    state.tariffs = []
    state.activeSubscription = null
    state.creditBalanceRub = 0
    state.ledger = []
    state.payments = []
    state.checkoutLoadingTariffId = null
    return
  }

  state.status = `Авторизован: ${user.phone}`
}

async function loadTariffs() {
  const data = await apiFetch('/tariffs')
  state.tariffs = data.items || []
}

async function loadOverview() {
  const data = await apiFetch('/billing/overview')
  state.activeSubscription = data.active_subscription || null
  state.creditBalanceRub = Number(data.credit_balance_rub || 0)
  state.ledger = data.ledger || []
  state.payments = data.payments || []
}

async function ensureBillingLoaded(user) {
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
  state.error = ''
  resetForUser(user)

  try {
    await Promise.all([loadTariffs(), loadOverview()])
    state.loadedUserId = user.id
  } catch (error) {
    state.error = error.message || 'Не удалось загрузить данные биллинга'
    throw error
  } finally {
    state.loading = false
  }
}

async function refresh() {
  await Promise.all([loadTariffs(), loadOverview()])
}

async function checkout(tariffId, returnUrl) {
  state.checkoutLoadingTariffId = Number(tariffId)
  state.error = ''

  try {
    const data = await apiFetch('/subscriptions/checkout', {
      method: 'POST',
      body: JSON.stringify({
        tariff_id: Number(tariffId),
        return_url: returnUrl,
      }),
    })

    const confirmationUrl = data?.payment?.confirmation_url
    if (confirmationUrl) {
      window.location.href = confirmationUrl
      return
    }

    await refresh()
  } catch (error) {
    state.error = error.message || 'Ошибка при оплате'
    throw error
  } finally {
    state.checkoutLoadingTariffId = null
  }
}

export function useBilling() {
  return {
    state: readonly(state),
    resetForUser,
    ensureBillingLoaded,
    loadTariffs,
    loadOverview,
    refresh,
    checkout,
  }
}
