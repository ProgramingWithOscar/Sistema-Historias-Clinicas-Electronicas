import { computed, readonly, ref } from 'vue'
import { api } from '../services/api'

// Estado compartido por toda la aplicación (fuera de la función, a propósito).
const user = ref(null)
const loading = ref(false)
const ready = ref(false)

export function useAuth() {
  async function login(credentials) {
    loading.value = true
    try {
      const data = await api('/login', { method: 'POST', body: credentials })
      user.value = data.user
      return data
    } finally {
      loading.value = false
    }
  }

  async function logout() {
    loading.value = true
    try {
      await api('/logout', { method: 'POST' })
    } catch {
      // Si la sesión ya expiró en el servidor, la cerramos igual en el cliente.
    } finally {
      user.value = null
      loading.value = false
    }
  }

  /**
   * Rehidrata la sesión al recargar la página. No hay nada que leer en el
   * cliente: la cookie es HttpOnly, así que le preguntamos al servidor.
   */
  async function restore() {
    try {
      user.value = await api('/me')
    } catch {
      user.value = null
    } finally {
      ready.value = true
    }
  }

  return {
    user: readonly(user),
    loading: readonly(loading),
    ready: readonly(ready),
    isAuthenticated: computed(() => user.value !== null),
    login,
    logout,
    restore,
  }
}
