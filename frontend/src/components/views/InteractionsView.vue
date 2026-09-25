<script setup>
import { computed, onMounted, ref } from 'vue'
import AppIcon from '../ui/AppIcon.vue'
import { useClinicalData } from '../../composables/useClinicalData'

const { interactionSources, error, cargarFuentesInteracciones, verificarInteracciones } =
  useClinicalData()

const source = ref('')
const farmacos = ref([{ nombre: 'Losartán' }, { nombre: 'Espironolactona' }])
const verificando = ref(false)
const reporte = ref(null)
const aviso = ref(null)

const fuente = computed(() => interactionSources.value.find((f) => f.source === source.value))

const listos = computed(() => farmacos.value.filter((f) => f.nombre.trim()).length >= 2)

function agregar() {
  farmacos.value.push({ nombre: '' })
}

function quitar(i) {
  farmacos.value.splice(i, 1)
}

async function verificar() {
  verificando.value = true
  aviso.value = null
  reporte.value = null

  try {
    reporte.value = await verificarInteracciones(
      farmacos.value.map((f) => f.nombre.trim()).filter(Boolean),
      source.value || null,
    )

    if (!reporte.value.total) {
      aviso.value = {
        tono: 'success',
        texto: 'No se encontraron interacciones entre los principios activos consultados.',
      }
    }
  } catch (e) {
    aviso.value = { tono: 'failure', texto: e.errors?.drugs?.[0] ?? e.message }
  } finally {
    verificando.value = false
  }
}

onMounted(cargarFuentesInteracciones)
</script>

<template>
  <div class="vista">
    <p v-if="error" class="error" role="alert">{{ error }}</p>

    <section class="card">
      <header class="card-head">
        <h2>Verificar interacciones medicamentosas</h2>
        <p class="nota">
          Las dos fuentes tienen interfaces incompatibles entre sí —una pide códigos numéricos y
          responde en inglés; la otra busca por nombre en un CSV—. El backend las envuelve con un
          adaptador cada una, así que desde aquí se consultan exactamente igual (patrón Adapter).
        </p>
      </header>

      <div class="fuentes">
        <button
          type="button"
          class="fuente"
          :class="{ activa: source === '' }"
          @click="source = ''"
        >
          <strong>Fuente por defecto</strong>
          <small>La que defina la configuración del prestador</small>
        </button>

        <button
          v-for="f in interactionSources"
          :key="f.source"
          type="button"
          class="fuente"
          :class="{ activa: source === f.source }"
          @click="source = f.source"
        >
          <strong>{{ f.label }}</strong>
          <small>{{ f.origin }}</small>
          <span class="estado" :class="f.requires_network ? 'warning' : 'normal'">
            {{ f.requires_network ? 'Requiere red' : 'Local' }}
          </span>
        </button>
      </div>

      <form class="formulario" @submit.prevent="verificar">
        <div class="campo total">
          <label>Principios activos de la fórmula</label>

          <div v-for="(f, i) in farmacos" :key="i" class="farmaco">
            <input
              v-model="f.nombre"
              type="text"
              placeholder="Ej.: Warfarina"
              :aria-label="`Principio activo ${i + 1}`"
            />
            <button
              v-if="farmacos.length > 2"
              type="button"
              class="btn ghost pequeno"
              @click="quitar(i)"
            >
              Quitar
            </button>
          </div>

          <button type="button" class="btn ghost pequeno alinear" @click="agregar">
            <AppIcon name="plus" :size="15" /> Añadir medicamento
          </button>
        </div>

        <button type="submit" class="btn" :disabled="verificando || !listos">
          <AppIcon name="pill" :size="17" />
          {{ verificando ? 'Verificando...' : 'Verificar fórmula' }}
        </button>

        <p v-if="fuente" class="nota aparte">
          Consultando <strong>{{ fuente.label }}</strong>.
          {{ fuente.requires_network ? 'Si el servicio no responde, la verificación devuelve un informe vacío en lugar de fallar.' : 'No depende de conexión a internet.' }}
        </p>
      </form>

      <p v-if="aviso" class="aviso" :class="aviso.tono" role="status">{{ aviso.texto }}</p>
    </section>

    <section v-if="reporte && reporte.total" class="card">
      <header class="card-head">
        <h2>
          Hallazgos
          <span class="count">{{ reporte.total }}</span>
        </h2>
        <p class="nota">
          Fuente: <code>{{ reporte.source }}</code> · Consultados:
          {{ reporte.checked_drugs.join(', ') }}
        </p>
      </header>

      <ul class="hallazgos">
        <li v-for="(i, idx) in reporte.interactions" :key="idx" :class="i.severity">
          <div class="cabecera">
            <span class="par">{{ i.drug_a }} + {{ i.drug_b }}</span>
            <span class="estado" :class="i.severity">{{ i.severity_label }}</span>
          </div>
          <p>{{ i.description }}</p>
        </li>
      </ul>
    </section>
  </div>
</template>

<style scoped>
.nota {
  font-size: 13.5px;
  max-width: 72ch;
}

.fuentes {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
  gap: 12px;
}

.fuente {
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

.fuente:hover {
  border-color: var(--accent);
}

.fuente.activa {
  border-color: var(--accent);
  background: var(--accent-bg);
}

.fuente strong {
  font-size: 14.5px;
  color: var(--text-h);
}

.fuente small {
  font-size: 12.5px;
  opacity: 0.8;
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

.campo.total {
  flex-basis: 100%;
}

.campo label {
  font-size: 13px;
}

.farmaco {
  display: flex;
  gap: 8px;
  margin-bottom: 8px;
  max-width: 460px;
}

.farmaco input {
  flex: 1;
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

.hallazgos {
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 10px;
  margin: 0;
  padding: 0;
}

.hallazgos li {
  padding: 13px 15px;
  border-radius: 10px;
  border: 1px solid var(--border);
  border-left-width: 4px;
}

.hallazgos li.leve {
  border-left-color: var(--border);
}

.hallazgos li.moderada {
  border-left-color: var(--warning);
}

.hallazgos li.grave,
.hallazgos li.contraindicada {
  border-left-color: var(--danger);
}

.cabecera {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 10px;
  margin-bottom: 6px;
}

.par {
  font-weight: 500;
  color: var(--text-h);
}

.hallazgos p {
  margin: 0;
  font-size: 13.5px;
  line-height: 1.55;
}

.estado.contraindicada {
  color: var(--danger);
  background: var(--danger-bg);
}

.estado.moderada {
  color: var(--warning);
  background: var(--warning-bg);
}

.estado.leve {
  color: var(--text);
  background: var(--code-bg);
}
</style>
