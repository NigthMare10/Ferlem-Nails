<script setup lang="ts">
withDefaults(defineProps<{ lines?: number; heading?: boolean }>(), { lines: 3, heading: true });
</script>

<template>
    <div class="loading-skeleton" aria-busy="true" aria-label="Cargando contenido">
        <VSkeletonLoader v-if="heading" type="heading" class="mb-2" />
        <VSkeletonLoader v-for="line in lines" :key="line" type="text" class="mb-1" />
    </div>
</template>

<style scoped>
.loading-skeleton { max-width: 100%; padding: 4px 0; }
.loading-skeleton :deep(.v-skeleton-loader__bone) { background: color-mix(in oklch, var(--sl-surface-elevated), var(--sl-surface) 45%); border-radius: var(--sl-radius-control); }
</style>
