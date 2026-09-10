import { computed, ref } from 'vue'
import { api } from '../services/api'

// Estado compartido: el resumen y las secciones de detalle leen los mismos
// datos, así que se cargan una vez y no una por vista.
const sessions = ref([])
const logs = ref([])
const readings = ref([])
const standards = ref([])
const exportacion = ref(null)
const encounters = ref([])
const encounterTypes = ref([])
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
    standards,
    exportacion,
    encounters,
    encounterTypes,
    error,

    criticas: computed(() => readings.value.filter((r) => r.severity === 'critical').length),
    atencion: computed(() => readings.value.filter((r) => r.requires_attention).length),

    cargarSesiones: () => cargar('/sessions', sessions),
    cargarAuditoria: () => cargar('/audit-logs', logs),
    cargarLecturas: () => cargar('/device-readings', readings),
    cargarEstandares: () => cargar('/exchange-standards', standards),
    cargarAtenciones: () => cargar('/clinical-encounters', encounters),
    cargarTiposAtencion: () => cargar('/encounter-types', encounterTypes),

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

    /**
     * Pide el documento de intercambio. El único dato que viaja es el estándar:
     * la familia de serializadores la elige el backend.
     */
    async exportarHistoria(standard) {
      const { data } = await api('/clinical-exports', {
        method: 'POST',
        body: { standard },
      })
      exportacion.value = data
      return data
    },

    /**
     * Envía la nota de atención. El backend la construye paso a paso: si queda
     * incompleta responde 422 con la lista de secciones que faltan.
     */
    async registrarAtencion(payload) {
      const { data } = await api('/clinical-encounters', { method: 'POST', body: payload })
      encounters.value = [data, ...encounters.value]
      return data
    },
  }
}
