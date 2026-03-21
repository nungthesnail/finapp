import { readonly, reactive } from 'vue'
import { apiFetch } from '../../shared/api/client'

const state = reactive({
  user: null,
  initialized: false,
  loading: false,
  error: '',
})

async function refreshUser() {
  state.loading = true
  state.error = ''

  try {
    const data = await apiFetch('/me')
    state.user = data.user
    return data.user
  } catch (error) {
    state.user = null
    return null
  } finally {
    state.loading = false
    state.initialized = true
  }
}

async function ensureInitialized() {
  if (state.initialized) {
    return state.user
  }

  return refreshUser()
}

async function login(payload) {
  state.loading = true
  state.error = ''

  try {
    await apiFetch('/auth/login', {
      method: 'POST',
      body: JSON.stringify(payload),
    })

    return await refreshUser()
  } catch (error) {
    state.error = error.message || 'Login failed'
    throw error
  } finally {
    state.loading = false
  }
}

async function register(payload) {
  state.loading = true
  state.error = ''

  try {
    await apiFetch('/auth/register', {
      method: 'POST',
      body: JSON.stringify(payload),
    })

    return await refreshUser()
  } catch (error) {
    state.error = error.message || 'Registration failed'
    throw error
  } finally {
    state.loading = false
  }
}

async function logout() {
  state.loading = true
  state.error = ''

  try {
    await apiFetch('/auth/logout', { method: 'POST' })
    state.user = null
  } finally {
    state.loading = false
    state.initialized = true
  }
}

async function updateProfile(payload) {
  state.loading = true
  state.error = ''

  try {
    const data = await apiFetch('/me', {
      method: 'PUT',
      body: JSON.stringify(payload),
    })

    state.user = data.user
    return data.user
  } catch (error) {
    state.error = error.message || 'Profile update failed'
    throw error
  } finally {
    state.loading = false
    state.initialized = true
  }
}

export function useAuth() {
  return {
    state: readonly(state),
    refreshUser,
    ensureInitialized,
    login,
    register,
    logout,
    updateProfile,
  }
}
