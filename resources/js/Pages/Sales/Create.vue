<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { useDisplay } from 'vuetify';
import AppLayout from '../../Layouts/AppLayout.vue';
import EmptyState from '../../Components/EmptyState.vue';
import PageHeader from '../../Components/PageHeader.vue';
import ConfirmSaleDialog from '../../Components/Sales/ConfirmSaleDialog.vue';
import SaleCart from '../../Components/Sales/SaleCart.vue';
import SaleCheckoutSummary from '../../Components/Sales/SaleCheckoutSummary.vue';
import SaleLineItem from '../../Components/Sales/SaleLineItem.vue';
import SalePaymentMethod from '../../Components/Sales/SalePaymentMethod.vue';
import ServiceCard from '../../Components/Sales/ServiceCard.vue';
import type { AppointmentCheckoutContext, AppointmentSaleCartItem, PaymentMethod, SaleCartItem, SaleService } from '../../types/sales';
import { centsToDecimal, decimalToCents, formatHnl, percentageOfCents } from '../../utils/money';

const props = defineProps<{
    services: SaleService[];
    appointment: AppointmentCheckoutContext | null;
    assignees: Array<{ id: number; name: string }>;
}>();
const page = usePage<{ auth: { user: { id: number; name: string } } }>();
const { smAndDown } = useDisplay();
const search = ref('');
const cart = ref<SaleCartItem[]>([]);
const mobileCart = ref(false);
const confirmDialog = ref(false);
const appointmentCart = ref<AppointmentSaleCartItem[]>((props.appointment?.items ?? []).map(item => ({
    key: `reserved-${item.appointment_item_id}`,
    appointment_item_id: item.appointment_item_id,
    service_id: item.service_id,
    name: item.name,
    description: item.description,
    duration_minutes: item.duration_minutes,
    price: item.price,
    quantity: item.quantity,
    performed_by: item.performed_by.id,
    performer_name: item.performed_by.name,
    reserved: true,
})));
const removedReserved = ref<number[]>([]);
const removalPending = ref<string | null>(null);
let additionalSequence = 0;
const form = useForm({
    checkout_token: crypto.randomUUID(),
    payment_method: 'cash' as PaymentMethod,
    items: [] as Array<{ service_id: number | null; appointment_item_id?: number | null; quantity: number; performed_by?: number }>,
    removed_appointment_item_ids: [] as number[],
});
const excessRefundForm = useForm({
    amount: '',
    operation_token: crypto.randomUUID(),
    note: '',
});
const excessRefundErrors = computed(() => excessRefundForm.errors as Record<string, string | undefined>);

