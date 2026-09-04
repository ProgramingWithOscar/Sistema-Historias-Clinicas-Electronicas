<script setup>
import { onMounted } from 'vue'
import AppShell from './components/AppShell.vue'
import LoginForm from './components/LoginForm.vue'
import AuditView from './components/views/AuditView.vue'
import DeviceReadingsView from './components/views/DeviceReadingsView.vue'
import OverviewView from './components/views/OverviewView.vue'
import SessionsView from './components/views/SessionsView.vue'
import { useAuth } from './composables/useAuth'
import { useNavigation } from './composables/useNavigation'

const { isAuthenticated, ready, restore } = useAuth()
const { current } = useNavigation()

// Cada sección es un componente; `<component :is>` hace de router mínimo.
const vistas = {
  overview: OverviewView,
  readings: DeviceReadingsView,
  sessions: SessionsView,
  audit: AuditView,
}

onMounted(restore)
</script>

<template>
  <p v-if="!ready" class="cargando">Cargando...</p>

  <AppShell v-else-if="isAuthenticated">
    <!-- `key` fuerza el remontaje al cambiar de sección: cada vista recarga sus datos. -->
    <component :is="vistas[current]" :key="current" />
  </AppShell>

  <main v-else class="acceso">
    <h1>Historia Clínica Electrónica</h1>
    <LoginForm />
  </main>
</template>

<style scoped>
.cargando,
.acceso {
  min-height: 100svh;
  display: flex;
  flex-direction: column;
  gap: 28px;
  align-items: center;
  justify-content: center;
  padding: 32px 20px;
  box-sizing: border-box;
}

.acceso h1 {
  font-size: 34px;
  font-weight: 500;
  letter-spacing: -0.8px;
  text-align: center;
  margin: 0;
  color: var(--text-h);
}
</style>
