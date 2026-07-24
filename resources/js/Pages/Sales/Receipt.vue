<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';

type ReceiptItem = {
    id: number;
    service_name: string;
    service_description: string | null;
    duration_minutes: number | null;
    unit_price: string;
    quantity: number;
    line_total: string;
    performed_by: { id: number; name: string } | null;
};

defineProps<{
    sale: {
        id: number;
        sale_number: string;
        sold_at: string;
        sold_at_display: string;
        subtotal: string;
        total: string;
        total_services: number;
        payment_method: 'cash' | 'card';
        payment_method_label: string;
        client: { name: string; phone: string | null } | null;
        sold_by: { id: number; name: string };
        items: ReceiptItem[];
        payments: Array<{ id: number; type: 'deposit_applied' | 'final_payment'; type_label: string; method: 'cash' | 'card'; method_label: string; amount: string }>;
    };
}>();

const money = (value: string) => new Intl.NumberFormat('es-HN', {
    style: 'currency',
    currency: 'HNL',
}).format(Number(value));
</script>

<template>
    <Head :title="`Comprobante ${sale.sale_number}`" />
    <VApp>
        <VMain class="receipt-page">
            <div class="receipt-actions">
                <VBtn variant="outlined" prepend-icon="mdi-plus" @click="router.visit('/sales/new')">Nueva venta</VBtn>
                <VBtn color="primary" prepend-icon="mdi-printer-outline" @click="window.print()">Imprimir</VBtn>
            </div>

            <article class="receipt-paper" aria-label="Comprobante de venta">
                <header class="receipt-header">
                    <div class="receipt-monogram">SL</div>
                    <h1>Studio Lemus</h1>
                    <p>Comprobante de venta</p>
                </header>

                <div class="receipt-divider" />

                <dl class="receipt-meta">
                    <div><dt>Número</dt><dd>{{ sale.sale_number }}</dd></div>
                    <div><dt>Fecha</dt><dd>{{ sale.sold_at_display }}</dd></div>
                    <div><dt>Atendido por</dt><dd>{{ sale.sold_by.name }}</dd></div>
                    <div v-if="sale.client"><dt>Clienta</dt><dd>{{ sale.client.name }}</dd></div>
                    <div v-if="!sale.payments.length"><dt>Método de pago</dt><dd>{{ sale.payment_method_label }}</dd></div>
                </dl>

                <div class="receipt-divider" />

                <section class="receipt-items">
                    <div v-for="item in sale.items" :key="item.id" class="receipt-item">
                        <div class="receipt-item__name">{{ item.service_name }}</div>
                        <div class="receipt-item__line">
                            <span>{{ item.quantity }} × {{ money(item.unit_price) }}</span>
                            <strong>{{ money(item.line_total) }}</strong>
                        </div>
                        <div v-if="item.performed_by" class="receipt-item__performer">Realizado por {{ item.performed_by.name }}</div>
                    </div>
                </section>

                <template v-if="sale.payments.length">
                    <div class="receipt-divider" />
                    <section class="receipt-payments">
                        <div v-for="payment in sale.payments" :key="payment.id" class="receipt-item__line">
                            <span>{{ payment.type_label }} · {{ payment.method_label }}</span><strong>{{ money(payment.amount) }}</strong>
                        </div>
                    </section>
                </template>

                <div class="receipt-divider" />

                <dl class="receipt-totals">
                    <div><dt>Total de servicios</dt><dd>{{ sale.total_services }}</dd></div>
                    <div class="receipt-total"><dt>Total</dt><dd>{{ money(sale.total) }}</dd></div>
                </dl>

                <footer class="receipt-footer">
                    <p>Gracias por confiar en Studio Lemus.</p>
                </footer>
            </article>
        </VMain>
    </VApp>
</template>

<style>
.receipt-page {
    min-height: 100dvh;
    padding: 32px 16px 60px;
    background: #eee9e7;
    color: #171315;
}

.receipt-actions {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin: 0 auto 22px;
}

.receipt-paper {
    box-sizing: border-box;
    width: min(80mm, 100%);
    margin: 0 auto;
    padding: 8mm 6mm;
    background: #fff;
    box-shadow: 0 18px 50px rgba(40, 29, 33, 0.16);
    font-family: "Courier New", Courier, monospace;
    font-size: 12px;
    line-height: 1.45;
}

.receipt-header {
    text-align: center;
}

.receipt-monogram {
    display: grid;
    width: 38px;
    height: 38px;
    margin: 0 auto 8px;
    place-items: center;
    border: 2px solid #171315;
    border-radius: 50%;
    font-weight: 800;
    letter-spacing: 0.08em;
}

.receipt-header h1 {
    margin: 0;
    font-size: 20px;
}

.receipt-header p,
.receipt-footer p {
    margin: 4px 0 0;
}

.receipt-divider {
    margin: 16px 0;
    border-top: 1px dashed #777;
}

.receipt-meta,
.receipt-totals {
    display: grid;
    gap: 6px;
    margin: 0;
}

.receipt-meta div,
.receipt-totals div,
.receipt-item__line {
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

.receipt-meta dt,
.receipt-totals dt {
    color: #555;
}

.receipt-meta dd,
.receipt-totals dd {
    margin: 0;
    text-align: right;
}

.receipt-items {
    display: grid;
    gap: 12px;
}

.receipt-card-note {
    margin: 10px 0 0;
    text-align: center;
    color: #555;
    font-size: 11px;
}

.receipt-item__name {
    margin-bottom: 3px;
    font-weight: 700;
}

.receipt-item__performer { margin-top: 2px; color: #555; font-size: 10px; }
.receipt-payments { display: grid; gap: 7px; }

.receipt-total {
    align-items: baseline;
    margin-top: 5px;
    font-size: 17px;
    font-weight: 800;
}

.receipt-total dt {
    color: #171315;
}

.receipt-footer {
    margin-top: 24px;
    text-align: center;
}

@page {
    size: 80mm auto;
    margin: 3mm;
}

@media print {
    html,
    body,
    #app {
        width: 80mm;
        min-width: 0;
        margin: 0;
        background: #fff !important;
    }

    .receipt-page {
        min-height: auto;
        padding: 0;
        background: #fff !important;
    }

    .receipt-actions {
        display: none !important;
    }

    .receipt-paper {
        width: 100%;
        padding: 0;
        box-shadow: none;
    }
}

@media (max-width: 500px) {
    .receipt-page {
        padding: 18px 10px 40px;
    }

    .receipt-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }
}
</style>
