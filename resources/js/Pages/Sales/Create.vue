<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { useDisplay } from 'vuetify';
import AppLayout from '../../Layouts/AppLayout.vue';
import EmptyState from '../../Components/EmptyState.vue';
import PageHeader from '../../Components/PageHeader.vue';
import ConfirmSaleDialog from '../../Components/Sales/ConfirmSaleDialog.vue';
import SaleCart from '../../Components/Sales/SaleCart.vue';
import ServiceCard from '../../Components/Sales/ServiceCard.vue';
import type { PaymentMethod, SaleCartItem, SaleService } from '../../types/sales';

const props = defineProps<{ services: SaleService[] }>();
const { smAndDown } = useDisplay();
const search = ref('');
const cart = ref<SaleCartItem[]>([]);
const mobileCart = ref(false);
const confirmDialog = ref(false);
const form = useForm({
    checkout_token: crypto.randomUUID(),
    payment_method: 'cash' as PaymentMethod,
    items: [] as { service_id: number; quantity: number }[],
});

const cents = (value: string) => {
    const [whole, fraction = ''] = value.split('.');
    return (Number(whole) * 100) + Number(fraction.padEnd(2, '0').slice(0, 2));
};
const money = (value: number) => new Intl.NumberFormat('es-HN', {
    style: 'currency',
    currency: 'HNL',
}).format(value / 100);
const filteredServices = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('es');
    if (!term) return props.services;

    return props.services.filter(service =>
        service.name.toLocaleLowerCase('es').includes(term)
        || (service.description ?? '').toLocaleLowerCase('es').includes(term),
    );
});
const totalServices = computed(() => cart.value.reduce((total, item) => total + item.quantity, 0));
const totalCents = computed(() => cart.value.reduce((total, item) => total + (cents(item.price) * item.quantity), 0));
const cardFeeCents = computed(() => form.payment_method === 'card' ? Math.floor(((totalCents.value * 4) + 50) / 100) : 0);
const netAmountCents = computed(() => totalCents.value - cardFeeCents.value);
const saleError = computed(() => {
    const errors = form.errors as Record<string, string>;
    return errors.items
        || errors.checkout_token
        || errors.payment_method
        || Object.entries(errors).find(([key]) => key.startsWith('items.'))?.[1]
        || '';
});

const quantityFor = (serviceId: number) => cart.value.find(item => item.id === serviceId)?.quantity ?? 0;
const add = (service: SaleService) => {
    const existing = cart.value.find(item => item.id === service.id);
    if (existing) {
        if (existing.quantity < 50) existing.quantity++;
        return;
    }
    cart.value.push({ ...service, quantity: 1 });
};
const increase = (serviceId: number) => {
    const item = cart.value.find(entry => entry.id === serviceId);
    if (item && item.quantity < 50) item.quantity++;
};
const decrease = (serviceId: number) => {
    const item = cart.value.find(entry => entry.id === serviceId);
    if (!item) return;
    if (item.quantity === 1) {
        remove(serviceId);
        return;
    }
    item.quantity--;
};
const remove = (serviceId: number) => {
    cart.value = cart.value.filter(item => item.id !== serviceId);
};
const openConfirmation = () => {
    if (!cart.value.length || form.processing) return;
    form.clearErrors();
    mobileCart.value = false;
    confirmDialog.value = true;
};
const submit = () => {
    if (!cart.value.length || form.processing) return;
    form.items = cart.value.map(item => ({ service_id: item.id, quantity: item.quantity }));
    form.post('/sales', { preserveScroll: true });
};
</script>

