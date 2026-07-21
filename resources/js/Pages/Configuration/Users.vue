<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import ConfigurationLayout from '../../Layouts/ConfigurationLayout.vue';
import ConfirmDialog from '../../Components/ConfirmDialog.vue';
import EmptyState from '../../Components/EmptyState.vue';
import PageHeader from '../../Components/PageHeader.vue';
import StatusChip from '../../Components/StatusChip.vue';
import UserForm from '../../Components/UserForm.vue';
import { usePermissions } from '../../composables/usePermissions';

type UserItem = {
    id: number;
    name: string;
    email: string;
    role: string;
    is_active: boolean;
    created_at?: string;
    last_login_at?: string;
};

const props = defineProps<{ users: any; filters: any; roles: string[] }>();
const page = usePage();
const { can } = usePermissions();
const data = computed<UserItem[]>(() => props.users.data ?? []);
const isOwner = computed(() => ((page.props.auth as any)?.roles ?? []).includes('owner'));
const currentUserId = computed(() => (page.props.auth as any)?.user?.id);
const dialog = ref(false);
const selected = ref<UserItem>();
const statusDialog = ref(false);
const passwordDialog = ref(false);
const loading = ref(false);
const actionLoading = ref(false);
const filters = ref({ search: props.filters.search ?? '', role: props.filters.role ?? null, status: props.filters.status ?? null });
const passwordForm = useForm({ password: '', password_confirmation: '' });
let searchTimer: ReturnType<typeof setTimeout>;

const roleNames: Record<string, string> = { owner: 'Propietario', administrator: 'Administrador', employee: 'Empleado' };
const roleItems = props.roles.map(role => ({ title: roleNames[role] ?? role, value: role }));
const statusItems = [{ title: 'Activo', value: '1' }, { title: 'Inactivo', value: '0' }];
const headers = [
    { title: 'Usuario', key: 'name' },
    { title: 'Rol', key: 'role' },
    { title: 'Estado', key: 'is_active' },
    { title: 'Último acceso', key: 'last_login_at' },
    { title: '', key: 'actions', sortable: false, align: 'end' as const },
];

const roleColor = (role: string) => role === 'owner' ? 'primary' : role === 'administrator' ? 'info' : 'on-surface-variant';
const formatDate = (value?: string) => value ? new Intl.DateTimeFormat('es-HN', { dateStyle: 'medium' }).format(new Date(value)) : 'Sin acceso';
const canManage = (user: UserItem) => isOwner.value || user.role !== 'owner';
const hasActions = (user: UserItem) => canManage(user) && (can('users.update') || can('users.reset_password') || (can('users.toggle_status') && user.id !== currentUserId.value));

const loadUsers = (extra: Record<string, unknown> = {}) => {
    loading.value = true;
    router.get('/configuration/users', { ...filters.value, ...extra }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => loading.value = false,
    });
};
const search = () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => loadUsers(), 350); };
const openCreate = () => { selected.value = undefined; dialog.value = true; };
const openEdit = (user: UserItem) => { selected.value = user; dialog.value = true; };
const openPassword = (user: UserItem) => { selected.value = user; passwordForm.reset(); passwordForm.clearErrors(); passwordDialog.value = true; };
const openStatus = (user: UserItem) => { selected.value = user; statusDialog.value = true; };
const updateStatus = () => {
    if (!selected.value) return;
    actionLoading.value = true;
    router.patch(`/configuration/users/${selected.value.id}/status`, { is_active: !selected.value.is_active }, {
        preserveScroll: true,
        onSuccess: () => statusDialog.value = false,
        onFinish: () => actionLoading.value = false,
    });
};
const resetPassword = () => {
    if (!selected.value || passwordForm.processing) return;
    passwordForm.patch(`/configuration/users/${selected.value.id}/password`, {
        preserveScroll: true,
        onSuccess: () => passwordDialog.value = false,
    });
};
</script>

