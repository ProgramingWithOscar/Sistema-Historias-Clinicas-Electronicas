<script setup>
import { onMounted } from 'vue'
import { useClinicalData } from '../../composables/useClinicalData'
import { useRefresh } from '../../composables/useRefresh'
import { acciones, fecha } from '../../utils/format'

const { logs, error, cargarAuditoria } = useClinicalData()
const { register } = useRefresh()

register(cargarAuditoria)
onMounted(cargarAuditoria)
</script>

<template>
  <div class="vista">
    <p v-if="error" class="error" role="alert">{{ error }}</p>

    <section class="card">
      <header class="card-head">
        <h2>Auditoría <span class="count">{{ logs.length }}</span></h2>
        <p class="nota">
          Registrada por el Singleton <code>AuditLogger</code>. El identificador de
          petición agrupa los eventos de una misma atención y la secuencia da su orden;
          incluye los intentos fallidos contra tu cuenta.
        </p>
      </header>

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
  </div>
</template>

<style scoped>
.nota {
  font-size: 13.5px;
  max-width: 72ch;
}

.http {
  margin-left: 6px;
  font-size: 12px;
  opacity: 0.7;
}
</style>
