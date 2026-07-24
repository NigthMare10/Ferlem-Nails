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

        $this->assertNotFalse($page);
        $this->assertNotFalse($cart);
        $this->assertNotFalse($dialog);
        $this->assertStringContainsString('<SaleLineItem v-for="item in appointmentCart"', $page);
        $this->assertStringContainsString('<SalePaymentMethod v-model="form.payment_method"', $page);
        $this->assertStringContainsString('<SaleCheckoutSummary', $page);
        $this->assertStringContainsString('<SaleLineItem v-for="item in items"', $cart);
        $this->assertStringContainsString('<SalePaymentMethod', $cart);
        $this->assertStringContainsString('<SaleCheckoutSummary', $cart);
        $this->assertStringContainsString('<SaleCheckoutSummary', $dialog);
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
