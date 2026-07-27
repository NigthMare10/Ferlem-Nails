<script setup lang="ts">
withDefaults(defineProps<{ modelValue: boolean; title?: string; maxWidth?: string | number }>(), { maxWidth: 680 });
defineEmits<{ 'update:modelValue': [value: boolean] }>();
</script>

<template>
    <VBottomSheet :model-value="modelValue" :max-width="maxWidth" @update:model-value="$emit('update:modelValue', $event)">
        <VCard class="bottom-sheet" rounded="t-lg">
            <VCardItem v-if="title" :title="title" class="pt-5" />
            <VCardText><slot /></VCardText>
            <VCardActions v-if="$slots.actions" class="px-6 pb-5"><slot name="actions" /></VCardActions>
        </VCard>
    </VBottomSheet>
</template>

<style scoped>
.bottom-sheet { background: var(--sl-glass-strong); border: 1px solid var(--sl-glass-border); box-shadow: var(--sl-shadow-overlay); padding-bottom: max(8px, env(safe-area-inset-bottom)); backdrop-filter: blur(20px); }
</style>
