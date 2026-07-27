<script setup lang="ts">
withDefaults(defineProps<{ variant?: 'flat' | 'elevated' | 'glass' }>(), { variant: 'flat' });
</script>

<template>
    <VCard class="app-card" :class="`app-card--${variant}`"><slot /></VCard>
</template>

<style scoped>
.app-card { background: var(--sl-surface); }
.app-card--elevated { background: var(--sl-surface-soft); box-shadow: var(--sl-shadow-raised); }
.app-card--glass { background: var(--sl-glass-strong); border: 1px solid var(--sl-glass-border); box-shadow: var(--sl-shadow-overlay); backdrop-filter: blur(18px); }
</style>
