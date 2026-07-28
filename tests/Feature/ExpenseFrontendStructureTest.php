<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExpenseFrontendStructureTest extends TestCase
{
    public function test_expense_pages_include_desktop_tables_mobile_cards_and_permission_navigation(): void
    {
        $index = file_get_contents(resource_path('js/Pages/Expenses/Index.vue'));
        $layout = file_get_contents(resource_path('js/Layouts/AppLayout.vue'));
        $home = file_get_contents(resource_path('js/Pages/Home.vue'));

        $this->assertStringContainsString('expense-desktop-table', $index);
        $this->assertStringContainsString('expense-mobile-cards', $index);
        $this->assertStringContainsString('Número, descripción o proveedor', $index);
        $this->assertStringNotContainsString('Operación automática de nómina', $index);
        $this->assertStringNotContainsString("section === 'payroll'", $index);
        $this->assertStringContainsString("title: 'Gastos'", $layout);
        $this->assertStringContainsString("startsWith('/expenses')", $layout);
        $this->assertStringContainsString("can('expenses.create')", $home);
        $this->assertStringContainsString('Registrar gasto', $home);
    }

    public function test_earnings_distinguishes_collection_and_expense_methods_and_displays_warning(): void
    {
        $earnings = file_get_contents(resource_path('js/Pages/Earnings/Index.vue'));

        $this->assertStringContainsString('Métodos de cobro', $earnings);
        $this->assertStringContainsString('Métodos de gasto', $earnings);
        $this->assertStringNotContainsString('Categorías de gastos', $earnings);
        $this->assertStringNotContainsString('Gastos por día', $earnings);
        $this->assertStringNotContainsString('Ritmo del periodo', $earnings);
        $this->assertStringContainsString('Resultado disponible', $earnings);
        $this->assertStringContainsString('incluida la categoría Nómina', $earnings);
        $this->assertStringNotContainsString('Nómina pagada por empleado', $earnings);
        $this->assertStringNotContainsString('ganancia neta', strtolower($earnings));
    }
}
