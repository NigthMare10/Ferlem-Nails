<script setup lang="ts">
withDefaults(defineProps<{
    modelValue: boolean;
    title: string;
    message: string;
    confirmText?: string;
    color?: string;
    loading?: boolean;
    icon?: string;
}>(), {
    confirmText: 'Confirmar',
    color: 'primary',
    icon: 'mdi-alert-circle-outline',
});
defineEmits(['update:modelValue', 'confirm']);
</script>

<template>
    <VDialog :model-value="modelValue" max-width="460" @update:model-value="$emit('update:modelValue', $event)">
        <VCard class="confirm-dialog pa-2">
            <VCardText class="pt-6 text-center">
                <VAvatar :color="color" variant="tonal" size="58" class="mb-4">
                    <VIcon :icon="icon" size="28" />
                </VAvatar>
                <h2 class="text-h6 font-weight-bold mb-2">{{ title }}</h2>
                <p class="text-body-2 text-medium-emphasis mb-0">{{ message }}</p>
            </VCardText>
            <VCardActions class="pa-4 pt-2">
                <VBtn class="flex-grow-1" variant="tonal" @click="$emit('update:modelValue', false)">Cancelar</VBtn>
                <VBtn class="flex-grow-1" :color="color" :loading="loading" :disabled="loading" @click="$emit('confirm')">
                    {{ confirmText }}
                </VBtn>
            </VCardActions>
        </VCard>
    </VDialog>
</template>

<style scoped>
.confirm-dialog { background: var(--sl-glass-strong); border: 1px solid var(--sl-glass-border); box-shadow: var(--sl-shadow-overlay); backdrop-filter: blur(20px); }
</style>
