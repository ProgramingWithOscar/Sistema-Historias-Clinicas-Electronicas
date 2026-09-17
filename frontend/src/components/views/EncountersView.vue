<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import AppIcon from '../ui/AppIcon.vue'
import { useClinicalData } from '../../composables/useClinicalData'
import { useRefresh } from '../../composables/useRefresh'
import { fecha } from '../../utils/format'

const {
  encounters,
  encounterTypes,
  templates,
  error,
  cargarAtenciones,
  cargarTiposAtencion,
  cargarPlantillas,
  cargarBorrador,
  guardarPlantilla,
  registrarAtencion,
} = useClinicalData()
const { register } = useRefresh()

/**
 * Campos propios de cada tipo de atención. Reflejan el `payloadRules()` del
 * director correspondiente: la teleconsulta no tiene examen físico porque su
 * director nunca llama a ese paso del builder.
 */
const especificos = {
  emergency: [
    { name: 'triage', label: 'Triaje (Res. 5596 de 2015)', type: 'select', options: ['I', 'II', 'III', 'IV', 'V'] },
    { name: 'physical_exam', label: 'Examen físico', type: 'textarea' },
    { name: 'service', label: 'Servicio', type: 'text' },
  ],
  outpatient_control: [
    { name: 'history', label: 'Antecedentes', type: 'textarea' },
    { name: 'physical_exam', label: 'Examen físico (opcional)', type: 'textarea' },
    { name: 'follow_up_at', label: 'Próxima cita', type: 'date' },
    { name: 'program', label: 'Programa', type: 'text' },
  ],
  teleconsultation: [
    { name: 'channel', label: 'Canal de la atención', type: 'text' },
    { name: 'consent', label: 'Consentimiento informado del paciente', type: 'checkbox' },
  ],
}

const encounterType = ref('emergency')
const valores = reactive({})
const diagnosticos = ref([{ code: '', description: '' }])
const enviando = ref(false)
const fieldErrors = ref({})
const aviso = ref(null)
const abierta = ref(null)

// Plantillas (patrón Prototype)
const plantilla = ref('')
const guardando = ref(null)

const plantillasDelTipo = computed(() =>
  templates.value.filter((t) => t.encounter_type === encounterType.value),
)

const campos = computed(() => especificos[encounterType.value] ?? [])
const tipo = computed(() => encounterTypes.value.find((t) => t.encounter_type === encounterType.value))

function cambiarTipo() {
  Object.keys(valores).forEach((clave) => delete valores[clave])
  diagnosticos.value = [{ code: '', description: '' }]
  fieldErrors.value = {}
  aviso.value = null
  plantilla.value = ''
}

/**
 * Carga una plantilla en el formulario.
 *
 * El backend devuelve una COPIA del prototipo, así que lo que se edite aquí no
 * afecta al catálogo ni a lo que cargue otro profesional al mismo tiempo.
 */
async function aplicarPlantilla() {
  if (!plantilla.value) return

  fieldErrors.value = {}
  aviso.value = null

  try {
    const borrador = await cargarBorrador(plantilla.value)

    valores.chief_complaint = borrador.chief_complaint ?? ''
    valores.treatment_plan = borrador.treatment_plan ?? ''
    if (borrador.follow_up_at) valores.follow_up_at = borrador.follow_up_at
    if (borrador.program) valores.program = borrador.program

    diagnosticos.value = (borrador.diagnoses ?? []).map((d) => ({
      code: d.code,
      description: d.description,
    }))

    aviso.value = {
      tono: 'success',
      texto: 'Plantilla cargada. Ajusta lo que sea propio de este paciente antes de registrar.',
    }
  } catch (e) {
    aviso.value = { tono: 'failure', texto: e.message }
  }
}

/** Convierte una nota ya registrada en una plantilla reutilizable. */
async function guardarComoPlantilla(encuentro) {
  const nombre = window.prompt('Nombre de la plantilla:', encuentro.chief_complaint)
  if (!nombre) return

  const clave = nombre
    .toLowerCase()
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .slice(0, 60)

  guardando.value = encuentro.id
  aviso.value = null

  try {
    await guardarPlantilla({ encounterId: encuentro.id, key: clave, name: nombre })
    aviso.value = {
      tono: 'success',
      texto: `Plantilla «${nombre}» guardada sin los datos del paciente.`,
    }
  } catch (e) {
    aviso.value = { tono: 'failure', texto: e.errors?.key?.[0] ?? e.message }
  } finally {
    guardando.value = null
  }
}

