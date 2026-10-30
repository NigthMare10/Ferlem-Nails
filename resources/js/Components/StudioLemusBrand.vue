<script setup lang="ts">
import logoDark from '../../images/studio-lemus-logo-dark.png';
import logoLight from '../../images/studio-lemus-logo-white.png';

withDefaults(defineProps<{
    variant?: 'full' | 'compact';
    tone?: 'light' | 'dark';
}>(), {
    variant: 'full',
    tone: 'dark',
});
</script>

<template>
    <div class="brand" :class="[`brand--${variant}`, `brand--${tone}`]" aria-label="Studio Lemus">
        <img class="brand__mark" :src="tone === 'light' ? logoLight : logoDark" alt="" />
        <div v-if="variant === 'full'" class="brand__copy">
            <strong>Studio Lemus</strong>
            <span>Atelier operativo</span>
        </div>
    </div>
</template>

<style scoped>
.brand {
    display: inline-flex;
    align-items: center;
    gap: 14px;
    color: rgb(var(--v-theme-on-surface));
}

.brand__mark {
    width: 64px;
    height: 64px;
    object-fit: contain;
    transform: scale(1.55);
}

.brand__copy {
    display: grid;
    line-height: 1.2;
}

.brand__copy strong {
    font-size: 1rem;
    letter-spacing: -0.02em;
}

.brand__copy span {
    margin-top: 3px;
    color: rgb(var(--v-theme-on-surface-variant));
    font-size: var(--sl-label-size);
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.brand--compact .brand__mark {
    width: 48px;
    height: 48px;
}

.brand--light {
    color: white;
}

.brand--light .brand__copy span {
    color: color-mix(in oklch, var(--sl-surface), transparent 28%);
}
</style>