const filteredServices = computed(() => {
    const term = search.value.trim().toLocaleLowerCase('es');
    if (!term) return props.services;

    return props.services.filter(service =>
        service.name.toLocaleLowerCase('es').includes(term)
        || (service.description ?? '').toLocaleLowerCase('es').includes(term),
    );
});
const totalServices = computed(() => cart.value.reduce((total, item) => total + item.quantity, 0));
const totalCents = computed(() => cart.value.reduce((total, item) => total + (decimalToCents(item.price) * item.quantity), 0));
const appointmentTotalServices = computed(() => appointmentCart.value.reduce((total, item) => total + item.quantity, 0));
const appointmentTotalCents = computed(() => appointmentCart.value.reduce((total, item) => total + (decimalToCents(item.price) * item.quantity), 0));
const depositCents = computed(() => props.appointment?.deposit ? decimalToCents(props.appointment.deposit.available_amount) : 0);
const depositFeeCents = computed(() => props.appointment?.deposit ? decimalToCents(props.appointment.deposit.card_fee_amount) : 0);
const appointmentBalanceCents = computed(() => Math.max(0, appointmentTotalCents.value - depositCents.value));
const appointmentBelowDeposit = computed(() => appointmentTotalCents.value < depositCents.value);
const depositExcessCents = computed(() => Math.max(0, depositCents.value - appointmentTotalCents.value));
const cardFeeCents = computed(() => form.payment_method === 'card' ? percentageOfCents(totalCents.value, 4) : 0);
const netAmountCents = computed(() => totalCents.value - cardFeeCents.value);
const appointmentBalanceFeeCents = computed(() => form.payment_method === 'card' ? percentageOfCents(appointmentBalanceCents.value, 4) : 0);
const appointmentTotalFeeCents = computed(() => depositFeeCents.value + appointmentBalanceFeeCents.value);
const appointmentNetAmountCents = computed(() => appointmentTotalCents.value - appointmentTotalFeeCents.value);
const saleError = computed(() => {
    const errors = form.errors as Record<string, string>;
    return errors.items
        || errors.checkout_token
        || errors.payment_method
        || Object.entries(errors).find(([key]) => key.startsWith('items.'))?.[1]
        || '';
});
const confirmationItems = computed<SaleCartItem[]>(() => (props.appointment
    ? appointmentCart.value.map((item, index) => ({
        id: index + 1,
        name: item.name,
        description: item.description,
        duration_minutes: item.duration_minutes,
        price: item.price,
        quantity: item.quantity,
    }))
    : cart.value));

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
    if (props.appointment) {
        if (!appointmentCart.value.length || appointmentBelowDeposit.value || form.processing) return;
        form.items = appointmentCart.value.map(item => ({
            appointment_item_id: item.appointment_item_id,
            service_id: item.service_id,
            quantity: item.quantity,
            performed_by: item.performed_by,
        }));
        form.removed_appointment_item_ids = removedReserved.value;
        form.post(`/appointments/${props.appointment.id}/checkout`, { preserveScroll: true });
        return;
    }
    if (!cart.value.length || form.processing) return;
    form.items = cart.value.map(item => ({ service_id: item.id, quantity: item.quantity }));
    form.post('/sales', { preserveScroll: true });
};

function addAppointmentService(service: SaleService): void {
    const existing = appointmentCart.value.find(item => !item.reserved && item.service_id === service.id);
    if (existing) {
        if (existing.quantity < 50) existing.quantity++;
        return;
    }
    const defaultPerformer = props.appointment?.can_assign
        ? props.assignees[0]
        : page.props.auth.user;
    if (!defaultPerformer) return;
    appointmentCart.value.push({
        key: `additional-${++additionalSequence}`,
        appointment_item_id: null,
        service_id: service.id,
        name: service.name,
        description: service.description,
        duration_minutes: service.duration_minutes,
        price: service.price,
        quantity: 1,
        performed_by: defaultPerformer.id,
        performer_name: defaultPerformer.name,
        reserved: false,
    });
}

function removeAppointmentLine(item: AppointmentSaleCartItem): void {
    if (item.reserved && removalPending.value !== item.key) {
        removalPending.value = item.key;
        return;
    }
    if (item.appointment_item_id) removedReserved.value.push(item.appointment_item_id);
    appointmentCart.value = appointmentCart.value.filter(line => line.key !== item.key);
    removalPending.value = null;
}

function updateAppointmentPerformer(item: AppointmentSaleCartItem, performerId: number): void {
    item.performed_by = performerId;
    item.performer_name = props.assignees.find(person => person.id === performerId)?.name ?? '';
}

function openAppointmentConfirmation(): void {
    if (!appointmentCart.value.length || appointmentBelowDeposit.value || form.processing) return;
    form.clearErrors();
    confirmDialog.value = true;
}

function refundExcess(): void {
    if (!props.appointment || depositExcessCents.value <= 0 || excessRefundForm.processing) return;
    excessRefundForm.amount = centsToDecimal(depositExcessCents.value);
    excessRefundForm.post(`/appointments/${props.appointment.id}/deposit/refund-excess`, {
        preserveScroll: true,
        onSuccess: () => {
            excessRefundForm.defaults({ amount: '', operation_token: crypto.randomUUID(), note: '' }).reset().clearErrors();
        },
    });
}
</script>

