<script setup>
import { onMounted } from 'vue'
import { useClinicalData } from '../../composables/useClinicalData'
import { useRefresh } from '../../composables/useRefresh'
import { fecha, navegador } from '../../utils/format'

const { sessions, error, cargarSesiones } = useClinicalData()
const { register } = useRefresh()

register(cargarSesiones)
onMounted(cargarSesiones)
</script>

<template>
  <div class="vista">
    <p v-if="error" class="error" role="alert">{{ error }}</p>

    <section class="card">
      <header class="card-head">
        <h2>Sesiones activas <span class="count">{{ sessions.length }}</span></h2>
        <p class="nota">Si no reconoces alguna, cierra la sesión y cambia tu contraseña.</p>
      </header>

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
    </section>
  </div>
</template>

<style scoped>
.nota {
  font-size: 13.5px;
}

.badge {
  margin-left: 8px;
  font-size: 12px;
  padding: 2px 8px;
  border-radius: 20px;
  color: var(--accent);
  background: var(--accent-bg);
}
</style>
