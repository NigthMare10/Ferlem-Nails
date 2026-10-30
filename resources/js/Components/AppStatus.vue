<script setup lang="ts">
withDefaults(defineProps<{
    tone?: 'success' | 'warning' | 'error' | 'neutral';
    icon?: string;
}>(), { tone: 'neutral' });
</script>

<template>
    <VChip class="app-status" :color="tone === 'neutral' ? 'on-surface-variant' : tone" size="small" variant="tonal" label>
        <VIcon v-if="icon" start :icon="icon" size="15" />
        <slot />
    </VChip>
</template>

<style scoped>
.app-status { min-height: 28px; padding-inline: 10px; font-size: var(--sl-label-size); font-weight: 700; letter-spacing: 0.02em; box-shadow: inset 1px 1px 2px var(--sl-status-inset); }
</style>
