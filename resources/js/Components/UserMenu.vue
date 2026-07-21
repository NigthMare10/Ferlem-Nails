<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps<{ user: { name: string; email: string }; role?: string }>();
const roleNames: Record<string, string> = {
    owner: 'Propietario',
    administrator: 'Administrador',
    employee: 'Empleado',
};
const roleLabel = computed(() => roleNames[props.role ?? ''] ?? 'Usuario');
const initials = computed(() => props.user.name.split(' ').slice(0, 2).map(part => part[0]).join('').toUpperCase());
const logout = () => router.post('/logout');
</script>

<template>
    <VMenu location="bottom end">
        <template #activator="{ props: activatorProps }">
            <VBtn v-bind="activatorProps" variant="text" class="text-none px-2" height="52">
                <VAvatar color="primary" size="36" class="mr-sm-3">{{ initials }}</VAvatar>
                <span class="d-none d-sm-grid text-left">
                    <strong class="text-body-2">{{ user.name }}</strong>
                    <small class="text-medium-emphasis">{{ roleLabel }}</small>
                </span>
                <VIcon icon="mdi-chevron-down" size="18" class="ml-1 d-none d-sm-block" />
            </VBtn>
        </template>
        <VList min-width="250" class="pa-2">
            <VListItem :title="user.name" :subtitle="user.email" class="mb-1" />
            <VDivider class="mb-1" />
            <VListItem prepend-icon="mdi-logout" title="Cerrar sesión" @click="logout" />
        </VList>
    </VMenu>
</template>
