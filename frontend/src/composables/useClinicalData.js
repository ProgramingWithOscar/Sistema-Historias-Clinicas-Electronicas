import { computed, ref } from 'vue'
import { api } from '../services/api'

// Estado compartido: el resumen y las secciones de detalle leen los mismos
// datos, así que se cargan una vez y no una por vista.
const sessions = ref([])
const logs = ref([])
const readings = ref([])
const error = ref(null)

async function cargar(recurso, destino) {
  try {
    const { data } = await api(recurso)
    destino.value = data
    error.value = null
  } catch (e) {
    error.value = e.message
  }
}

export function useClinicalData() {
  return {
    sessions,
    logs,
    readings,
    error,

    criticas: computed(() => readings.value.filter((r) => r.severity === 'critical').length),
    atencion: computed(() => readings.value.filter((r) => r.requires_attention).length),

    cargarSesiones: () => cargar('/sessions', sessions),
    cargarAuditoria: () => cargar('/audit-logs', logs),
    cargarLecturas: () => cargar('/device-readings', readings),

    async cargarTodo() {
      await Promise.all([
        cargar('/sessions', sessions),
        cargar('/audit-logs', logs),
        cargar('/device-readings', readings),
      ])
    },

    async registrarLectura(deviceType, payload) {
      const { data } = await api('/device-readings', {
        method: 'POST',
        body: { device_type: deviceType, payload },
      })
      readings.value = [data, ...readings.value]
      return data
    },
  }
}
