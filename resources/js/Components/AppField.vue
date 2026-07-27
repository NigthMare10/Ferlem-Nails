<script setup lang="ts">
withDefaults(defineProps<{
    modelValue?: string | number | null;
    label: string;
    type?: string;
    hint?: string;
    errorMessages?: string | string[];
    disabled?: boolean;
    required?: boolean;
}>(), { type: 'text' });

defineEmits<{ 'update:modelValue': [value: string] }>();
</script>

<template>
    <VTextField
        class="app-field"
        :model-value="modelValue"
        :label="label"
        :type="type"
        :hint="hint"
        :error-messages="errorMessages"
        :disabled="disabled"
        :required="required"
        persistent-hint
        @update:model-value="$emit('update:modelValue', $event)"
    />
</template>

<style scoped>
.app-field :deep(.v-field) { min-height: 44px; background: var(--sl-surface-soft); border-radius: var(--sl-radius-control); box-shadow: inset 1px 1px 3px var(--sl-field-inset), inset -1px -1px 2px color-mix(in oklch, var(--sl-surface), transparent 20%); }
</style>
