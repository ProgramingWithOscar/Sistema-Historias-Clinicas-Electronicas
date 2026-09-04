<script setup>
import { computed, onMounted } from 'vue'
import StatCard from '../ui/StatCard.vue'
import { useAuth } from '../../composables/useAuth'
import { useClinicalData } from '../../composables/useClinicalData'
import { useNavigation } from '../../composables/useNavigation'
import { useRefresh } from '../../composables/useRefresh'
import { acciones, dispositivos, fecha } from '../../utils/format'

const { user } = useAuth()
const { go } = useNavigation()
const { sessions, logs, readings, error, criticas, atencion, cargarTodo } = useClinicalData()
const { register } = useRefresh()

const ultimos = computed(() => logs.value.slice(0, 6))
const ultimasLecturas = computed(() => readings.value.slice(0, 5))

register(cargarTodo)
onMounted(cargarTodo)
</script>

<template>
  <div class="vista">
    <p v-if="error" class="error" role="alert">{{ error }}</p>

    <section class="stats">
      <StatCard
        icon="shield"
        label="Sesiones activas"
        :value="sessions.length"
        hint="Dispositivos con sesión abierta"
      />
      <StatCard
        icon="activity"
        label="Lecturas IoT"
        :value="readings.length"
        hint="Normalizadas por sus fábricas"
      />
      <StatCard
        icon="heart"
        label="Requieren atención"
        :value="atencion"
        :tone="atencion ? 'warning' : 'accent'"
        :hint="`${criticas} crítica(s)`"
      />
      <StatCard
        icon="list"
        label="Eventos auditados"
        :value="logs.length"
        hint="Registrados por AuditLogger"
      />
    </section>

    <div class="columnas">
      <section class="card">
        <header class="card-head">
          <h2>Últimas lecturas</h2>
          <button type="button" class="btn ghost pequeno" @click="go('readings')">Ver todas</button>
        </header>
        <ul class="lista">
          <li v-for="r in ultimasLecturas" :key="r.id">
            <span class="punto" :class="r.severity" aria-hidden="true" />
            <span class="lista-texto">
              <strong>{{ r.display }}</strong>
              <small>{{ dispositivos[r.device_type] ?? r.device_type }} · {{ fecha(r.measured_at) }}</small>
            </span>
            <span class="valor">{{ r.value }} <small>{{ r.unit }}</small></span>
          </li>
          <li v-if="!readings.length" class="vacio">Todavía no hay lecturas registradas.</li>
        </ul>
      </section>

      <section class="card">
        <header class="card-head">
          <h2>Actividad reciente</h2>
          <button type="button" class="btn ghost pequeno" @click="go('audit')">Ver auditoría</button>
        </header>
        <ul class="lista">
          <li v-for="log in ultimos" :key="log.id">
            <span class="punto" :class="log.outcome" aria-hidden="true" />
            <span class="lista-texto">
              <strong>{{ acciones[log.action] ?? log.action }}</strong>
              <small>{{ fecha(log.created_at) }} · {{ log.ip_address }}</small>
            </span>
            <span class="estado" :class="log.outcome">{{ log.outcome_label }}</span>
          </li>
          <li v-if="!logs.length" class="vacio">Sin eventos todavía.</li>
        </ul>
      </section>
    </div>

    <section class="card bienvenida">
      <h2>Hola, {{ user.name }}</h2>
      <p>
        Este panel expone los dos patrones de diseño implementados en el backend: la
        auditoría centralizada (<strong>Singleton</strong>) y la ingesta de dispositivos
        médicos (<strong>Factory Method</strong>).
      </p>
    </section>
  </div>
</template>

<style scoped>
.stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
}

.columnas {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 16px;
}

.lista {
  list-style: none;
  margin: 0;
  padding: 0;
}

.lista li {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 11px 0;
  border-bottom: 1px solid var(--border);
}

.lista li:last-child {
  border-bottom: none;
  padding-bottom: 0;
}

.lista-texto {
  flex: 1;
  display: flex;
  flex-direction: column;
  line-height: 1.3;
  min-width: 0;
}

.lista-texto strong {
  font-size: 15px;
  font-weight: 500;
  color: var(--text-h);
}

.lista-texto small {
  font-size: 12.5px;
}

.valor {
  font-family: var(--mono);
  font-size: 15px;
  color: var(--text-h);
  white-space: nowrap;
}

.valor small {
  font-size: 12px;
  opacity: 0.7;
}

.punto {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--ok);
}

.punto.warning,
.punto.denied {
  background: var(--warning);
}

.punto.critical,
.punto.failure {
  background: var(--danger);
}

.bienvenida p {
  margin-top: 8px;
  font-size: 15px;
  max-width: 70ch;
}

.vacio {
  color: var(--text);
  font-size: 14px;
  padding: 14px 0;
}
</style>
