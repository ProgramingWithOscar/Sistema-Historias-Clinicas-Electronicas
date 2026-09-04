<script setup>
/**
 * Iconografía en línea (trazo, 24×24). Van como paths dentro del componente en
 * lugar de un sprite externo para que hereden `currentColor` y no dependan de
 * una petición extra ni de una librería de iconos.
 */
defineProps({
  name: { type: String, required: true },
  size: { type: [Number, String], default: 20 },
})

const paths = {
  grid: ['M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z'],
  activity: ['M3 12h4l3 8 4-16 3 8h4'],
  shield: ['M12 3l7 3v6c0 4.5-3 7.6-7 9-4-1.4-7-4.5-7-9V6z'],
  list: ['M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01'],
  menu: ['M4 6h16M4 12h16M4 18h16'],
  refresh: ['M20 11a8 8 0 1 0-2.3 6.3', 'M20 5v6h-6'],
  logout: ['M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3', 'M10 17l-5-5 5-5', 'M5 12h11'],
  plus: ['M12 5v14M5 12h14'],
  heart: ['M12 20s-7-4.4-7-9.5A4 4 0 0 1 12 8a4 4 0 0 1 7 2.5C19 15.6 12 20 12 20z'],
}
</script>

<template>
  <svg
    class="icon"
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.7"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
  >
    <path v-for="(d, i) in paths[name]" :key="i" :d="d" />
  </svg>
</template>

<style scoped>
.icon {
  flex: none;
}
</style>
