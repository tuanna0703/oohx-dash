<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Phase 1 Feature Flags
    |--------------------------------------------------------------------------
    |
    | Phase 1 focuses on marketplace listing/booking.
    | AdOps fields (SSP/programmatic) are hidden by default.
    | Set to true to reveal advanced SSP sections in Filament forms.
    |
    */

    'show_adops_fields' => (bool) env('OOHX_SHOW_ADOPS', false),

];
