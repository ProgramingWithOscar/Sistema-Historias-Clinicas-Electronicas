<script setup>
import { onMounted } from 'vue'
import Dashboard from './components/Dashboard.vue'
import LoginForm from './components/LoginForm.vue'
import { useAuth } from './composables/useAuth'

const { isAuthenticated, ready, restore } = useAuth()

onMounted(restore)
</script>

<template>
  <main id="center">
    <h1>Historia Clínica Electrónica</h1>

    <p v-if="!ready">Cargando...</p>
    <Dashboard v-else-if="isAuthenticated" />
    <LoginForm v-else />
  </main>
</template>
