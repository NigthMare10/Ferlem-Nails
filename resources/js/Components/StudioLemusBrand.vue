<script setup lang="ts">
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
        <div class="brand__monogram" aria-hidden="true">SL</div>
        <div v-if="variant === 'full'" class="brand__copy">
            <strong>Studio Lemus</strong>
            <span>Administración</span>
        </div>
    </div>
</template>

<style scoped>
.brand {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    color: rgb(var(--v-theme-on-surface));
}

.brand__monogram {
    display: grid;
    width: 42px;
    height: 42px;
    place-items: center;
    border-radius: 14px;
    color: white;
    background: rgb(var(--v-theme-primary));
    font-size: 0.85rem;
    font-weight: 800;
    letter-spacing: 0.12em;
}

.brand__copy {
    display: grid;
    line-height: 1.2;
}

.brand__copy strong {
    font-size: 1rem;
    letter-spacing: -0.01em;
}

.brand__copy span {
    margin-top: 3px;
    color: rgb(var(--v-theme-on-surface-variant));
    font-size: 0.72rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.brand--compact .brand__monogram {
    width: 38px;
    height: 38px;
    border-radius: 12px;
}

.brand--light {
    color: white;
}

.brand--light .brand__monogram {
    color: rgb(var(--v-theme-primary-darken-1));
    background: white;
}

.brand--light .brand__copy span {
    color: rgba(255, 255, 255, 0.72);
}
</style>