function agregarDiagnostico() {
  diagnosticos.value.push({ code: '', description: '' })
}

function quitarDiagnostico(indice) {
  diagnosticos.value.splice(indice, 1)
}

async function enviar() {
  enviando.value = true
  fieldErrors.value = {}
  aviso.value = null

  const payload = {
    encounter_type: encounterType.value,
    // Si la nota partió de una plantilla, se declara: el backend rellena con la
    // copia del prototipo los huecos que el formulario no haya cubierto.
    ...(plantilla.value ? { template: plantilla.value } : {}),
    professional_license: valores.professional_license,
    chief_complaint: valores.chief_complaint,
    present_illness: valores.present_illness,
    treatment_plan: valores.treatment_plan,
    // El primero es el principal si nadie dice lo contrario, igual que en el director.
    diagnoses: diagnosticos.value
      .filter((d) => d.code && d.description)
      .map((d, i) => ({ ...d, primary: i === 0 })),
    ...Object.fromEntries(
      campos.value
        .map((campo) => [campo.name, valores[campo.name]])
        .filter(([, valor]) => valor !== '' && valor !== undefined),
    ),
  }

  try {
    const nota = await registrarAtencion(payload)
    aviso.value = {
      tono: 'success',
      texto: `${nota.type_label} registrada: ${nota.diagnoses[0]?.code} — ${nota.diagnoses[0]?.description}`,
    }
    cambiarTipo()
  } catch (e) {
    fieldErrors.value = e.errors ?? {}
    aviso.value = { tono: 'failure', texto: e.message }
  } finally {
    enviando.value = false
  }
}

register(cargarAtenciones)

onMounted(() => {
  cargarTiposAtencion()
  cargarPlantillas()
  cargarAtenciones()
})
</script>

