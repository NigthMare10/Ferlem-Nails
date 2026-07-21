<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import ConfigurationLayout from '../../Layouts/ConfigurationLayout.vue';
import ConfirmDialog from '../../Components/ConfirmDialog.vue';
import EmptyState from '../../Components/EmptyState.vue';
import PageHeader from '../../Components/PageHeader.vue';
import ServiceForm from '../../Components/ServiceForm.vue';
import StatusChip from '../../Components/StatusChip.vue';
import { usePermissions } from '../../composables/usePermissions';

type ServiceItem = {
    id: number;
    name: string;
    description?: string;
    duration_minutes: number;
    price: string;
    is_active: boolean;
};

const props = defineProps<{ services: any; filters: any }>();
const { can } = usePermissions();
const data = computed<ServiceItem[]>(() => props.services.data ?? []);
const dialog = ref(false);
const selected = ref<ServiceItem>();
const statusDialog = ref(false);
const deleteDialog = ref(false);
const loading = ref(false);
const actionLoading = ref(false);
const filters = ref({ search: props.filters.search ?? '', status: props.filters.status ?? null });
const statusItems = [{ title: 'Activo', value: '1' }, { title: 'Inactivo', value: '0' }];
const headers = [
    { title: 'Servicio', key: 'name' },
    { title: 'Duración', key: 'duration_minutes' },
    { title: 'Precio', key: 'price' },
    { title: 'Estado', key: 'is_active' },
    { title: '', key: 'actions', sortable: false, align: 'end' as const },
];
let searchTimer: ReturnType<typeof setTimeout>;

const money = (value: string) => new Intl.NumberFormat('es-HN', { style: 'currency', currency: 'HNL' }).format(Number(value));
const duration = (minutes: number) => {
    if (minutes < 60) return `${minutes} min`;
    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;
    return remainder ? `${hours} h ${remainder} min` : `${hours} h`;
};
const hasActions = computed(() => can('services.update') || can('services.toggle_status') || can('services.delete'));
const loadServices = (extra: Record<string, unknown> = {}) => {
    loading.value = true;
    router.get('/configuration/services', { ...filters.value, ...extra }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => loading.value = false,
    });
};
const search = () => { clearTimeout(searchTimer); searchTimer = setTimeout(() => loadServices(), 350); };
const openCreate = () => { selected.value = undefined; dialog.value = true; };
const openEdit = (service: ServiceItem) => { selected.value = service; dialog.value = true; };
const openStatus = (service: ServiceItem) => { selected.value = service; statusDialog.value = true; };
const openDelete = (service: ServiceItem) => { selected.value = service; deleteDialog.value = true; };
const updateStatus = () => {
    if (!selected.value) return;
    actionLoading.value = true;
    router.patch(`/configuration/services/${selected.value.id}/status`, { is_active: !selected.value.is_active }, {
        preserveScroll: true,
        onSuccess: () => statusDialog.value = false,
        onFinish: () => actionLoading.value = false,
    });
};
const remove = () => {
    if (!selected.value) return;
    actionLoading.value = true;
    router.delete(`/configuration/services/${selected.value.id}`, {
        preserveScroll: true,
        onSuccess: () => deleteDialog.value = false,
        onFinish: () => actionLoading.value = false,
    });
};
</script>

