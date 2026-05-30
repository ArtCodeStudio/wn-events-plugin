<?php

return [
    'plugin' => [
        'name'        => 'Veranstaltungen',
        'description' => 'Verwaltung von Veranstaltungen, Kalendern, Staffelpreisen, Bildergalerien und Buchungen.',
        'menu_label'  => 'Veranstaltungen',
    ],
    'events' => [
        'menu_label' => 'Veranstaltungen',
        'label'      => 'Veranstaltung',
        'label_plural' => 'Veranstaltungen',
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
        'manage_events'   => 'Veranstaltungen & Kalender verwalten',
        'manage_bookings' => 'Buchungen verwalten',
    ],
    'settings' => [
        'label'       => 'Veranstaltungen-Einstellungen',
        'description' => 'Benachrichtigungs-E-Mail, Absender und Buchungsoptionen.',
    ],
];
