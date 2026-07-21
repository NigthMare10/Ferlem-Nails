<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import EmptyState from '../../Components/EmptyState.vue';
</script>

<template>
    <Head title="Acceso denegado" />
    <AppLayout title="Acceso denegado">
        <VRow justify="center" align="center" class="forbidden-page">
            <VCol cols="12" md="8" lg="6">
                <VCard class="surface-card">
                    <EmptyState
                        icon="mdi-shield-lock-outline"
                        title="No tienes permiso para entrar"
                        description="Tu cuenta no está autorizada para consultar esta sección. Regresa a una pantalla disponible o solicita acceso a un responsable."
                    >
                        <VBtn color="primary" prepend-icon="mdi-arrow-left" @click="router.visit('/')">
                            Ir a mi pantalla inicial
                        </VBtn>
                    </EmptyState>
                </VCard>
            </VCol>
        </VRow>
    </AppLayout>
</template>

<style scoped>
.forbidden-page {
    min-height: calc(100dvh - 160px);
}
</style>
