<script setup>
import AppIcon from './ui/AppIcon.vue'
import { useAuth } from '../composables/useAuth'
import { useNavigation } from '../composables/useNavigation'
import { useRefresh } from '../composables/useRefresh'

const { user, logout, loading } = useAuth()
const { sections, current, section, sidebarOpen, go, toggleSidebar } = useNavigation()
const { refresh, refreshing } = useRefresh()

const iniciales = (nombre) =>
  (nombre ?? '')
    .split(' ')
    .slice(0, 2)
    .map((parte) => parte[0])
    .join('')
    .toUpperCase()
</script>

<template>
  <div class="shell" :class="{ 'sidebar-open': sidebarOpen }">
    <!-- Sidebar: navegación entre secciones -->
    <aside class="sidebar">
      <div class="brand">
        <span class="mark"><AppIcon name="heart" :size="18" /></span>
        <span class="brand-text">
          <strong>HCE</strong>
          <small>Historia Clínica</small>
        </span>
      </div>

      <nav aria-label="Secciones">
        <ul>
          <li v-for="item in sections" :key="item.id">
            <button
              type="button"
              class="nav-item"
              :class="{ active: current === item.id }"
              :aria-current="current === item.id ? 'page' : undefined"
              @click="go(item.id)"
            >
              <AppIcon :name="item.icon" />
              {{ item.label }}
            </button>
          </li>
        </ul>
      </nav>

      <div class="sidebar-foot">
        <p class="patrones">Patrones aplicados</p>
        <ul class="chips">
          <li>Singleton</li>
          <li>Factory Method</li>
        </ul>
      </div>
    </aside>

    <!-- Capa que cierra el sidebar en móvil al tocar fuera -->
    <div class="overlay" @click="toggleSidebar" />

    <div class="main">
      <!-- Toolbar: contexto de la sección y acciones globales -->
      <header class="toolbar">
        <button type="button" class="icon-btn burger" aria-label="Alternar menú" @click="toggleSidebar">
          <AppIcon name="menu" />
        </button>

        <div class="titles">
          <h1>{{ section.title }}</h1>
          <p>{{ section.description }}</p>
        </div>

        <div class="tools">
          <button type="button" class="btn ghost" :disabled="refreshing" @click="refresh">
            <AppIcon name="refresh" :size="17" :class="{ spin: refreshing }" />
            <span class="solo-ancho">{{ refreshing ? 'Actualizando' : 'Actualizar' }}</span>
          </button>

          <div class="user">
            <span class="avatar" aria-hidden="true">{{ iniciales(user.name) }}</span>
            <span class="user-text solo-ancho">
              <strong>{{ user.name }}</strong>
              <small>{{ user.email }}</small>
            </span>
          </div>

          <button
            type="button"
            class="icon-btn"
            :disabled="loading"
            :aria-label="loading ? 'Cerrando sesión' : 'Cerrar sesión'"
            @click="logout"
          >
            <AppIcon name="logout" :size="18" />
          </button>
        </div>
      </header>

      <main class="contenido">
        <slot />
      </main>
    </div>
  </div>
</template>

<style scoped>
.shell {
  display: grid;
  grid-template-columns: 248px 1fr;
  min-height: 100svh;
}

/* ---------- Sidebar ---------- */
.sidebar {
  display: flex;
  flex-direction: column;
  gap: 24px;
  padding: 20px 16px;
  border-right: 1px solid var(--border);
  background: var(--surface);
}

.brand {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 4px 8px 20px;
  border-bottom: 1px solid var(--border);
}

.mark {
  display: inline-flex;
  padding: 8px;
  border-radius: 10px;
  color: #fff;
  background: var(--accent);
}

.brand-text {
  display: flex;
  flex-direction: column;
  line-height: 1.25;
}

.brand-text strong {
  font-size: 17px;
  color: var(--text-h);
}

.brand-text small {
  font-size: 12px;
}

nav ul,
.chips {
  list-style: none;
  margin: 0;
  padding: 0;
}

nav ul {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.nav-item {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 11px;
  font: inherit;
  font-size: 15px;
  text-align: left;
  padding: 10px 12px;
  border: none;
  border-radius: 8px;
  color: var(--text);
  background: none;
  cursor: pointer;
  transition: background 0.2s, color 0.2s;
}

.nav-item:hover {
  color: var(--text-h);
  background: var(--code-bg);
}

.nav-item.active {
  color: var(--accent);
  background: var(--accent-bg);
  font-weight: 500;
}

.sidebar-foot {
  margin-top: auto;
  padding-top: 16px;
  border-top: 1px solid var(--border);
}

.patrones {
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  opacity: 0.7;
  margin-bottom: 8px;
}

.chips {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.chips li {
  font-size: 12px;
  padding: 4px 9px;
  border-radius: 20px;
  color: var(--accent);
  background: var(--accent-bg);
}

/* ---------- Toolbar ---------- */
.toolbar {
  position: sticky;
  top: 0;
  z-index: 5;
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 14px 24px;
  border-bottom: 1px solid var(--border);
  background: var(--bg-blur);
  backdrop-filter: blur(8px);
}

.titles {
  flex: 1;
  min-width: 0;
}

.titles h1 {
  font-size: 20px;
  font-weight: 500;
  letter-spacing: -0.2px;
  margin: 0;
  color: var(--text-h);
}

.titles p {
  font-size: 13px;
  margin-top: 2px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tools {
  display: flex;
  align-items: center;
  gap: 10px;
}

.user {
  display: flex;
  align-items: center;
  gap: 9px;
  padding-left: 10px;
  border-left: 1px solid var(--border);
}

.avatar {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  font-size: 13px;
  font-weight: 500;
  color: var(--accent);
  background: var(--accent-bg);
}

.user-text {
  display: flex;
  flex-direction: column;
  line-height: 1.25;
  max-width: 160px;
}

.user-text strong {
  font-size: 14px;
  color: var(--text-h);
}

.user-text small {
  font-size: 12px;
  overflow: hidden;
  text-overflow: ellipsis;
}

.spin {
  animation: giro 0.9s linear infinite;
}

@keyframes giro {
  to {
    transform: rotate(360deg);
  }
}

@media (prefers-reduced-motion: reduce) {
  .spin {
    animation: none;
  }
}

.contenido {
  padding: 28px 24px 48px;
}

/* ---------- Responsive: el sidebar pasa a cajón ---------- */
.burger,
.overlay {
  display: none;
}

@media (max-width: 900px) {
  .shell {
    grid-template-columns: 1fr;
  }

  .sidebar {
    position: fixed;
    z-index: 20;
    inset: 0 auto 0 0;
    width: 248px;
    transform: translateX(-100%);
    transition: transform 0.25s ease;
  }

  .sidebar-open .sidebar {
    transform: none;
    box-shadow: var(--shadow);
  }

  .sidebar-open .overlay {
    display: block;
    position: fixed;
    inset: 0;
    z-index: 10;
    background: rgba(0, 0, 0, 0.45);
  }

  .burger {
    display: inline-flex;
  }

  .toolbar {
    padding: 12px 16px;
  }

  .contenido {
    padding: 20px 16px 40px;
  }

  .solo-ancho {
    display: none;
  }

  .user {
    padding-left: 6px;
  }
}
</style>