<template>
    <Head title="Usuarios" />
    <ConfigurationLayout>
        <PageHeader title="Usuarios" description="Administra las personas que pueden acceder a Studio Lemus.">
            <template #actions>
                <VBtn v-if="can('users.create')" color="primary" prepend-icon="mdi-account-plus-outline" @click="openCreate">
                    Crear usuario
                </VBtn>
            </template>
        </PageHeader>

        <VCard class="surface-card">
            <VCardText class="pa-4 pa-sm-5">
                <VRow class="filter-bar pa-1" align="center">
                    <VCol cols="12" sm="6" lg="5">
                        <VTextField
                            v-model="filters.search"
                            label="Buscar por nombre o correo"
                            prepend-inner-icon="mdi-magnify"
                            clearable
                            hide-details
                            @update:model-value="search"
                        />
                    </VCol>
                    <VCol cols="6" sm="3">
                        <VSelect v-model="filters.role" label="Rol" :items="roleItems" clearable hide-details @update:model-value="loadUsers()" />
                    </VCol>
                    <VCol cols="6" sm="3">
                        <VSelect v-model="filters.status" label="Estado" :items="statusItems" clearable hide-details @update:model-value="loadUsers()" />
                    </VCol>
                </VRow>
            </VCardText>

            <VDataTable :headers="headers" :items="data" :loading="loading" class="desktop-table" hide-default-footer>
                <template #item.name="{ item }">
                    <div class="py-2">
                        <div class="font-weight-bold">{{ item.name }}</div>
                        <div class="text-caption text-medium-emphasis">{{ item.email }}</div>
                    </div>
                </template>
                <template #item.role="{ item }">
                    <VChip :color="roleColor(item.role)" variant="tonal" size="small">{{ roleNames[item.role] ?? item.role }}</VChip>
                </template>
                <template #item.is_active="{ item }"><StatusChip :active="item.is_active" /></template>
                <template #item.last_login_at="{ item }">{{ formatDate(item.last_login_at) }}</template>
                <template #item.actions="{ item }">
                    <VMenu v-if="hasActions(item)" location="bottom end">
                        <template #activator="{ props: menuProps }">
                            <VBtn v-bind="menuProps" icon="mdi-dots-vertical" variant="text" size="small" aria-label="Acciones del usuario" />
                        </template>
                        <VList density="comfortable" min-width="220">
                            <VListItem v-if="can('users.update')" prepend-icon="mdi-pencil-outline" title="Editar usuario" @click="openEdit(item)" />
                            <VListItem v-if="can('users.reset_password')" prepend-icon="mdi-lock-reset" title="Restablecer contraseña" @click="openPassword(item)" />
                            <VListItem
                                v-if="can('users.toggle_status') && item.id !== currentUserId"
                                :prepend-icon="item.is_active ? 'mdi-account-off-outline' : 'mdi-account-check-outline'"
                                :title="item.is_active ? 'Desactivar usuario' : 'Activar usuario'"
                                :base-color="item.is_active ? 'error' : 'success'"
                                @click="openStatus(item)"
                            />
                        </VList>
                    </VMenu>
                </template>
                <template #no-data>
                    <EmptyState icon="mdi-account-search-outline" title="No se encontraron usuarios" description="Ajusta la búsqueda o los filtros para ver otros resultados." />
                </template>
            </VDataTable>

            <div class="mobile-cards pa-4 pt-0">
                <template v-if="loading">
                    <VSkeletonLoader v-for="index in 3" :key="index" type="article, actions" class="mb-3" />
                </template>
                <EmptyState v-else-if="!data.length" icon="mdi-account-search-outline" title="No se encontraron usuarios" description="Ajusta la búsqueda o los filtros." />
                <VCard v-for="item in data" v-else :key="item.id" variant="outlined" class="mb-3 pa-1">
                    <VCardItem>
                        <template #prepend><VAvatar color="primary" variant="tonal"><VIcon icon="mdi-account-outline" /></VAvatar></template>
                        <VCardTitle class="text-body-1 font-weight-bold">{{ item.name }}</VCardTitle>
                        <VCardSubtitle>{{ item.email }}</VCardSubtitle>
                        <template #append>
                            <VMenu v-if="hasActions(item)" location="bottom end">
                                <template #activator="{ props: menuProps }"><VBtn v-bind="menuProps" icon="mdi-dots-vertical" variant="text" /></template>
                                <VList min-width="210">
                                    <VListItem v-if="can('users.update')" title="Editar" prepend-icon="mdi-pencil-outline" @click="openEdit(item)" />
                                    <VListItem v-if="can('users.reset_password')" title="Restablecer contraseña" prepend-icon="mdi-lock-reset" @click="openPassword(item)" />
                                    <VListItem v-if="can('users.toggle_status') && item.id !== currentUserId" :title="item.is_active ? 'Desactivar' : 'Activar'" prepend-icon="mdi-power" @click="openStatus(item)" />
                                </VList>
                            </VMenu>
                        </template>
                    </VCardItem>
                    <VCardText class="d-flex flex-wrap ga-2 pt-1">
                        <VChip :color="roleColor(item.role)" variant="tonal" size="small">{{ roleNames[item.role] ?? item.role }}</VChip>
                        <StatusChip :active="item.is_active" />
                    </VCardText>
                </VCard>
            </div>

            <VPagination
                v-if="users.meta?.last_page > 1"
                :model-value="users.meta.current_page"
                :length="users.meta.last_page"
                class="my-4"
                @update:model-value="loadUsers({ page: $event })"
            />
        </VCard>

        <UserForm v-model="dialog" :user="selected" :roles="roles" :can-assign-owner="can('users.assign_role') && isOwner" />
        <ConfirmDialog
            v-model="statusDialog"
            title="Cambiar estado del usuario"
            :message="`¿Deseas ${selected?.is_active ? 'desactivar' : 'activar'} a ${selected?.name}?`"
            :confirm-text="selected?.is_active ? 'Desactivar' : 'Activar'"
            :color="selected?.is_active ? 'error' : 'success'"
            :loading="actionLoading"
            @confirm="updateStatus"
        />

        <VDialog v-model="passwordDialog" max-width="480">
            <VCard class="pa-2">
                <VCardTitle class="px-5 pt-5 font-weight-bold">Restablecer contraseña</VCardTitle>
                <VCardText class="px-5 pt-3">
                    <p class="text-body-2 text-medium-emphasis mb-5">Define una nueva contraseña para {{ selected?.name }}.</p>
                    <VTextField v-model="passwordForm.password" label="Nueva contraseña" type="password" autocomplete="new-password" :error-messages="passwordForm.errors.password" />
                    <VTextField v-model="passwordForm.password_confirmation" label="Confirmar contraseña" type="password" autocomplete="new-password" />
                </VCardText>
                <VCardActions class="px-5 pb-5">
                    <VSpacer />
                    <VBtn variant="text" @click="passwordDialog = false">Cancelar</VBtn>
                    <VBtn color="primary" :loading="passwordForm.processing" :disabled="passwordForm.processing" @click="resetPassword">Guardar contraseña</VBtn>
                </VCardActions>
            </VCard>
        </VDialog>
    </ConfigurationLayout>
</template>
