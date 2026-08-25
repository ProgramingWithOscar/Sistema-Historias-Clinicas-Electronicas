<script setup>
import { onMounted, ref } from 'vue'
import { api } from './services/api'

const data = ref(null)
const error = ref(null)
const loading = ref(false)

async function ping() {
  loading.value = true
  error.value = null
  try {
    data.value = await api('/ping')
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

onMounted(ping)
</script>

<template>
  <main>
    <h1>Vue + Laravel</h1>
    <button :disabled="loading" @click="ping">
      {{ loading ? 'Consultando...' : 'Llamar /api/ping' }}
    </button>
    <p v-if="error" class="error">{{ error }}</p>
    <pre v-else-if="data">{{ data }}</pre>
  </main>
</template>

<style scoped>
main { font-family: system-ui, sans-serif; padding: 2rem; }
pre { background: #1112; padding: 1rem; border-radius: 6px; }
.error { color: #c00; }
</style>
