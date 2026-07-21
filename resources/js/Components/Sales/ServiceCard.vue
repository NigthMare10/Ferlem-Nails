<script setup lang="ts">
import type { SaleService } from '../../types/sales';

defineProps<{
    service: SaleService;
    selectedQuantity: number;
}>();

defineEmits<{ add: [service: SaleService] }>();

const money = (value: string) => new Intl.NumberFormat('es-HN', {
    style: 'currency',
    currency: 'HNL',
}).format(Number(value));

const duration = (minutes: number) => {
    if (minutes < 60) return `${minutes} min`;
    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;
    return remainder ? `${hours} h ${remainder} min` : `${hours} h`;
};
</script>

<template>
    <VCard class="service-sale-card surface-card" height="100%">
        <VCardText class="pa-5 d-flex flex-column h-100">
            <div class="d-flex align-start justify-space-between ga-3 mb-3">
                <VAvatar color="primary" variant="tonal" size="42">
                    <VIcon icon="mdi-hand-heart-outline" size="22" />
                </VAvatar>
                <VChip size="small" variant="tonal" prepend-icon="mdi-clock-outline">
                    {{ duration(service.duration_minutes) }}
                </VChip>
            </div>
            <h2 class="text-body-1 font-weight-bold mb-2">{{ service.name }}</h2>
            <p class="service-sale-card__description text-body-2 text-medium-emphasis mb-5">
                {{ service.description || 'Servicio disponible en Studio Lemus.' }}
            </p>
            <div class="d-flex align-center justify-space-between ga-3 mt-auto">
                <span class="text-h6 font-weight-bold text-primary">{{ money(service.price) }}</span>
                <VBtn
                    color="primary"
                    variant="tonal"
                    prepend-icon="mdi-plus"
                    :disabled="selectedQuantity >= 50"
                    :aria-label="`Agregar ${service.name}`"
                    @click="$emit('add', service)"
                >
                    {{ selectedQuantity >= 50 ? 'Máximo' : 'Agregar' }}
                </VBtn>
            </div>
        </VCardText>
    </VCard>
</template>

<style scoped>
.service-sale-card {
    transition: transform 160ms ease, box-shadow 160ms ease;
}

.service-sale-card:hover {
    transform: translateY(-2px);
}

.service-sale-card__description {
    display: -webkit-box;
    min-height: 42px;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}
</style>
