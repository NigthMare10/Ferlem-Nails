<script setup lang="ts">
defineProps<{ title: string; description?: string; eyebrow?: string }>();
</script>

<template>
    <div class="d-flex flex-wrap ga-4 align-start justify-space-between mb-6">
        <div>
            <div v-if="eyebrow" class="text-overline text-primary font-weight-bold mb-1">{{ eyebrow }}</div>
            <h1 class="text-h4 font-weight-bold mb-2">{{ title }}</h1>
            <p v-if="description" class="text-body-1 text-medium-emphasis mb-0">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="d-flex flex-wrap ga-2 align-center">
            <slot name="actions" />
        </div>
    </div>
</template>
