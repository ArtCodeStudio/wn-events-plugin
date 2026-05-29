<?php

return [
    'plugin' => [
        'name'        => 'Führungen',
        'description' => 'Verwaltung von Führungen, Kalendern, Staffelpreisen, Bildergalerien und Buchungen.',
        'menu_label'  => 'Führungen',
    ],
    'events' => [
        'menu_label' => 'Veranstaltungen',
        'label'      => 'Führung',
        'label_plural' => 'Führungen',
    ],
    'calendars' => [
        'menu_label' => 'Kalender',
        'label'      => 'Kalender',
    ],
    'bookings' => [
        'menu_label'    => 'Buchungen',
        'label'         => 'Buchung',
        'counter_label' => 'Neue Buchungsanfragen',
    ],
    'permissions' => [
        'manage_events'   => 'Führungen & Kalender verwalten',
        'manage_bookings' => 'Buchungen verwalten',
    ],
    'settings' => [
        'label'       => 'Führungen-Einstellungen',
        'description' => 'Benachrichtigungs-E-Mail, Absender und Buchungsoptionen.',
    ],
];
