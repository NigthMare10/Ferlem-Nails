<?php

return [
    'slot_minutes' => (int) env('APPOINTMENTS_SLOT_MINUTES', 15),
    'checkout_grace_minutes' => (int) env('APPOINTMENT_CHECKOUT_GRACE_MINUTES', 30),
];
