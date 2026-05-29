<?php

return [
    'plugin' => [
        'name'        => 'Events',
        'description' => 'Manage guided tours, calendars, scale prices, image galleries and bookings.',
        'menu_label'  => 'Events',
    ],
    'events' => [
        'menu_label'   => 'Events',
        'label'        => 'Event',
        'label_plural' => 'Events',
    ],
    'calendars' => [
        'menu_label' => 'Calendars',
        'label'      => 'Calendar',
    ],
    'bookings' => [
        'menu_label'    => 'Bookings',
        'label'         => 'Booking',
        'counter_label' => 'New booking requests',
    ],
    'permissions' => [
        'manage_events'   => 'Manage events & calendars',
        'manage_bookings' => 'Manage bookings',
    ],
    'settings' => [
        'label'       => 'Events settings',
        'description' => 'Notification email, sender and booking options.',
    ],
];
