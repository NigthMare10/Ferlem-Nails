<script setup lang="ts">
withDefaults(defineProps<{ icon?: string; title: string; description?: string }>(), {
    icon: 'mdi-inbox-outline',
});
</script>

<template>
    <div class="text-center px-5 py-10">
        <VAvatar color="surface-variant" size="64" class="empty-state__mark mb-4" aria-hidden="true">
            <VIcon :icon="icon" color="primary" size="30" />
        </VAvatar>
        <h3 class="text-h6 font-weight-bold mb-2">{{ title }}</h3>
        <p v-if="description" class="empty-state__description text-body-2 text-medium-emphasis mx-auto mb-4">
            {{ description }}
        </p>
        <slot />
    </div>
</template>

<style scoped>
.empty-state__description { max-width: 420px; }
.empty-state__mark { box-shadow: var(--sl-shadow-raised); }
</style>
