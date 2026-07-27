<script setup lang="ts">
import { router } from '@inertiajs/vue3';
defineProps<{ label: string; value: number; icon: string; href?: string }>();
</script>

<template>
    <VBtn v-if="href" class="metric-card metric-card--interactive pa-1 text-none" variant="text" :aria-label="`Ver ${label}`" @click="router.visit(href)">
        <VCardText class="d-flex align-center ga-4">
            <VAvatar color="secondary" variant="tonal" size="52" aria-hidden="true">
                <VIcon :icon="icon" color="primary" size="26" />
            </VAvatar>
            <div>
                <div class="text-h4 font-weight-bold">{{ value }}</div>
                <div class="text-body-2 text-medium-emphasis">{{ label }}</div>
            </div>
            <VSpacer />
            <VIcon v-if="href" icon="mdi-chevron-right" color="on-surface-variant" />
        </VCardText>
    </VBtn>
    <VCard v-else class="metric-card pa-1">
        <VCardText class="d-flex align-center ga-4">
            <VAvatar color="secondary" variant="tonal" size="52" aria-hidden="true"><VIcon :icon="icon" color="primary" size="26" /></VAvatar>
            <div><div class="text-h4 font-weight-bold">{{ value }}</div><div class="text-body-2 text-medium-emphasis">{{ label }}</div></div>
        </VCardText>
    </VCard>
</template>

<style scoped>
.metric-card { height: 100%; border: 1px solid var(--sl-border); border-radius: var(--sl-radius-surface); background: var(--sl-surface); box-shadow: var(--sl-shadow-raised); }
.metric-card--interactive { justify-content: flex-start; transition: box-shadow var(--sl-duration-fast) var(--sl-ease), transform var(--sl-duration-fast) var(--sl-ease); }
@media (hover: hover) and (pointer: fine) { .metric-card--interactive:hover { box-shadow: var(--sl-shadow-raised); transform: translateY(-1px); } }
</style>
