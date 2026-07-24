<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppointmentDialogStructureTest extends TestCase
{
    public function test_appointment_dialog_uses_one_shell_and_resets_every_mode_after_closing(): void
    {
        $component = file_get_contents(resource_path('js/Components/Appointments/AppointmentDetailsDialog.vue'));

        $this->assertNotFalse($component);
        $this->assertSame(1, substr_count($component, '<VDialog'));
        $this->assertStringContainsString("'detail' | 'edit' | 'reschedule'", $component);
        $this->assertStringContainsString("'cancel' | 'no_show'", $component);
        $this->assertStringContainsString('initialMode: InitialDialogMode', $component);
        $this->assertStringContainsString('resolveInitialMode(initialMode, appointment)', $component);
        $this->assertStringContainsString("openMode('edit')", $component);
        $this->assertStringContainsString('function closeDialog()', $component);
        $this->assertStringContainsString('@after-leave="resetDialog"', $component);
        $this->assertStringContainsString('<VCard v-if="modelValue"', $component);
        $this->assertStringContainsString("emit('closed')", $component);
        $this->assertStringContainsString("mode.value = 'detail'", $component);
        $this->assertStringContainsString('availableTimes.value = []', $component);
        $this->assertStringContainsString("availabilityMessage.value = ''", $component);
        $this->assertStringContainsString('editForm.reset().clearErrors()', $component);
        $this->assertStringContainsString('rescheduleForm.reset().clearErrors()', $component);
        $this->assertStringContainsString('cancelForm.reset().clearErrors()', $component);
        $this->assertStringContainsString('noShowForm.reset().clearErrors()', $component);
    }

    public function test_reschedule_ui_shows_reserved_summary_without_editable_services(): void
    {
        $component = file_get_contents(resource_path('js/Components/Appointments/AppointmentDetailsDialog.vue'));

        $this->assertNotFalse($component);
        $this->assertStringContainsString('Los servicios, cantidades, duración y total reservado no cambian al reprogramar.', $component);
        $this->assertStringContainsString('rescheduleForm.assignments[index].assigned_to', $component);
        $this->assertStringNotContainsString('rescheduleForm.items', $component);
        $this->assertStringNotContainsString('VAutocomplete', $component);
        $this->assertStringNotContainsString('selectedServiceIds', $component);
    }

    public function test_reschedule_initializes_after_details_arrive_and_uses_appointment_contract(): void
    {
        $component = file_get_contents(resource_path('js/Components/Appointments/AppointmentDetailsDialog.vue'));

        $this->assertNotFalse($component);
        $this->assertStringContainsString('watch([() => props.modelValue, () => props.appointment, () => props.initialMode]', $component);
        $this->assertStringContainsString('date: appointment.date', $component);
        $this->assertStringContainsString("if (nextMode === 'reschedule') scheduleAvailability()", $component);
        $this->assertStringContainsString('appointment_id: props.appointment.id', $component);
        $this->assertStringContainsString('assignments: rescheduleForm.assignments', $component);
        $this->assertStringNotContainsString('service_id: item.service_id', $component);
        $this->assertStringContainsString("'Selecciona una fecha.'", $component);
        $this->assertStringContainsString("'No hay horarios disponibles.'", $component);
        $this->assertStringContainsString('response.status === 422', $component);
        $this->assertStringContainsString('response.status === 403', $component);
    }

    public function test_parent_clears_selected_detail_after_dialog_transition(): void
    {
        $page = file_get_contents(resource_path('js/Pages/Appointments/Index.vue'));

        $this->assertNotFalse($page);
        $this->assertStringContainsString('function clearDetails()', $page);
        $this->assertStringContainsString('selectedAppointmentId.value = null', $page);
        $this->assertStringContainsString('selectedAppointment.value = null', $page);
        $this->assertStringContainsString('@closed="clearDetails"', $page);
    }

    public function test_card_actions_open_each_mode_directly_and_use_distinct_desktop_and_mobile_presentations(): void
    {
        $page = file_get_contents(resource_path('js/Pages/Appointments/Index.vue'));

        $this->assertNotFalse($page);
        $this->assertStringContainsString("openAppointment(appointment, 'reschedule')", $page);
        $this->assertStringContainsString("openAppointment(appointment, 'cancel')", $page);
        $this->assertStringContainsString("openAppointment(appointment, 'no_show')", $page);
        $this->assertStringContainsString('desktop-appointment-actions', $page);
        $this->assertStringContainsString('mobile-appointment-actions', $page);
        $this->assertStringContainsString('Más acciones', $page);
        $this->assertStringContainsString('v-if="canReprogram(appointment)"', $page);
        $this->assertStringContainsString('v-if="canCancelAppointment(appointment)"', $page);
        $this->assertStringContainsString('v-if="canMarkNoShow(appointment)"', $page);
        $this->assertStringContainsString("appointment.status === 'scheduled'", $page);
        $this->assertStringContainsString('appointment.can_mark_no_show_now', $page);
    }

    public function test_detail_footer_is_informational_except_for_editing(): void
    {
        $component = file_get_contents(resource_path('js/Components/Appointments/AppointmentDetailsDialog.vue'));

        $this->assertNotFalse($component);
        $this->assertStringContainsString("<template v-if=\"mode === 'detail'\">", $component);
        $this->assertStringContainsString("openMode('edit')", $component);
        $this->assertStringNotContainsString("openMode('reschedule')", $component);
        $this->assertStringNotContainsString("openMode('cancel')", $component);
        $this->assertStringNotContainsString("openMode('no_show')", $component);
        $this->assertStringNotContainsString('Cerrar detalle', $component);
    }

    public function test_dialog_uses_natural_desktop_height_and_compact_loading_states(): void
    {
        $component = file_get_contents(resource_path('js/Components/Appointments/AppointmentDetailsDialog.vue'));

        $this->assertNotFalse($component);
        $this->assertStringContainsString('max-width="780"', $component);
        $this->assertStringContainsString(':scrollable="false"', $component);
        $this->assertStringContainsString('max-height: 85vh', $component);
        $this->assertStringContainsString('flex: 0 1 auto !important', $component);
        $this->assertStringContainsString('height: 100dvh', $component);
        $this->assertStringContainsString('compact-loading', $component);
        $this->assertStringContainsString('compact-error', $component);
        $this->assertStringContainsString('v-if="showFooter"', $component);
    }

    public function test_appointment_shell_provides_csrf_token_for_availability_requests(): void
    {
        $view = file_get_contents(resource_path('views/app.blade.php'));
        $createDialog = file_get_contents(resource_path('js/Components/Appointments/AppointmentFormDialog.vue'));
        $detailsDialog = file_get_contents(resource_path('js/Components/Appointments/AppointmentDetailsDialog.vue'));

        $this->assertNotFalse($view);
        $this->assertNotFalse($createDialog);
        $this->assertNotFalse($detailsDialog);
        $this->assertStringContainsString('<meta name="csrf-token" content="{{ csrf_token() }}">', $view);
        $this->assertStringContainsString('meta[name="csrf-token"]', $createDialog);
        $this->assertStringContainsString('meta[name="csrf-token"]', $detailsDialog);
    }

    public function test_phase_four_c_deposits_share_the_existing_dialog_shell_without_later_features(): void
    {
        $component = file_get_contents(resource_path('js/Components/Appointments/AppointmentDetailsDialog.vue'));

        $this->assertNotFalse($component);
        $this->assertSame(1, substr_count($component, '<VDialog'));
        $this->assertStringContainsString("'cancel' | 'no_show'", $component);
        $this->assertStringContainsString('La cita dejará de ocupar estos horarios.', $component);
        $this->assertStringContainsString('La cita se marcará como no presentada.', $component);
        $this->assertStringContainsString('Confirmar cancelación', $component);
        $this->assertStringContainsString('Marcar No llegó', $component);
        $this->assertStringContainsString("emit('update:modelValue', false)", $component);
        $this->assertStringContainsString("mode === 'deposit'", $component);
        $this->assertStringContainsString('Registrar adelanto', $component);
        $this->assertStringContainsString('Resolución obligatoria', $component);
        $this->assertStringContainsString('full_refund', $component);
        $this->assertStringNotContainsString('Atender y cobrar', $component);
    }
}
