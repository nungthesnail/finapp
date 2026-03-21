<script setup>
import { computed, watch } from 'vue'
import { useAuth } from '../../app/providers/auth-provider'
import { useBilling } from '../../features/billing/use-billing'

const auth = useAuth()
const billing = useBilling()

watch(
  () => auth.state.user,
  async (user) => {
    try {
      await billing.ensureBillingLoaded(user)
    } catch (error) {
      // state.error already set in feature
    }
  },
  { immediate: true }
)

const activeTariffName = computed(() => {
  return billing.state.activeSubscription?.tariff?.name || 'Нет активной подписки'
})

function formatDate(value) {
  if (!value) return '-'
  return new Date(value).toLocaleString()
}

async function onCheckout(tariffId) {
  const returnUrl = `${window.location.origin}/app/subscription`
  try {
    await billing.checkout(tariffId, returnUrl)
  } catch (error) {
    // state.error already set in feature
  }
}
</script>

<template>
  <section class="card page">
    <h1>Подписка и кредиты</h1>
    <p>{{ billing.state.status }}</p>
    <p v-if="billing.state.error" class="error-text">{{ billing.state.error }}</p>

    <section class="row">
      <article class="card">
        <strong>Активный тариф</strong>
        <div>{{ activeTariffName }}</div>
      </article>
      <article class="card">
        <strong>Действует до</strong>
        <div>{{ formatDate(billing.state.activeSubscription?.end_at) }}</div>
      </article>
      <article class="card">
        <strong>Баланс кредитов</strong>
        <div>{{ billing.state.creditBalanceRub.toFixed(2) }} RUB</div>
      </article>
    </section>

    <section class="card page">
      <h2>Доступные тарифы</h2>
      <div class="row">
        <article v-for="tariff in billing.state.tariffs" :key="tariff.id" class="card page">
          <strong>{{ tariff.name }}</strong>
          <div>{{ tariff.description || 'Без описания' }}</div>
          <div>{{ tariff.duration_days }} дней</div>
          <div>{{ tariff.price_rub }} RUB</div>
          <button
            type="button"
            :disabled="billing.state.checkoutLoadingTariffId === tariff.id"
            @click="onCheckout(tariff.id)"
          >
            {{ billing.state.checkoutLoadingTariffId === tariff.id ? 'Переход...' : 'Оплатить через YooMoney' }}
          </button>
        </article>
      </div>
    </section>

    <section class="card page">
      <h2>Журнал кредитов</h2>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Тип</th>
            <th>Сумма</th>
            <th>Описание</th>
            <th>Создано</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="entry in billing.state.ledger" :key="entry.id">
            <td>{{ entry.id }}</td>
            <td>{{ entry.entry_type }}</td>
            <td>{{ entry.amount }}</td>
            <td>{{ entry.description }}</td>
            <td>{{ formatDate(entry.created_at) }}</td>
          </tr>
        </tbody>
      </table>
    </section>

    <section class="card page">
      <h2>Платежи</h2>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Статус</th>
            <th>Сумма</th>
            <th>Тариф</th>
            <th>Оплачено</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="payment in billing.state.payments" :key="payment.id">
            <td>{{ payment.id }}</td>
            <td>{{ payment.status }}</td>
            <td>{{ payment.amount_rub }}</td>
            <td>{{ payment.tariff?.name || payment.tariff_id }}</td>
            <td>{{ formatDate(payment.paid_at) }}</td>
          </tr>
        </tbody>
      </table>
    </section>
  </section>
</template>