<template>
    <Head title="Nueva venta" />
    <AppLayout title="Nueva venta">
        <div class="sales-create-page" :class="{ 'sales-create-page--mobile-cart': smAndDown && cart.length }">
            <PageHeader
                :eyebrow="appointment ? 'Cita programada' : 'Venta rápida'"
                :title="appointment ? 'Atender y cobrar' : 'Nueva venta'"
                :description="appointment ? `Registra el trabajo realizado para ${appointment.client_name}.` : 'Selecciona los servicios realizados y genera el comprobante.'"
            />

            <template v-if="appointment">
                <VAlert type="info" variant="tonal" class="mb-5">
                    Abrir esta pantalla no completa la cita. Permanecerá programada hasta confirmar el cobro.
                </VAlert>
                <VRow align="start">
                    <VCol cols="12" lg="7">
                        <VCard class="surface-card mb-5" rounded="xl">
                            <VCardItem class="pa-5">
                                <VCardTitle class="font-weight-bold">{{ appointment.client_name }}</VCardTitle>
                                <VCardSubtitle>{{ appointment.client_phone || 'Sin teléfono' }} · {{ appointment.reserved_duration_minutes }} min reservados</VCardSubtitle>
                            </VCardItem>
                            <VDivider />
                            <VCardText class="pa-4 pa-sm-5">
                                <div class="text-overline text-primary mb-3">Trabajo realizado</div>
                                <SaleLineItem v-for="item in appointmentCart" :key="item.key" :item-key="item.key" :name="item.name" :price="item.price" :quantity="item.quantity" :duration-minutes="item.duration_minutes" :reserved="item.reserved" :processing="form.processing" @increase="item.quantity++" @decrease="item.quantity > 1 ? item.quantity-- : removeAppointmentLine(item)" @remove="removeAppointmentLine(item)">
                                    <template #notice>
                                        <VAlert v-if="removalPending === item.key" type="warning" variant="tonal" density="compact" class="mt-3">
                                            Confirma que este servicio reservado no se realizó.
                                            <template #append><div class="d-flex ga-1"><VBtn size="small" variant="text" @click="removalPending = null">Conservar</VBtn><VBtn size="small" color="error" variant="text" @click="removeAppointmentLine(item)">Quitar</VBtn></div></template>
                                        </VAlert>
                                    </template>
                                    <template #extra><VSelect v-if="appointment.can_assign" :model-value="item.performed_by" label="Realizado por" :items="assignees" item-title="name" item-value="id" density="compact" hide-details :disabled="form.processing" @update:model-value="updateAppointmentPerformer(item, $event)" /><div v-else class="text-body-2"><span class="text-medium-emphasis">Realizado por </span><strong>{{ item.performer_name }}</strong></div></template>
                                </SaleLineItem>
                                <VAlert v-if="!appointmentCart.length" type="warning" variant="tonal">Agrega al menos un servicio realizado.</VAlert>
                            </VCardText>
                        </VCard>

                        <VTextField v-model="search" label="Buscar servicios adicionales" prepend-inner-icon="mdi-magnify" clearable hide-details class="mb-4" />
                        <VRow dense><VCol v-for="service in filteredServices" :key="service.id" cols="12" sm="6"><ServiceCard :service="service" :selected-quantity="appointmentCart.find(item => !item.reserved && item.service_id === service.id)?.quantity ?? 0" @add="addAppointmentService" /></VCol></VRow>
                    </VCol>
                    <VCol cols="12" lg="5">
                        <VCard class="surface-card appointment-summary" rounded="xl">
                            <VCardItem class="pa-5"><VCardTitle class="font-weight-bold">Resumen de cobro</VCardTitle><VCardSubtitle>La venta conserva el valor completo del trabajo.</VCardSubtitle></VCardItem>
                            <VDivider />
                            <VCardText class="pa-5">
                                <SalePaymentMethod v-model="form.payment_method" :amount-cents="appointmentBalanceCents" :processing="form.processing" :balance-payment="depositCents > 0" class="mb-4" />
                                <SaleCheckoutSummary :total-cents="appointmentTotalCents" :total-services="appointmentTotalServices" :payment-method="form.payment_method" :deposit-cents="depositCents" :deposit-fee-cents="depositFeeCents" :balance-cents="appointmentBalanceCents" :balance-fee-cents="appointmentBalanceFeeCents" :total-fee-cents="appointmentTotalFeeCents" :net-amount-cents="appointmentNetAmountCents" />
                                <VAlert v-if="appointmentBelowDeposit" type="error" variant="tonal" density="compact" class="mt-4">
                                    El adelanto disponible supera los servicios por {{ formatHnl(depositExcessCents) }}. Debe devolverse exactamente ese excedente antes de completar.
                                </VAlert>
                                <VCard v-if="appointmentBelowDeposit" variant="outlined" rounded="lg" class="pa-4 mt-3">
                                    <template v-if="appointment.can_resolve_deposit">
                                        <div class="font-weight-bold mb-1">Devolver excedente</div>
                                        <div class="text-body-2 text-medium-emphasis mb-3">Se devolverán {{ formatHnl(depositExcessCents) }}. El monto y la comisión originales del adelanto no cambiarán.</div>
                                        <VTextarea v-model="excessRefundForm.note" label="Nota (opcional)" rows="2" counter="500" :disabled="excessRefundForm.processing" :error-messages="excessRefundForm.errors.note" />
                                        <VAlert v-if="excessRefundErrors.amount || excessRefundErrors.operation_token || excessRefundErrors.deposit || excessRefundErrors.appointment" type="error" variant="tonal" density="compact" class="mb-3">{{ excessRefundErrors.amount || excessRefundErrors.operation_token || excessRefundErrors.deposit || excessRefundErrors.appointment }}</VAlert>
                                        <VBtn block color="error" variant="tonal" prepend-icon="mdi-cash-refund" :loading="excessRefundForm.processing" :disabled="form.processing" @click="refundExcess">Devolver excedente de {{ formatHnl(depositExcessCents) }}</VBtn>
                                    </template>
                                    <VAlert v-else type="warning" variant="tonal" density="compact">Solicita a una persona responsable con permiso para resolver adelantos que devuelva el excedente.</VAlert>
                                </VCard>
                                <VAlert v-if="saleError" type="error" variant="tonal" density="compact" class="mt-4">{{ saleError }}</VAlert>
                                <VBtn block color="primary" size="large" prepend-icon="mdi-check-circle-outline" class="mt-5" :loading="form.processing" :disabled="form.processing || !appointmentCart.length || appointmentBelowDeposit" @click="openAppointmentConfirmation">Completar y cobrar {{ formatHnl(appointmentBalanceCents) }}</VBtn>
                            </VCardText>
                        </VCard>
                    </VCol>
                </VRow>
            </template>

            <VRow v-else align="start">
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

            <div v-if="!appointment && smAndDown && cart.length" class="mobile-checkout-bar">
                <VBtn variant="text" class="mobile-checkout-bar__summary" @click="mobileCart = true">
                    <span class="text-left">
                        <strong>{{ totalServices }} {{ totalServices === 1 ? 'servicio' : 'servicios' }}</strong>
                        <small>Ver resumen</small>
                    </span>
                </VBtn>
                <VBtn color="primary" :loading="form.processing" :disabled="form.processing" @click="openConfirmation">
                    Cobrar {{ formatHnl(totalCents) }}
                </VBtn>
            </div>

            <VBottomSheet v-if="!appointment" v-model="mobileCart" :disabled="form.processing" max-height="88dvh">
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
                :items="confirmationItems"
                :total-cents="appointment ? appointmentTotalCents : totalCents"
                :total-services="appointment ? appointmentTotalServices : totalServices"
                :payment-method="form.payment_method"
                :card-fee-cents="appointment ? appointmentTotalFeeCents : cardFeeCents"
                :net-amount-cents="appointment ? appointmentNetAmountCents : netAmountCents"
                :appointment-mode="Boolean(appointment)"
                :deposit-cents="depositCents"
                :deposit-fee-cents="depositFeeCents"
                :balance-cents="appointmentBalanceCents"
                :balance-fee-cents="appointmentBalanceFeeCents"
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

.appointment-summary { position: sticky; top: 88px; }

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