<template>
    <Head title="Servicios" />
    <ConfigurationLayout>
        <PageHeader title="Servicios" description="Administra los servicios disponibles en Studio Lemus.">
            <template #actions>
                <VBtn v-if="can('services.create')" color="primary" prepend-icon="mdi-plus" @click="openCreate">Crear servicio</VBtn>
            </template>
        </PageHeader>

        <VCard class="surface-card">
            <VCardText class="pa-4 pa-sm-5">
                <VRow class="filter-bar pa-1" align="center">
                    <VCol cols="12" sm="7">
                        <VTextField v-model="filters.search" label="Buscar servicio" prepend-inner-icon="mdi-magnify" clearable hide-details @update:model-value="search" />
                    </VCol>
                    <VCol cols="12" sm="5" lg="3">
                        <VSelect v-model="filters.status" label="Estado" :items="statusItems" clearable hide-details @update:model-value="loadServices()" />
                    </VCol>
                </VRow>
            </VCardText>

            <VDataTable :headers="headers" :items="data" :loading="loading" class="desktop-table" hide-default-footer>
                <template #item.name="{ item }">
                    <div class="py-2" style="max-width: 360px">
                        <div class="font-weight-bold">{{ item.name }}</div>
                        <div class="text-caption text-medium-emphasis text-truncate">{{ item.description || 'Sin descripción' }}</div>
                    </div>
                </template>
                <template #item.duration_minutes="{ item }">{{ duration(item.duration_minutes) }}</template>
                <template #item.price="{ item }"><span class="font-weight-bold">{{ money(item.price) }}</span></template>
                <template #item.is_active="{ item }"><StatusChip :active="item.is_active" /></template>
                <template #item.actions="{ item }">
                    <VMenu v-if="hasActions" location="bottom end">
                        <template #activator="{ props: menuProps }">
                            <VBtn v-bind="menuProps" icon="mdi-dots-vertical" variant="text" size="small" aria-label="Acciones del servicio" />
                        </template>
                        <VList min-width="210">
                            <VListItem v-if="can('services.update')" prepend-icon="mdi-pencil-outline" title="Editar servicio" @click="openEdit(item)" />
                            <VListItem v-if="can('services.toggle_status')" prepend-icon="mdi-power" :title="item.is_active ? 'Desactivar servicio' : 'Activar servicio'" @click="openStatus(item)" />
                            <VListItem v-if="can('services.delete')" prepend-icon="mdi-delete-outline" title="Eliminar servicio" base-color="error" @click="openDelete(item)" />
                        </VList>
                    </VMenu>
                </template>
                <template #no-data>
                    <EmptyState icon="mdi-hand-heart-outline" title="No se encontraron servicios" description="Crea un servicio o ajusta los filtros para ver otros resultados." />
                </template>
            </VDataTable>

            <div class="mobile-cards pa-4 pt-0">
                <template v-if="loading">
                    <VSkeletonLoader v-for="index in 3" :key="index" type="article, actions" class="mb-3" />
                </template>
                <EmptyState v-else-if="!data.length" icon="mdi-hand-heart-outline" title="No se encontraron servicios" description="Crea un servicio o ajusta los filtros." />
                <VCard v-for="item in data" v-else :key="item.id" variant="outlined" class="mb-3 pa-1">
                    <VCardItem>
                        <VCardTitle class="text-body-1 font-weight-bold">{{ item.name }}</VCardTitle>
                        <VCardSubtitle>{{ duration(item.duration_minutes) }} · {{ money(item.price) }}</VCardSubtitle>
                        <template #append>
                            <VMenu v-if="hasActions" location="bottom end">
                                <template #activator="{ props: menuProps }"><VBtn v-bind="menuProps" icon="mdi-dots-vertical" variant="text" /></template>
                                <VList min-width="200">
                                    <VListItem v-if="can('services.update')" title="Editar" prepend-icon="mdi-pencil-outline" @click="openEdit(item)" />
                                    <VListItem v-if="can('services.toggle_status')" :title="item.is_active ? 'Desactivar' : 'Activar'" prepend-icon="mdi-power" @click="openStatus(item)" />
                                    <VListItem v-if="can('services.delete')" title="Eliminar" prepend-icon="mdi-delete-outline" base-color="error" @click="openDelete(item)" />
                                </VList>
                            </VMenu>
                        </template>
                    </VCardItem>
                    <VCardText class="pt-1">
                        <p class="text-body-2 text-medium-emphasis mb-3">{{ item.description || 'Sin descripción' }}</p>
                        <StatusChip :active="item.is_active" />
                    </VCardText>
                </VCard>
            </div>

            <VPagination
                v-if="services.meta?.last_page > 1"
                :model-value="services.meta.current_page"
                :length="services.meta.last_page"
                class="my-4"
                @update:model-value="loadServices({ page: $event })"
            />
        </VCard>

        <ServiceForm v-model="dialog" :service="selected" />
        <ConfirmDialog
            v-model="statusDialog"
            title="Cambiar estado del servicio"
            :message="`¿Deseas ${selected?.is_active ? 'desactivar' : 'activar'} ${selected?.name}?`"
            :confirm-text="selected?.is_active ? 'Desactivar' : 'Activar'"
            :color="selected?.is_active ? 'warning' : 'success'"
            :loading="actionLoading"
            @confirm="updateStatus"
        />
        <ConfirmDialog
            v-model="deleteDialog"
            title="Eliminar servicio"
            :message="`¿Seguro que deseas eliminar ${selected?.name}? Esta acción no se puede deshacer.`"
            confirm-text="Eliminar"
            color="error"
            icon="mdi-delete-alert-outline"
            :loading="actionLoading"
            @confirm="remove"
        />
    </ConfigurationLayout>
</template>
