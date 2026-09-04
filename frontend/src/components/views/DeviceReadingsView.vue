<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import AppIcon from '../ui/AppIcon.vue'
import { useClinicalData } from '../../composables/useClinicalData'
import { useRefresh } from '../../composables/useRefresh'
import { dispositivos, fecha } from '../../utils/format'

const { readings, error, cargarLecturas, registrarLectura } = useClinicalData()
const { register } = useRefresh()

/**
 * Campos de cada dispositivo. Reflejan el `payloadRules()` de la fábrica
 * correspondiente en el backend: cada equipo manda lo suyo, y el formulario
 * cambia con el mismo criterio con el que el resolver elige la fábrica.
 */
const formularios = {
  glucometer: [
    { name: 'mg_dl', label: 'Glucemia (mg/dL)', type: 'number', min: 10, max: 900, step: 1 },
    { name: 'fasting', label: 'Muestra en ayunas', type: 'checkbox' },
  ],
  sphygmomanometer: [
    { name: 'systolic', label: 'Sistólica (mm[Hg])', type: 'number', min: 50, max: 300, step: 1 },
    { name: 'diastolic', label: 'Diastólica (mm[Hg])', type: 'number', min: 20, max: 200, step: 1 },
    { name: 'pulse', label: 'Pulso (lpm)', type: 'number', min: 20, max: 250, step: 1 },
  ],
  pulse_oximeter: [
    { name: 'spo2', label: 'SpO₂ (%)', type: 'number', min: 50, max: 100, step: 1 },
    { name: 'pulse', label: 'Pulso (lpm)', type: 'number', min: 20, max: 250, step: 1 },
  ],
}

const deviceType = ref('glucometer')
const valores = reactive({})
const enviando = ref(false)
const fieldErrors = ref({})
const aviso = ref(null)
const filtro = ref('')

const campos = computed(() => formularios[deviceType.value])

const visibles = computed(() =>
  filtro.value ? readings.value.filter((r) => r.device_type === filtro.value) : readings.value,
)

function cambiarDispositivo() {
  Object.keys(valores).forEach((clave) => delete valores[clave])
  fieldErrors.value = {}
  aviso.value = null
}

async function enviar() {
  enviando.value = true
  fieldErrors.value = {}
  aviso.value = null

  // Los campos vacíos no se mandan: son opcionales en las reglas del backend.
  const payload = Object.fromEntries(
    Object.entries(valores).filter(([, valor]) => valor !== '' && valor !== undefined),
  )

  try {
    const lectura = await registrarLectura(deviceType.value, payload)
    aviso.value = {
      tono: lectura.requires_attention ? lectura.severity : 'success',
      texto: `${lectura.display}: ${lectura.value} ${lectura.unit} — ${lectura.severity_label}`,
    }
    cambiarDispositivo()
  } catch (e) {
    fieldErrors.value = e.errors ?? {}
    aviso.value = { tono: 'failure', texto: e.message }
  } finally {
    enviando.value = false
  }
}

register(cargarLecturas)
onMounted(cargarLecturas)
</script>

<template>
  <div class="vista">
    <p v-if="error" class="error" role="alert">{{ error }}</p>

    <section class="card">
      <header class="card-head">
        <h2>Registrar lectura</h2>
        <p class="nota">
          El backend elige la fábrica según el dispositivo y devuelve la lectura ya
          normalizada con su código LOINC.
        </p>
      </header>

      <form class="formulario" @submit.prevent="enviar">
        <div class="campo">
          <label for="device">Dispositivo</label>
          <select id="device" v-model="deviceType" @change="cambiarDispositivo">
            <option v-for="(nombre, id) in dispositivos" :key="id" :value="id">{{ nombre }}</option>
          </select>
        </div>

        <div v-for="campo in campos" :key="campo.name" class="campo" :class="campo.type">
          <label :for="campo.name">{{ campo.label }}</label>
          <input
            :id="campo.name"
            v-model="valores[campo.name]"
            :type="campo.type"
            :min="campo.min"
            :max="campo.max"
            :step="campo.step"
            :required="campo.type === 'number' && campo.name !== 'pulse'"
            :aria-invalid="Boolean(fieldErrors[campo.name])"
          />
          <p v-if="fieldErrors[campo.name]" class="field-error">{{ fieldErrors[campo.name][0] }}</p>
        </div>

        <button type="submit" class="btn" :disabled="enviando">
          <AppIcon name="plus" :size="17" />
          {{ enviando ? 'Enviando...' : 'Registrar' }}
        </button>
      </form>

      <p v-if="aviso" class="aviso" :class="aviso.tono" role="status">{{ aviso.texto }}</p>
    </section>

    <section class="card">
      <header class="card-head">
        <h2>Lecturas registradas <span class="count">{{ visibles.length }}</span></h2>
        <div class="filtros">
          <button
            type="button"
            class="chip"
            :class="{ activo: filtro === '' }"
            @click="filtro = ''"
          >
            Todos
          </button>
          <button
            v-for="(nombre, id) in dispositivos"
            :key="id"
            type="button"
            class="chip"
            :class="{ activo: filtro === id }"
            @click="filtro = id"
          >
            {{ nombre }}
          </button>
        </div>
      </header>

      <div class="scroll">
        <table>
          <thead>
            <tr>
              <th>Dispositivo</th>
              <th>Observación</th>
              <th>LOINC</th>
              <th>Valor</th>
              <th>Criticidad</th>
              <th>Medición</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in visibles" :key="r.id">
              <td>{{ dispositivos[r.device_type] ?? r.device_type }}</td>
              <td>{{ r.display }}</td>
              <td><code>{{ r.loinc_code }}</code></td>
              <td class="num">{{ r.value }} <small>{{ r.unit }}</small></td>
              <td><span class="estado" :class="r.severity">{{ r.severity_label }}</span></td>
              <td>{{ fecha(r.measured_at) }}</td>
            </tr>
            <tr v-if="!visibles.length">
              <td colspan="6" class="vacio">Sin lecturas para este filtro.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<style scoped>
.nota {
  font-size: 13.5px;
  max-width: 62ch;
}

.formulario {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 14px;
  margin-top: 18px;
}

.campo {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 170px;
}

.campo label {
  font-size: 13px;
}

.campo.checkbox {
  flex-direction: row-reverse;
  align-items: center;
  justify-content: flex-end;
  min-width: 0;
  padding-bottom: 9px;
}

.campo.checkbox input {
  width: 17px;
  height: 17px;
  accent-color: var(--accent);
}

.filtros {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.chip {
  font: inherit;
  font-size: 13px;
  padding: 5px 11px;
  border: 1px solid var(--border);
  border-radius: 20px;
  color: var(--text);
  background: none;
  cursor: pointer;
  transition: color 0.2s, background 0.2s, border-color 0.2s;
}

.chip:hover {
  color: var(--text-h);
}

.chip.activo {
  color: var(--accent);
  border-color: transparent;
  background: var(--accent-bg);
}

.aviso {
  margin-top: 16px;
  font-size: 14.5px;
  padding: 11px 14px;
  border-radius: 8px;
  color: var(--ok);
  background: var(--ok-bg);
}

.aviso.warning {
  color: var(--warning);
  background: var(--warning-bg);
}

.aviso.critical,
.aviso.failure {
  color: var(--danger);
  background: var(--danger-bg);
}

.num small {
  font-size: 12px;
  opacity: 0.7;
}
</style>
