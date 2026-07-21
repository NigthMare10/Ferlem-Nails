<?php

return [
    // Valores temporales hasta que Studio Lemus confirme su horario operativo real.
    'open_time' => env('APPOINTMENTS_OPEN_TIME', '08:00'),
    'close_time' => env('APPOINTMENTS_CLOSE_TIME', '18:00'),
    'slot_minutes' => (int) env('APPOINTMENTS_SLOT_MINUTES', 15),
];
