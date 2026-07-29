<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import CancelInvoiceDialog from '../../Components/Invoices/CancelInvoiceDialog.vue';
import TransferProofUploadDialog from '../../Components/Invoices/TransferProofUploadDialog.vue';
import PageHeader from '../../Components/PageHeader.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import type { InvoiceDetail } from '../../types/invoices';

const props = defineProps<{ invoice: InvoiceDetail }>();
const cancelOpen = ref(false);
const proofOpen = ref(false);
const selectedPayment = ref<InvoiceDetail['payments'][number] | null>(null);
const money = (value: string) => new Intl.NumberFormat('es-HN', { style: 'currency', currency: 'HNL' }).format(Number(value));
function upload(payment: InvoiceDetail['payments'][number]): void { selectedPayment.value = payment; proofOpen.value = true; }
</script>

<template>
    <Head :title="`Factura ${invoice.sale_number}`" />
    <AppLayout title="Detalle de factura">
        <PageHeader :title="invoice.sale_number" description="Comprobante interno de venta registrado en Studio Lemus."><template #actions><VBtn href="/invoices" variant="text" prepend-icon="mdi-arrow-left">Volver</VBtn><VBtn :href="invoice.receipt_url" variant="tonal" prepend-icon="mdi-printer-outline">Ver comprobante</VBtn><VBtn v-if="invoice.can_cancel" color="error" variant="tonal" prepend-icon="mdi-cancel" @click="cancelOpen = true">Anular factura</VBtn></template></PageHeader>
        <VAlert v-if="invoice.cancellation" type="error" variant="tonal" class="mb-5"><strong>ANULADA</strong><div>{{ invoice.cancellation.canceled_at_display }}<span v-if="invoice.cancellation.canceled_by"> · {{ invoice.cancellation.canceled_by }}</span></div><div>{{ invoice.cancellation.reason }}</div></VAlert>
        <VRow><VCol cols="12" md="5"><VCard class="surface-card h-100"><VCardItem><VCardTitle>Información</VCardTitle></VCardItem><VCardText class="detail-list"><div><span>Estado</span><VChip :color="invoice.status === 'canceled' ? 'error' : 'success'" variant="tonal" size="small">{{ invoice.status_label }}</VChip></div><div><span>Clienta</span><strong>{{ invoice.client_name }}</strong></div><div><span>Fecha</span><strong>{{ invoice.sold_at_display }}</strong></div><div><span>Cobrado por</span><strong>{{ invoice.sold_by.name }}</strong></div><div><span>Método</span><strong>{{ invoice.payment_method_label }}</strong></div><div v-if="invoice.related_appointment"><span>Origen</span><VBtn size="small" variant="text" :href="invoice.related_appointment.url">{{ invoice.related_appointment.label }}</VBtn></div></VCardText></VCard></VCol><VCol cols="12" md="7"><VCard class="surface-card h-100"><VCardItem><VCardTitle>Servicios y cargos</VCardTitle></VCardItem><VList><VListItem v-for="(item, index) in invoice.items" :key="index"><VListItemTitle>{{ item.service_name }} × {{ item.quantity }}</VListItemTitle><VListItemSubtitle>{{ item.performed_by ? `Realizado por ${item.performed_by}` : 'Sin persona registrada' }} · {{ money(item.unit_price) }} c/u</VListItemSubtitle><template #append><strong>{{ money(item.line_total) }}</strong></template></VListItem><VListItem v-for="charge in invoice.additional_charges" :key="charge.name"><VListItemTitle>{{ charge.name }}</VListItemTitle><template #append><strong>{{ money(charge.amount) }}</strong></template></VListItem></VList><VDivider /><VCardText class="detail-list"><div><span>Subtotal</span><strong>{{ money(invoice.subtotal) }}</strong></div><div v-if="Number(invoice.discount_amount) > 0"><span>Descuento</span><strong>− {{ money(invoice.discount_amount) }}</strong></div><div class="text-h6"><span>Total</span><strong>{{ money(invoice.total) }}</strong></div></VCardText></VCard></VCol></VRow>
        <VCard class="surface-card mt-6"><VCardItem><VCardTitle>Pagos</VCardTitle><VCardSubtitle>Adelantos y saldo final conservan su método original</VCardSubtitle></VCardItem><VCardText><VCard v-for="payment in invoice.payments" :key="payment.id" variant="outlined" class="payment-row mb-3"><VCardText><div><strong>{{ payment.type_label }}</strong><div class="text-body-2 text-medium-emphasis">{{ payment.method_label }} · {{ payment.proof_status_label }}</div></div><strong>{{ money(payment.amount) }}</strong><div class="payment-actions"><VBtn v-if="payment.proof_url" :href="payment.proof_url" target="_blank" variant="text" prepend-icon="mdi-image-outline">Ver captura</VBtn><VBtn v-if="payment.can_upload_proof" variant="tonal" prepend-icon="mdi-upload-outline" @click="upload(payment)">Subir captura</VBtn></div></VCardText></VCard></VCardText></VCard>
        <CancelInvoiceDialog v-model="cancelOpen" :invoice="invoice" />
        <TransferProofUploadDialog v-model="proofOpen" :invoice="selectedPayment ? { id: invoice.id, sale_number: invoice.sale_number, client_name: invoice.client_name, sold_at_display: invoice.sold_at_display, payment_id: selectedPayment.id, amount: selectedPayment.amount } : null" />
    </AppLayout>
</template>

<style scoped>
.detail-list { display: grid; gap: 14px; }.detail-list > div { display: flex; align-items: center; justify-content: space-between; gap: 16px; }.detail-list span { color: rgba(var(--v-theme-on-surface), .62); }.payment-row :deep(.v-card-text) { display: grid; grid-template-columns: 1fr auto auto; align-items: center; gap: 16px; }.payment-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; }
@media (max-width: 700px) { .payment-row :deep(.v-card-text) { grid-template-columns: 1fr; }.payment-actions { justify-content: stretch; }.payment-actions :deep(.v-btn) { width: 100%; } }
</style>
