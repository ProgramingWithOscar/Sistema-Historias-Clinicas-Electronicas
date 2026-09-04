<script setup>
import { ref } from 'vue'
import { useAuth } from '../composables/useAuth'

const { login, loading } = useAuth()

const email = ref('')
const password = ref('')
const message = ref(null)
const fieldErrors = ref({})

async function submit() {
  message.value = null
  fieldErrors.value = {}

  try {
    await login({
      email: email.value,
      password: password.value,
    })
  } catch (e) {
    fieldErrors.value = e.errors ?? {}
    message.value = e.message
  }
}
</script>

<template>
  <form class="login" @submit.prevent="submit">
    <h2>Iniciar sesión</h2>
    <p class="hint">Historia Clínica Electrónica</p>

    <label for="email">Correo</label>
    <input
      id="email"
      v-model="email"
      type="email"
      autocomplete="username"
      required
      :aria-invalid="Boolean(fieldErrors.email)"
      placeholder="medico@clinica.test"
    />
    <p v-if="fieldErrors.email" class="field-error">{{ fieldErrors.email[0] }}</p>

    <label for="password">Contraseña</label>
    <input
      id="password"
      v-model="password"
      type="password"
      autocomplete="current-password"
      required
      :aria-invalid="Boolean(fieldErrors.password)"
    />
    <p v-if="fieldErrors.password" class="field-error">{{ fieldErrors.password[0] }}</p>

    <button type="submit" :disabled="loading">
      {{ loading ? 'Verificando...' : 'Entrar' }}
    </button>

    <p v-if="message" class="error" role="alert">{{ message }}</p>
  </form>
</template>

<style scoped>
.login {
  width: 360px;
  max-width: 100%;
  text-align: left;
  display: flex;
  flex-direction: column;
  padding: 32px;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: var(--surface);
  box-shadow: var(--shadow);
  box-sizing: border-box;
}

h2 {
  margin-bottom: 4px;
}

.hint {
  font-size: 15px;
  margin-bottom: 24px;
}

label {
  font-size: 15px;
  color: var(--text-h);
  margin-bottom: 6px;
}

input {
  font: inherit;
  font-size: 16px;
  padding: 10px 12px;
  margin-bottom: 16px;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: var(--bg);
  color: var(--text-h);
}

input:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 1px;
  border-color: transparent;
}

input[aria-invalid='true'] {
  border-color: var(--danger);
}

button {
  font: inherit;
  font-size: 16px;
  margin-top: 8px;
  padding: 10px 16px;
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

button:disabled {
  opacity: 0.6;
  cursor: progress;
}

.error {
  margin-top: 16px;
}

/* El resto de estilos de error vienen de style.css */
.field-error {
  margin: -10px 0 12px;
  font-size: 14px;
  color: var(--danger);
}
</style>
