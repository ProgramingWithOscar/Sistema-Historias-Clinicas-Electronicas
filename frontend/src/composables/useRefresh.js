import { onScopeDispose, ref } from 'vue'

/**
 * Permite que el botón "Actualizar" del toolbar recargue la sección visible sin
 * que el toolbar sepa qué sección es. Cada vista registra su propia función de
 * carga y la retira sola al desmontarse.
 */
const handlers = new Set()
const refreshing = ref(false)

export function useRefresh() {
  return {
    refreshing,

    /** La llama cada vista con su función de carga. */
    register(handler) {
      handlers.add(handler)
      onScopeDispose(() => handlers.delete(handler))
    },

    async refresh() {
      if (refreshing.value) return
      refreshing.value = true
      try {
        await Promise.all([...handlers].map((handler) => handler()))
      } finally {
        refreshing.value = false
      }
    },
  }
}
