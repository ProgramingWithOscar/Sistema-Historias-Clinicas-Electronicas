<script setup>
import AppIcon from './AppIcon.vue'

defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], required: true },
  hint: { type: String, default: null },
  icon: { type: String, default: 'grid' },
  tone: { type: String, default: 'accent' },
})
</script>

<template>
  <article class="stat" :class="tone">
    <span class="badge"><AppIcon :name="icon" :size="18" /></span>
    <p class="value">{{ value }}</p>
    <p class="label">{{ label }}</p>
    <p v-if="hint" class="hint">{{ hint }}</p>
  </article>
</template>

<style scoped>
.stat {
  padding: 18px 20px;
  border: 1px solid var(--border);
  border-radius: 12px;
  background: var(--surface);
}

.badge {
  display: inline-flex;
  padding: 8px;
  border-radius: 10px;
  color: var(--accent);
  background: var(--accent-bg);
  margin-bottom: 14px;
}

.stat.warning .badge {
  color: var(--warning);
  background: var(--warning-bg);
}

.stat.danger .badge {
  color: var(--danger);
  background: var(--danger-bg);
}

.value {
  font-size: 30px;
  line-height: 1.1;
  font-weight: 500;
  color: var(--text-h);
}

.label {
  margin-top: 4px;
  font-size: 14px;
}

.hint {
  margin-top: 6px;
  font-size: 13px;
  opacity: 0.75;
}
</style>
