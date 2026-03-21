export class ApiError extends Error {
  constructor(message, status, payload = null) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.payload = payload
  }
}

export async function apiFetch(path, options = {}) {
  const response = await fetch(`/api${path}`, {
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      ...(options.headers || {}),
    },
    ...options,
  })

  const payload = await response.json().catch(() => ({}))
  if (!response.ok) {
    throw new ApiError(payload.error || `HTTP ${response.status}`, response.status, payload)
  }

  return payload
}

export async function apiStream(path, options = {}) {
  const response = await fetch(`/api${path}`, {
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      ...(options.headers || {}),
    },
    ...options,
  })

  if (!response.ok || !response.body) {
    let payload = null
    try {
      payload = await response.clone().json()
    } catch (error) {
      payload = null
    }

    throw new ApiError(payload?.error || `HTTP ${response.status}`, response.status, payload)
  }

  return response
}