<template>
  <div class="vista">
    <p v-if="error" class="error" role="alert">{{ error }}</p>

    <section class="card">
      <header class="card-head">
        <h2>Registrar nota de atención</h2>
        <p class="nota">
          El backend arma la nota paso a paso y sólo la entrega si cumple el contenido mínimo de la
          Resolución 1995 de 1999 (patrón Builder). Cada tipo de atención tiene su propio director.
        </p>
      </header>

      <div class="tipos">
        <button
          v-for="t in encounterTypes"
          :key="t.encounter_type"
          type="button"
          class="tipo"
          :class="{ activo: encounterType === t.encounter_type }"
          :aria-pressed="encounterType === t.encounter_type"
          @click="encounterType = t.encounter_type; cambiarTipo()"
        >
          <strong>{{ t.label }}</strong>
          <small>{{ t.in_person ? 'Presencial' : 'No presencial' }}</small>
          <span class="secciones">
            <em v-for="s in t.required_sections" :key="s">{{ s }}</em>
          </span>
        </button>
      </div>

      <div v-if="plantillasDelTipo.length" class="plantillas">
        <label for="plantilla">Partir de una plantilla</label>
        <select id="plantilla" v-model="plantilla" @change="aplicarPlantilla">
          <option value="">Sin plantilla — empezar en blanco</option>
          <option v-for="p in plantillasDelTipo" :key="p.key" :value="p.key">
            {{ p.name }}{{ p.built_in ? '' : ' (propia)' }}
          </option>
        </select>
        <p class="nota aparte">
          Cada carga es una copia independiente del prototipo: lo que ajustes aquí no altera la
          plantilla del catálogo ni la de otro profesional.
        </p>
      </div>

      <form class="formulario" @submit.prevent="enviar">
        <div class="campo ancho">
          <label for="license">Registro profesional</label>
          <input id="license" v-model="valores.professional_license" type="text" placeholder="RM-12345" required />
          <p v-if="fieldErrors.professional_license" class="field-error">
            {{ fieldErrors.professional_license[0] }}
          </p>
        </div>

        <div class="campo ancho">
          <label for="complaint">Motivo de consulta</label>
          <input id="complaint" v-model="valores.chief_complaint" type="text" required />
          <p v-if="fieldErrors.chief_complaint" class="field-error">{{ fieldErrors.chief_complaint[0] }}</p>
        </div>

        <div class="campo total">
          <label for="illness">Enfermedad actual</label>
          <textarea id="illness" v-model="valores.present_illness" rows="3" required />
          <p v-if="fieldErrors.present_illness" class="field-error">{{ fieldErrors.present_illness[0] }}</p>
        </div>

        <!-- Secciones que aporta el director de este tipo de atención -->
        <div
          v-for="campo in campos"
          :key="campo.name"
          class="campo"
          :class="[campo.type, campo.type === 'textarea' ? 'total' : '']"
        >
          <label :for="campo.name">{{ campo.label }}</label>

          <select v-if="campo.type === 'select'" :id="campo.name" v-model="valores[campo.name]" required>
            <option v-for="opcion in campo.options" :key="opcion" :value="opcion">{{ opcion }}</option>
          </select>

          <textarea
            v-else-if="campo.type === 'textarea'"
            :id="campo.name"
            v-model="valores[campo.name]"
            rows="3"
          />

          <input
            v-else
            :id="campo.name"
            v-model="valores[campo.name]"
            :type="campo.type"
            :true-value="true"
            :aria-invalid="Boolean(fieldErrors[campo.name])"
          />

          <p v-if="fieldErrors[campo.name]" class="field-error">{{ fieldErrors[campo.name][0] }}</p>
        </div>

        <div class="campo total">
          <label>Diagnósticos (CIE-10)</label>
          <div v-for="(d, i) in diagnosticos" :key="i" class="diagnostico">
            <input v-model="d.code" type="text" placeholder="I10" class="codigo" />
            <input v-model="d.description" type="text" placeholder="Hipertensión esencial" />
            <button
              v-if="diagnosticos.length > 1"
              type="button"
              class="btn ghost pequeno"
              @click="quitarDiagnostico(i)"
            >
              Quitar
            </button>
          </div>
          <button type="button" class="btn ghost pequeno alinear" @click="agregarDiagnostico">
            <AppIcon name="plus" :size="15" /> Añadir diagnóstico
          </button>
          <p v-if="fieldErrors['diagnoses.0.code']" class="field-error">
            {{ fieldErrors['diagnoses.0.code'][0] }}
          </p>
        </div>

        <div class="campo total">
          <label for="plan">Plan de manejo</label>
          <textarea id="plan" v-model="valores.treatment_plan" rows="3" required />
          <p v-if="fieldErrors.treatment_plan" class="field-error">{{ fieldErrors.treatment_plan[0] }}</p>
        </div>

        <button type="submit" class="btn" :disabled="enviando">
          <AppIcon name="note" :size="17" />
          {{ enviando ? 'Construyendo...' : 'Registrar atención' }}
        </button>

        <p v-if="tipo && !tipo.in_person" class="nota aparte">
          En una teleconsulta el director nunca documenta examen físico: el paciente no está
          presente y consignarlo falsearía la historia clínica.
        </p>
      </form>

      <p v-if="aviso" class="aviso" :class="aviso.tono" role="status">{{ aviso.texto }}</p>

      <ul v-if="fieldErrors.note" class="faltantes">
        <li>La nota quedó incompleta. Falta:</li>
        <li v-for="seccion in fieldErrors.note" :key="seccion"><code>{{ seccion }}</code></li>
      </ul>
    </section>

    <section class="card">
      <header class="card-head">
        <h2>Atenciones registradas <span class="count">{{ encounters.length }}</span></h2>
      </header>

      <div class="scroll">
        <table>
          <thead>
            <tr>
              <th>Tipo</th>
              <th>Motivo</th>
              <th>Diagnóstico principal</th>
              <th>Triaje</th>
              <th>Atención</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <template v-for="e in encounters" :key="e.id">
              <tr>
                <td>{{ e.type_label }}</td>
                <td>{{ e.chief_complaint }}</td>
                <td><code>{{ e.diagnoses[0]?.code }}</code> {{ e.diagnoses[0]?.description }}</td>
                <td>{{ e.triage ?? '—' }}</td>
                <td>{{ fecha(e.attended_at) }}</td>
                <td class="acciones-fila">
                  <button
                    type="button"
                    class="btn ghost pequeno"
                    @click="abierta = abierta === e.id ? null : e.id"
                  >
                    {{ abierta === e.id ? 'Cerrar' : 'Ver nota' }}
                  </button>
                  <button
                    type="button"
                    class="btn ghost pequeno"
                    :disabled="guardando === e.id"
                    title="Clona la estructura de esta nota sin los datos del paciente"
                    @click="guardarComoPlantilla(e)"
                  >
                    {{ guardando === e.id ? 'Guardando...' : 'Guardar plantilla' }}
                  </button>
                </td>
              </tr>
              <tr v-if="abierta === e.id">
                <td colspan="6" class="detalle">
                  <dl>
                    <dt>Enfermedad actual</dt>
                    <dd>{{ e.present_illness }}</dd>

                    <template v-if="e.history">
                      <dt>Antecedentes</dt>
                      <dd>{{ e.history }}</dd>
                    </template>

                    <template v-if="e.physical_exam">
                      <dt>Examen físico</dt>
                      <dd>{{ e.physical_exam }}</dd>
                    </template>

                    <template v-if="e.vital_signs.length">
                      <dt>Signos vitales</dt>
                      <dd>
                        <span v-for="(v, i) in e.vital_signs" :key="i" class="estado" :class="v.severity">
                          {{ v.display }}: {{ v.value }} {{ v.unit }}
                        </span>
                      </dd>
                    </template>

                    <dt>Plan de manejo</dt>
                    <dd>{{ e.treatment_plan }}</dd>

                    <template v-if="e.prescriptions.length">
                      <dt>Prescripciones</dt>
                      <dd>
                        <span v-for="(p, i) in e.prescriptions" :key="i">
                          {{ p.active_ingredient }} {{ p.dose }} {{ p.frequency }} ({{ p.duration_days }} días)
                        </span>
                      </dd>
                    </template>

                    <dt>Profesional</dt>
                    <dd>Registro {{ e.professional_license }}</dd>
                  </dl>
                </td>
              </tr>
            </template>
            <tr v-if="!encounters.length">
              <td colspan="6" class="vacio">Aún no hay atenciones registradas.</td>
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
  max-width: 72ch;
}

