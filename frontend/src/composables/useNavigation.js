import { computed, ref } from 'vue'

/**
 * Navegación de la aplicación.
 *
 * No usamos vue-router a propósito: el panel es una sola pantalla con secciones
 * y añadir el router traería dependencia, historial y guardas que hoy no
 * aportan nada. Si más adelante hacen falta URLs profundas, este composable es
 * el único punto que habría que sustituir.
 */
const sections = [
  {
    id: 'overview',
    label: 'Resumen',
    icon: 'grid',
    title: 'Resumen clínico',
    description: 'Estado general de la sesión y de los datos registrados.',
  },
  {
    id: 'readings',
    label: 'Dispositivos IoT',
    icon: 'activity',
    title: 'Monitoreo con dispositivos IoT',
    description: 'Lecturas normalizadas por el patrón Factory Method.',
  },
  {
    id: 'sessions',
    label: 'Sesiones',
    icon: 'shield',
    title: 'Sesiones activas',
    description: 'Dispositivos con sesión abierta contra tu cuenta.',
  },
  {
    id: 'audit',
    label: 'Auditoría',
    icon: 'list',
    title: 'Trazabilidad de auditoría',
    description: 'Eventos registrados por el Singleton AuditLogger.',
  },
]

const current = ref('overview')
const sidebarOpen = ref(false)

export function useNavigation() {
  return {
    sections,
    current,
    sidebarOpen,
    section: computed(() => sections.find((s) => s.id === current.value)),
    go(id) {
      current.value = id
      sidebarOpen.value = false
    },
    toggleSidebar() {
      sidebarOpen.value = !sidebarOpen.value
    },
  }
}
