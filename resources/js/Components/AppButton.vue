<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{
    variant?: 'primary' | 'secondary' | 'quiet' | 'danger';
    loading?: boolean;
    disabled?: boolean;
    block?: boolean;
    type?: 'button' | 'submit' | 'reset';
}>(), {
    variant: 'primary',
    type: 'button',
});

const color = computed(() => props.variant === 'danger' ? 'error' : props.variant === 'primary' ? 'primary' : undefined);
const vuetifyVariant = computed(() => props.variant === 'primary' || props.variant === 'danger' ? 'flat' : props.variant === 'secondary' ? 'tonal' : 'text');
</script>

<template>
    <VBtn
        class="app-button"
        :class="`app-button--${variant}`"
        :color="color"
        :variant="vuetifyVariant"
        :loading="loading"
        :disabled="disabled"
        :block="block"
        :type="type"
    >
        <slot />
    </VBtn>
</template>

<style scoped>
.app-button { min-height: 44px; letter-spacing: -0.01em; }
.app-button--primary { box-shadow: 0 6px 14px var(--sl-button-shadow), inset 0 1px 0 color-mix(in oklch, var(--sl-surface), transparent 72%); }
.app-button--secondary { color: var(--sl-primary-strong); background: var(--sl-surface-soft); box-shadow: var(--sl-shadow-raised); }
.app-button--secondary:active { box-shadow: var(--sl-shadow-inset); }
.app-button--quiet { color: var(--sl-primary-strong); }
</style>
