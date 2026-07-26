<?php

namespace Tests\Feature;

use Tests\TestCase;

class SaleCheckoutStructureTest extends TestCase
{
    public function test_normal_and_appointment_checkout_share_lines_payment_and_summary_components(): void
    {
        $page = file_get_contents(resource_path('js/Pages/Sales/Create.vue'));
        $cart = file_get_contents(resource_path('js/Components/Sales/SaleCart.vue'));
        $dialog = file_get_contents(resource_path('js/Components/Sales/ConfirmSaleDialog.vue'));
        $mobile = file_get_contents(resource_path('js/Components/Sales/SaleMobileCheckout.vue'));
        $payment = file_get_contents(resource_path('js/Components/Sales/SalePaymentMethod.vue'));

        $this->assertNotFalse($page);
        $this->assertNotFalse($cart);
        $this->assertNotFalse($dialog);
        $this->assertNotFalse($mobile);
        $this->assertNotFalse($payment);
        $this->assertStringContainsString('<SaleLineItem v-for="item in appointmentCart"', $page);
        $this->assertStringContainsString('<SalePaymentMethod v-model="form.payment_method"', $page);
        $this->assertStringContainsString('<SaleCheckoutSummary', $page);
        $this->assertStringContainsString('<SaleLineItem v-for="item in items"', $cart);
        $this->assertStringContainsString('<SalePaymentMethod', $cart);
        $this->assertStringContainsString('<SaleCheckoutSummary', $cart);
        $this->assertStringContainsString('<SaleCheckoutSummary', $dialog);
        $this->assertStringContainsString('<SalePaymentMethod', $dialog);
        $this->assertStringContainsString('<SaleMobileCheckout', $page);
        $this->assertStringContainsString('v-if="showMobileCheckout"', $page);
        $this->assertStringContainsString('appointmentCart', $page);
        $this->assertStringNotContainsString('mobile-checkout-bar__summary {\n        display: none', $page);
        $this->assertStringContainsString('env(safe-area-inset-bottom', $mobile);
        $this->assertStringContainsString('data-testid="sale-mobile-summary"', $mobile);
        $this->assertStringContainsString("label: 'Efectivo'", $payment);
        $this->assertStringContainsString("label: 'Tarjeta'", $payment);
        $this->assertStringContainsString("label: 'Transferencia'", $payment);
        $this->assertStringContainsString('Agregar captura', $payment);
        $this->assertStringNotContainsString('<VSwitch', $payment);
    }

    public function test_both_backend_flows_use_the_same_financial_writer(): void
    {
        $normal = file_get_contents(app_path('Actions/Sales/CreateSaleAction.php'));
        $appointment = file_get_contents(app_path('Actions/Appointments/CheckoutAppointmentAction.php'));

        $this->assertNotFalse($normal);
        $this->assertNotFalse($appointment);
        $this->assertStringContainsString('PersistCompletedSaleAction', $normal);
        $this->assertStringContainsString('PersistCompletedSaleAction', $appointment);
        $this->assertStringContainsString('SaleFinancials::payment', $normal);
        $this->assertStringContainsString('SaleFinancials::payment', $appointment);
        $this->assertStringNotContainsString('new SaleItem', $normal);
        $this->assertStringNotContainsString('new SaleItem', $appointment);
    }
}
