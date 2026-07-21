<script setup lang="ts">
withDefaults(defineProps<{ icon?: string; title: string; description?: string }>(), {
    icon: 'mdi-inbox-outline',
});
</script>

<template>
    <div class="text-center px-5 py-10">
        <VAvatar color="surface-variant" size="64" class="mb-4">
            <VIcon :icon="icon" color="primary" size="30" />
        </VAvatar>
        <h3 class="text-h6 font-weight-bold mb-2">{{ title }}</h3>
        <p v-if="description" class="text-body-2 text-medium-emphasis mx-auto mb-4" style="max-width: 420px">
            {{ description }}
        </p>
        <slot />
    </div>
</template>