.tipos {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
  gap: 12px;
}

.tipo {
  font: inherit;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 6px;
  padding: 14px;
  text-align: left;
  color: var(--text);
  border: 1px solid var(--border);
  border-radius: 10px;
  background: none;
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s;
}

.tipo:hover {
  border-color: var(--accent);
}

.tipo.activo {
  border-color: var(--accent);
  background: var(--accent-bg);
}

.tipo strong {
  font-size: 14.5px;
  color: var(--text-h);
}

.tipo small {
  font-size: 12.5px;
  opacity: 0.8;
}

.secciones {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
}

.secciones em {
  font-style: normal;
  font-size: 11.5px;
  padding: 2px 7px;
  border-radius: 20px;
  color: var(--accent);
  background: var(--accent-bg);
}

.plantillas {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-top: 20px;
  padding: 14px;
  border: 1px dashed var(--border);
  border-radius: 10px;
}

.plantillas > label {
  font-size: 13px;
}

.plantillas select {
  max-width: 420px;
}

.acciones-fila {
  display: flex;
  gap: 6px;
  flex-wrap: wrap;
}

.formulario {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  gap: 14px;
  margin-top: 20px;
}

.campo {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 170px;
}

.campo.ancho {
  flex: 1 1 240px;
}

.campo.total {
  flex-basis: 100%;
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

.card textarea {
  font: inherit;
  font-size: 14.5px;
  padding: 9px 11px;
  border: 1px solid var(--border);
  border-radius: 8px;
  color: var(--text-h);
  background: var(--bg);
  resize: vertical;
}

.card textarea:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 1px;
}

.diagnostico {
  display: flex;
  gap: 8px;
  margin-bottom: 8px;
}

.diagnostico input {
  flex: 1;
}

.diagnostico .codigo {
  flex: 0 0 110px;
  font-family: var(--mono);
  text-transform: uppercase;
}

.alinear {
  align-self: flex-start;
}

.aparte {
  flex: 1 1 320px;
  margin: 0;
}

.aviso {
  margin-top: 16px;
  font-size: 14.5px;
  padding: 11px 14px;
  border-radius: 8px;
  color: var(--ok);
  background: var(--ok-bg);
}

.aviso.failure {
  color: var(--danger);
  background: var(--danger-bg);
}

.faltantes {
  list-style: none;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  align-items: center;
  margin: 12px 0 0;
  padding: 11px 14px;
  font-size: 13.5px;
  border-radius: 8px;
  color: var(--danger);
  background: var(--danger-bg);
}

.detalle {
  background: var(--code-bg);
}

.detalle dl {
  display: grid;
  grid-template-columns: minmax(140px, auto) 1fr;
  gap: 6px 16px;
  margin: 0;
  padding: 6px 0;
}

.detalle dt {
  font-size: 12.5px;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  opacity: 0.7;
}

.detalle dd {
  margin: 0;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}
</style>
