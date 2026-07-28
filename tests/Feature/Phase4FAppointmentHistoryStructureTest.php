<?php

namespace Tests\Feature;

use Tests\TestCase;

class Phase4FAppointmentHistoryStructureTest extends TestCase
{
    public function test_history_has_separate_desktop_table_mobile_cards_and_collapsible_mobile_filters(): void
    {
        $page = file_get_contents(resource_path('js/Pages/Appointments/History.vue'));
        $filters = file_get_contents(resource_path('js/Components/Appointments/AppointmentHistoryFilters.vue'));

        $this->assertNotFalse($page);
        $this->assertNotFalse($filters);
        $this->assertStringContainsString('history-desktop-table', $page);
        $this->assertStringContainsString('history-mobile-cards', $page);
        $this->assertStringContainsString('mobile-history-filters', $page);
        $this->assertStringContainsString('<VExpansionPanels', $page);
        $this->assertStringContainsString('@media (max-width: 700px)', $page);
        $this->assertStringContainsString('.desktop-history-filters, .history-desktop-table { display: none; }', $page);
        $this->assertStringContainsString('.mobile-history-filters, .history-mobile-cards { display: block; }', $page);
        $this->assertStringNotContainsString('overflow-x: auto', $page);
        $this->assertStringContainsString('grid-template-columns: 1fr', $filters);
    }

    public function test_history_uses_one_read_only_detail_dialog_and_only_real_record_actions(): void
    {
        $page = file_get_contents(resource_path('js/Pages/Appointments/History.vue'));

        $this->assertNotFalse($page);
        $this->assertSame(1, substr_count($page, '<AppointmentDetailsDialog'));
        $this->assertSame(0, substr_count($page, '<VDialog'));
        foreach ([':can-update="false"', ':can-assign="false"', ':can-cancel="false"', ':can-mark-no-show="false"', ':can-manage-deposit="false"', ':can-resolve-deposit="false"'] as $contract) {
            $this->assertStringContainsString($contract, $page);
        }
        $this->assertStringContainsString('Ver detalle', $page);
        $this->assertStringContainsString('Ver comprobante', $page);
        $this->assertStringContainsString("item.status === 'completed' && item.linked_sale", $page);
        foreach (['Reprogramar', 'Cancelar', 'No llegó</VBtn>', 'Atender y cobrar', 'Registrar adelanto', 'Editar información'] as $fakeAction) {
            $this->assertStringNotContainsString($fakeAction, $page);
        }
    }

    public function test_history_is_discoverable_and_agenda_active_states_are_distinct(): void
    {
        $layout = file_get_contents(resource_path('js/Layouts/AppLayout.vue'));
        $agenda = file_get_contents(resource_path('js/Pages/Appointments/Index.vue'));

        $this->assertNotFalse($layout);
        $this->assertNotFalse($agenda);
        $this->assertStringContainsString("title: 'Historial de citas'", $layout);
        $this->assertStringContainsString("currentUrl.value.startsWith('/appointments') && !currentUrl.value.startsWith('/appointments/history')", $layout);
        $this->assertStringContainsString("currentUrl.value.startsWith('/appointments/history')", $layout);
        $this->assertStringContainsString('href="/appointments/history"', $agenda);
        $this->assertStringContainsString('Historial', $agenda);
    }
}
