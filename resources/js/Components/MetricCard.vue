<script setup lang="ts">
import { router } from '@inertiajs/vue3';
defineProps<{ label: string; value: number; icon: string; href?: string }>();
</script>

<template>
    <VCard class="metric-card pa-1" :class="{ 'cursor-pointer': href }" @click="href && router.visit(href)">
        <VCardText class="d-flex align-center ga-4">
            <VAvatar color="secondary" variant="tonal" size="52">
                <VIcon :icon="icon" color="primary" size="26" />
            </VAvatar>
            <div>
                <div class="text-h4 font-weight-bold">{{ value }}</div>
                <div class="text-body-2 text-medium-emphasis">{{ label }}</div>
            </div>
            <VSpacer />
            <VIcon v-if="href" icon="mdi-chevron-right" color="on-surface-variant" />
        </VCardText>
    </VCard>
</template>
