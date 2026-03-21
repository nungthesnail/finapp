<script setup>
import { reactive, watch } from 'vue'
import { useAuth } from '../../app/providers/auth-provider'
import { useAdmin } from '../../features/admin/use-admin'

const auth = useAuth()
const admin = useAdmin()

const form = reactive({
  code: '',
  name: '',
  provider: 'openai',
  input_cost_per_1k: '',
  output_cost_per_1k: '',
  cached_input_cost_per_1k: '',
  supports_tools: true,
  is_active: true,
})

watch(
  () => auth.state.user,
  async (user) => {
    try {
      await admin.ensureAdminLoaded(user)
    } catch (error) {
      // state.error is reactive
    }
  },
  { immediate: true }
)

async function submitCreate() {
  await admin.createAiModel({
    code: form.code,
    name: form.name,
    provider: form.provider,
    input_cost_per_1k: Number(form.input_cost_per_1k),
    output_cost_per_1k: Number(form.output_cost_per_1k),
    cached_input_cost_per_1k: Number(form.cached_input_cost_per_1k || 0),
    supports_tools: !!form.supports_tools,
    is_active: !!form.is_active,
  })

  form.code = ''
  form.name = ''
  form.input_cost_per_1k = ''
  form.output_cost_per_1k = ''
  form.cached_input_cost_per_1k = ''
}

async function toggleActive(item) {
  await admin.updateAiModel(item.id, { is_active: !item.is_active })
}
</script>

<template>
  <section class="card page">
    <h1>AI-модели (админ)</h1>
    <p v-if="admin.state.error" class="error-text">{{ admin.state.error }}</p>

    <form class="row" @submit.prevent="submitCreate">
      <input v-model="form.code" placeholder="код-модели" required />
      <input v-model="form.name" placeholder="Название модели" required />
      <input v-model="form.provider" placeholder="Провайдер" required />
      <input v-model="form.input_cost_per_1k" type="number" step="0.000001" placeholder="Вход/1k" required />
      <input v-model="form.output_cost_per_1k" type="number" step="0.000001" placeholder="Выход/1k" required />
      <input v-model="form.cached_input_cost_per_1k" type="number" step="0.000001" placeholder="Кэш/1k" />
      <button type="submit">Создать модель</button>
    </form>

    <table>
      <thead>
        <tr>
          <th>Код</th>
          <th>Название</th>
          <th>Провайдер</th>
          <th>Вход</th>
          <th>Выход</th>
          <th>Кэш</th>
          <th>Инструменты</th>
          <th>Активна</th>
          <th>Действие</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in admin.state.aiModels" :key="item.id">
          <td>{{ item.code }}</td>
          <td>{{ item.name }}</td>
          <td>{{ item.provider }}</td>
          <td>{{ item.input_cost_per_1k }}</td>
          <td>{{ item.output_cost_per_1k }}</td>
          <td>{{ item.cached_input_cost_per_1k }}</td>
          <td>{{ item.supports_tools ? 'да' : 'нет' }}</td>
          <td>{{ item.is_active ? 'да' : 'нет' }}</td>
          <td><button type="button" @click="toggleActive(item)">Переключить активность</button></td>
        </tr>
      </tbody>
    </table>
  </section>
</template>
