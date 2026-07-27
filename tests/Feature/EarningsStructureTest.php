<?php

namespace Tests\Feature;

use Tests\TestCase;

class EarningsStructureTest extends TestCase
{
    public function test_earnings_focuses_on_real_projection_employee_and_daily_results(): void
    {
        $page = file_get_contents(resource_path('js/Pages/Earnings/Index.vue'));

        $this->assertNotFalse($page);
        $this->assertStringContainsString('Resultados reales', $page);
        $this->assertStringContainsString('Proyección', $page);
        $this->assertStringContainsString('Rendimiento por empleado', $page);
        $this->assertStringContainsString('Resultados reales por día', $page);
        $this->assertStringContainsString('No hay ventas en este periodo', $page);
        $this->assertStringContainsString('Las ventas completadas aparecerán aquí automáticamente.', $page);
        $this->assertStringContainsString('incluida la categoría Nómina', $page);
        $this->assertStringNotContainsString('Nómina pagada por empleado', $page);
        $this->assertStringNotContainsString('Nómina pendiente', $page);
        $this->assertStringContainsString('Resultado disponible', $page);
        $this->assertStringNotContainsString('Otros ingresos', $page);
        $this->assertStringNotContainsString('Salidas', $page);
        $this->assertStringNotContainsString('other_income', $page);
        $this->assertStringNotContainsString('outflows', $page);
    }
}
