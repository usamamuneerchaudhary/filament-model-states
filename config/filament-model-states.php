<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default timeline settings
    |--------------------------------------------------------------------------
    |
    | Applied when a StatusTimelineEntry doesn't override them explicitly.
    |
    */
    'timeline' => [
        'per_page' => 25,
        'show_reason' => true,
        'date_format' => 'M j, Y \a\t g:i A',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Kanban settings
    |--------------------------------------------------------------------------
    |
    | 'column_colors' maps a state/status name to a Filament color token.
    | Anything not listed falls back to 'gray'. This is a *default* map —
    | individual StateKanbanBoard widgets can override via getColumnColor().
    |
    */
    'kanban' => [
        'column_colors' => [
            'draft' => 'gray',
            'pending' => 'warning',
            'processing' => 'info',
            'completed' => 'success',
            'cancelled' => 'danger',
            'failed' => 'danger',
        ],

        // If true, an invalid drag (no transition allowed) snaps back with
        // a notification instead of silently failing.
        'notify_on_invalid_transition' => true,

        // Maximum cards rendered per column. Totals always use SQL counts.
        'records_per_column' => 25,

        // Ask for confirmation in a modal before persisting a drag-and-drop move.
        'confirm_before_move' => true,
    ],
];
