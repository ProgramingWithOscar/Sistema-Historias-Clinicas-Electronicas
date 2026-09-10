<script setup>
import { computed, onMounted, ref } from 'vue'
import AppIcon from '../ui/AppIcon.vue'
import { useClinicalData } from '../../composables/useClinicalData'

const { standards, exportacion, error, cargarEstandares, exportarHistoria } = useClinicalData()

const standard = ref('fhir_r4')
const generando = ref(false)
const aviso = ref(null)
const copiado = ref(false)

const familia = computed(() => standards.value.find((s) => s.standard === standard.value))

// El documento se muestra tal cual sale del backend: es la prueba de que cada
// familia produce una estructura completamente distinta.
const documento = computed(() =>
  exportacion.value ? JSON.stringify(exportacion.value.document, null, 2) : '',
)

async function generar() {
  generando.value = true
  aviso.value = null
  copiado.value = false

  try {
    const salida = await exportarHistoria(standard.value)
    aviso.value = {
      tono: 'success',
      texto: `${salida.label}: ${salida.observations} observación(es) en ${salida.filename}`,
    }
  } catch (e) {
    aviso.value = { tono: 'failure', texto: e.message }
  } finally {
    generando.value = false
  }
}

async function copiar() {
  await navigator.clipboard.writeText(documento.value)
  copiado.value = true
}

onMounted(cargarEstandares)
</script>

<template>
  <div class="vista">
    <p v-if="error" class="error" role="alert">{{ error }}</p>

    <section class="card">
      <header class="card-head">
        <h2>Generar documento de intercambio</h2>
        <p class="nota">
          El estándar elegido selecciona una familia completa de serializadores —paciente,
          observaciones y sobre— que el backend nunca mezcla entre sí (patrón Abstract Factory).
        </p>
      </header>

      <div class="familias">
        <button
          v-for="f in standards"
          :key="f.standard"
          type="button"
          class="familia"
          :class="{ activa: standard === f.standard }"
          :aria-pressed="standard === f.standard"
          @click="standard = f.standard"
        >
          <strong>{{ f.label }}</strong>
          <small>{{ f.legal_basis }}</small>
          <span class="estado" :class="f.identifies_patient ? 'warning' : 'normal'">
            {{ f.identifies_patient ? 'Datos identificables' : 'Disociado' }}
          </span>
        </button>
      </div>

      <div class="acciones">
        <button type="button" class="btn" :disabled="generando || !standards.length" @click="generar">
          <AppIcon name="share" :size="17" />
          {{ generando ? 'Generando...' : 'Exportar historia' }}
        </button>

        <p v-if="familia && !familia.identifies_patient" class="nota disociado">
          Esta familia sustituye al paciente por un seudónimo y agrupa la edad: ningún producto
          del documento publica datos identificables.
        </p>
      </div>

      <p v-if="aviso" class="aviso" :class="aviso.tono" role="status">{{ aviso.texto }}</p>
    </section>

    <section v-if="exportacion" class="card">
      <header class="card-head">
        <h2>
          Documento generado
          <span class="count">{{ exportacion.media_type }}</span>
        </h2>
        <button type="button" class="btn ghost pequeno" @click="copiar">
          {{ copiado ? 'Copiado' : 'Copiar JSON' }}
        </button>
        <p class="nota">
          Base legal: {{ exportacion.legal_basis }} · Archivo sugerido:
          <code>{{ exportacion.filename }}</code>
        </p>
      </header>

      <div class="scroll">
        <pre class="documento">{{ documento }}</pre>
      </div>
    </section>

    <section v-else class="card">
      <p class="vacio">Aún no se ha generado ningún documento en esta sesión.</p>
    </section>
  </div>
</template>

<style scoped>
.nota {
  font-size: 13.5px;
  max-width: 72ch;
}

.familias {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
  gap: 12px;
}

.familia {
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

.familia:hover {
  border-color: var(--accent);
}

.familia.activa {
  border-color: var(--accent);
  background: var(--accent-bg);
}

.familia strong {
  font-size: 14.5px;
  color: var(--text-h);
}

.familia small {
  font-size: 12.5px;
  opacity: 0.8;
}

.acciones {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 14px;
  margin-top: 18px;
}

.disociado {
  flex: 1 1 320px;
  margin: 0;
}

.documento {
  font-family: var(--mono);
  font-size: 12.5px;
  line-height: 1.55;
  margin: 0;
  padding: 4px 0;
  max-height: 460px;
  overflow: auto;
  white-space: pre;
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
</style>
