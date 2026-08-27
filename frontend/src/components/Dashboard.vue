<script setup>
import { onMounted, ref } from 'vue'
import { useAuth } from '../composables/useAuth'
import { api } from '../services/api'

const { user, logout, loading } = useAuth()

const sessions = ref([])
const logs = ref([])
const error = ref(null)
const refreshing = ref(false)

const acciones = {
  'auth.login.succeeded': 'Inicio de sesión',
  'auth.login.failed': 'Intento fallido',
  'auth.login.throttled': 'Bloqueado por intentos',
  'auth.logout': 'Cierre de sesión',
  'auth.session.read': 'Lectura de sesión',
}

function fecha(iso) {
  return new Date(iso).toLocaleString('es-CO', {
    dateStyle: 'short',
    timeStyle: 'medium',
  })
}

function navegador(userAgent) {
  if (!userAgent) return 'Desconocido'
  const match = userAgent.match(/(Firefox|Edg|Chrome|Safari)\/[\d.]+/)
  return match ? match[0].replace('Edg', 'Edge') : userAgent.slice(0, 40)
}

async function cargar() {
  refreshing.value = true
  error.value = null
  try {
    const [s, l] = await Promise.all([api('/sessions'), api('/audit-logs')])
    sessions.value = s.data
    logs.value = l.data
  } catch (e) {
    error.value = e.message
  } finally {
    refreshing.value = false
  }
}

onMounted(cargar)
</script>

<template>
  <section class="panel">
    <header>
      <div>
        <h2>{{ user.name }}</h2>
        <p class="sub">{{ user.email }}</p>
      </div>
      <div class="actions">
        <button class="secondary" :disabled="refreshing" @click="cargar">
          {{ refreshing ? 'Actualizando...' : 'Actualizar' }}
        </button>
        <button :disabled="loading" @click="logout">
          {{ loading ? 'Cerrando...' : 'Cerrar sesión' }}
        </button>
      </div>
    </header>

    <p v-if="error" class="error" role="alert">{{ error }}</p>

    <h3>Sesiones activas <span class="count">{{ sessions.length }}</span></h3>
    <div class="scroll">
      <table>
        <thead>
          <tr>
            <th>Sesión</th>
            <th>IP</th>
            <th>Navegador</th>
            <th>Última actividad</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in sessions" :key="s.id">
            <td>
              <code>{{ s.id }}</code>
              <span v-if="s.is_current" class="badge">actual</span>
            </td>
            <td>{{ s.ip_address }}</td>
            <td>{{ navegador(s.user_agent) }}</td>
            <td>{{ fecha(s.last_activity) }}</td>
          </tr>
          <tr v-if="!sessions.length">
            <td colspan="4" class="vacio">Sin sesiones registradas.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <h3>
      Auditoría <span class="count">{{ logs.length }}</span>
      <span class="sub">
        registrada por el Singleton <code>AuditLogger</code> · incluye intentos
        fallidos contra tu cuenta
      </span>
    </h3>
    <div class="scroll">
      <table>
        <thead>
          <tr>
            <th>Petición</th>
            <th>#</th>
            <th>Evento</th>
            <th>Estado</th>
            <th>IP</th>
            <th>Fecha</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="log in logs" :key="log.id">
            <td><code>{{ log.request_id }}</code></td>
            <td class="num">{{ log.sequence }}</td>
            <td>{{ acciones[log.action] ?? log.action }}</td>
            <td>
              <span class="estado" :class="log.outcome">{{ log.outcome_label }}</span>
              <code v-if="log.status_code" class="http">{{ log.status_code }}</code>
            </td>
            <td>{{ log.ip_address }}</td>
            <td>{{ fecha(log.created_at) }}</td>
          </tr>
          <tr v-if="!logs.length">
            <td colspan="6" class="vacio">Sin eventos todavía.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<style scoped>
.panel {
  width: 860px;
  max-width: 100%;
  text-align: left;
  padding: 32px;
  border: 1px solid var(--border);
  border-radius: 10px;
  box-shadow: var(--shadow);
  box-sizing: border-box;
}

header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  flex-wrap: wrap;
  padding-bottom: 20px;
  border-bottom: 1px solid var(--border);
}

h2 {
  margin: 0;
}

h3 {
  font-size: 17px;
  font-weight: 500;
  color: var(--text-h);
  margin: 28px 0 10px;
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.sub {
  font-size: 14px;
  font-weight: 400;
  color: var(--text);
}

.count {
  font-family: var(--mono);
  font-size: 13px;
  padding: 2px 8px;
  border-radius: 20px;
  color: var(--accent);
  background: var(--accent-bg);
}

.actions {
  display: flex;
  gap: 10px;
}

button {
  font: inherit;
  font-size: 15px;
  padding: 7px 13px;
  border: 2px solid transparent;
  border-radius: 6px;
  color: var(--accent);
  background: var(--accent-bg);
  cursor: pointer;
  transition: border-color 0.3s;
}

button:hover:not(:disabled) {
  border-color: var(--accent-border);
}

button.secondary {
  color: var(--text-h);
  background: var(--code-bg);
}

button:disabled {
  opacity: 0.6;
  cursor: progress;
}

/* Las tablas anchas se desplazan dentro de su caja, no en la página. */
.scroll {
  overflow-x: auto;
  border: 1px solid var(--border);
  border-radius: 8px;
}

table {
  width: 100%;
  border-collapse: collapse;
  font-size: 14px;
  white-space: nowrap;
}

th {
  text-align: left;
  font-weight: 500;
  color: var(--text);
  padding: 10px 14px;
  background: var(--code-bg);
  border-bottom: 1px solid var(--border);
}

td {
  padding: 10px 14px;
  color: var(--text-h);
  border-bottom: 1px solid var(--border);
}

tr:last-child td {
  border-bottom: none;
}

.num {
  font-family: var(--mono);
  text-align: right;
}

code {
  font-size: 13px;
  padding: 2px 6px;
}

.badge {
  margin-left: 8px;
  font-size: 12px;
  padding: 2px 8px;
  border-radius: 20px;
  color: var(--accent);
  background: var(--accent-bg);
}

.estado {
  font-size: 12px;
  padding: 3px 9px;
  border-radius: 20px;
  white-space: nowrap;
}

.estado.success {
  color: #1b7f4f;
  background: rgba(27, 127, 79, 0.12);
}

.estado.failure {
  color: #c0392b;
  background: rgba(192, 57, 43, 0.12);
}

.estado.denied {
  color: #a86400;
  background: rgba(168, 100, 0, 0.14);
}

@media (prefers-color-scheme: dark) {
  .estado.success {
    color: #58d69b;
  }
  .estado.failure {
    color: #ff8a7a;
  }
  .estado.denied {
    color: #e5a745;
  }
}

.http {
  margin-left: 6px;
  font-size: 12px;
  opacity: 0.7;
}

.vacio {
  color: var(--text);
  text-align: center;
  padding: 20px;
}

.error {
  margin-top: 20px;
  font-size: 15px;
  color: #c0392b;
}
</style>
