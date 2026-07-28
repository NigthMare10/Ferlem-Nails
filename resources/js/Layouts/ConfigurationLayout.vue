<script setup lang="ts">
import AppLayout from './AppLayout.vue';
</script>

<template>
    <AppLayout title="Configuración">
        <main class="configuration-content">
            <slot />
        </main>
    </AppLayout>
</template>

<style scoped>
.configuration-content {
    min-width: 0;
}
</style>