<template>
    <Head title="Nueva venta" />
    <AppLayout title="Nueva venta">
        <div class="sales-create-page" :class="{ 'sales-create-page--mobile-cart': smAndDown && cart.length }">
            <PageHeader
                eyebrow="Venta rápida"
                title="Nueva venta"
                description="Selecciona los servicios realizados y genera el comprobante."
            />

            <VRow align="start">
                <VCol cols="12" md="7" lg="8">
                    <VTextField
                        v-model="search"
                        label="Buscar servicios"
                        prepend-inner-icon="mdi-magnify"
                        clearable
                        hide-details
                        class="mb-5"
                    />

                    <VRow v-if="filteredServices.length" dense>
                        <VCol v-for="service in filteredServices" :key="service.id" cols="12" sm="6" xl="4" class="pa-2">
                            <ServiceCard
                                :service="service"
                                :selected-quantity="quantityFor(service.id)"
                                @add="add"
                            />
                        </VCol>
                    </VRow>
                    <VCard v-else class="surface-card">
                        <EmptyState
                            icon="mdi-magnify-close"
                            title="No encontramos servicios"
                            description="Prueba con otro nombre o descripción."
                        />
                    </VCard>
                </VCol>

                <VCol v-if="!smAndDown" cols="12" md="5" lg="4">
                    <div class="sales-create-page__sticky-cart">
                        <SaleCart
                            :items="cart"
                            :total-cents="totalCents"
                            :total-services="totalServices"
                            :payment-method="form.payment_method"
                            :card-fee-cents="cardFeeCents"
                            :net-amount-cents="netAmountCents"
                            :processing="form.processing"
                            @increase="increase"
                            @decrease="decrease"
                            @remove="remove"
                            @payment-method="form.payment_method = $event"
                            @checkout="openConfirmation"
                        />
                    </div>
                </VCol>
            </VRow>

            <div v-if="smAndDown && cart.length" class="mobile-checkout-bar">
                <VBtn variant="text" class="mobile-checkout-bar__summary" @click="mobileCart = true">
                    <span class="text-left">
                        <strong>{{ totalServices }} {{ totalServices === 1 ? 'servicio' : 'servicios' }}</strong>
                        <small>Ver resumen</small>
                    </span>
                </VBtn>
                <VBtn color="primary" :loading="form.processing" :disabled="form.processing" @click="openConfirmation">
                    Cobrar {{ money(totalCents) }}
                </VBtn>
            </div>

            <VBottomSheet v-model="mobileCart" :disabled="form.processing" max-height="88dvh">
                <SaleCart
                    :items="cart"
                    :total-cents="totalCents"
                    :total-services="totalServices"
                    :payment-method="form.payment_method"
                    :card-fee-cents="cardFeeCents"
                    :net-amount-cents="netAmountCents"
                    :processing="form.processing"
                    @increase="increase"
                    @decrease="decrease"
                    @remove="remove"
                    @payment-method="form.payment_method = $event"
                    @checkout="openConfirmation"
                />
            </VBottomSheet>

            <ConfirmSaleDialog
                v-model="confirmDialog"
                :items="cart"
                :total-cents="totalCents"
                :total-services="totalServices"
                :payment-method="form.payment_method"
                :card-fee-cents="cardFeeCents"
                :net-amount-cents="netAmountCents"
                :processing="form.processing"
                :error="saleError"
                @confirm="submit"
            />
        </div>
    </AppLayout>
</template>

<style scoped>
.sales-create-page__sticky-cart {
    position: sticky;
    top: 88px;
}

.mobile-checkout-bar {
    position: fixed;
    z-index: 5;
    right: 0;
    bottom: 0;
    left: 0;
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 10px;
    align-items: center;
    padding: 12px 16px calc(12px + env(safe-area-inset-bottom));
    border-top: 1px solid rgba(var(--v-theme-on-surface), 0.1);
    background: rgb(var(--v-theme-surface));
    box-shadow: 0 -10px 35px rgba(55, 38, 44, 0.12);
}

.mobile-checkout-bar__summary {
    min-width: 0;
    justify-content: flex-start;
}

.mobile-checkout-bar__summary span {
    display: grid;
    min-width: 0;
}

.mobile-checkout-bar__summary small {
    color: rgb(var(--v-theme-on-surface-variant));
}

.sales-create-page--mobile-cart {
    padding-bottom: 82px;
}

@media (max-width: 420px) {
    .mobile-checkout-bar {
        grid-template-columns: 1fr;
    }

    .mobile-checkout-bar__summary {
        display: none;
    }

    .sales-create-page--mobile-cart {
        padding-bottom: 70px;
    }
}
</style>
