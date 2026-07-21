<script setup lang="ts">
defineProps<{ active: boolean }>();
</script>
<template>
    <VChip :color="active ? 'success' : 'on-surface-variant'" size="small" variant="tonal" label>
        <VIcon start :icon="active ? 'mdi-check-circle-outline' : 'mdi-minus-circle-outline'" size="15" />
        {{ active ? 'Activo' : 'Inactivo' }}
    </VChip>
</template>
