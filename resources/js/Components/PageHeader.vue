<script setup lang="ts">
defineProps<{ title: string; description?: string; eyebrow?: string }>();
</script>

<template>
    <header class="d-flex flex-wrap ga-4 align-start justify-space-between mb-6">
        <div class="page-header__copy">
            <div v-if="eyebrow" class="page-header__eyebrow mb-2">{{ eyebrow }}</div>
            <h1 class="page-header__title mb-2">{{ title }}</h1>
            <p v-if="description" class="text-body-1 text-medium-emphasis mb-0">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="d-flex flex-wrap ga-2 align-center">
            <slot name="actions" />
        </div>
    </header>
</template>

<style scoped>
.page-header__copy { max-width: 68ch; }
.page-header__eyebrow { color: var(--sl-primary); font-size: var(--sl-label-size); font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; }
.page-header__title { color: var(--sl-text); font-size: var(--sl-heading-size); font-weight: 650; letter-spacing: -0.04em; line-height: 1.06; }
</style>
