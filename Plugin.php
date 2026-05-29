<?php namespace JumpLink\Events;

use Backend;
use System\Classes\PluginBase;
use JumpLink\Events\Models\Booking;

/**
 * JumpLink Events Plugin
 *
 * Lokale Ablösung der clientseitigen Firebase/Firestore-Lösung für Führungen:
 * Kalender, Events (mit Staffelpreisen & Bildergalerien) und Buchungen.
 */
class Plugin extends PluginBase
{
    public function pluginDetails()
    {
        return [
            'name'        => 'jumplink.events::lang.plugin.name',
            'description' => 'jumplink.events::lang.plugin.description',
            'author'      => 'JumpLink – Art+Code Studio',
            'icon'        => 'icon-compass',
            'homepage'    => 'https://artandcode.studio',
        ];
    }

    public function registerComponents()
    {
        return [
            \JumpLink\Events\Components\EventApi::class => 'eventApi',
        ];
    }

    public function registerNavigation()
    {
        return [
            'events' => [
                'label'       => 'jumplink.events::lang.plugin.menu_label',
                'url'         => Backend::url('jumplink/events/events'),
                'icon'        => 'icon-compass',
                'permissions' => ['jumplink.events.*'],
                'order'       => 500,
                'sideMenu' => [
                    'events' => [
                        'label'       => 'jumplink.events::lang.events.menu_label',
                        'icon'        => 'icon-map-signs',
                        'url'         => Backend::url('jumplink/events/events'),
                        'permissions' => ['jumplink.events.manage_events'],
                    ],
                    'calendars' => [
                        'label'       => 'jumplink.events::lang.calendars.menu_label',
                        'icon'        => 'icon-calendar',
                        'url'         => Backend::url('jumplink/events/calendars'),
                        'permissions' => ['jumplink.events.manage_events'],
                    ],
                    'bookings' => [
                        'label'        => 'jumplink.events::lang.bookings.menu_label',
                        'icon'         => 'icon-envelope',
                        'url'          => Backend::url('jumplink/events/bookings'),
                        'permissions'  => ['jumplink.events.manage_bookings'],
                        'counter'      => [Booking::class, 'unconfirmedCount'],
                        'counterLabel' => 'jumplink.events::lang.bookings.counter_label',
                    ],
                ],
            ],
        ];
    }

    public function registerPermissions()
    {
        return [
            'jumplink.events.manage_events' => [
                'tab'   => 'jumplink.events::lang.plugin.menu_label',
                'label' => 'jumplink.events::lang.permissions.manage_events',
            ],
            'jumplink.events.manage_bookings' => [
                'tab'   => 'jumplink.events::lang.plugin.menu_label',
                'label' => 'jumplink.events::lang.permissions.manage_bookings',
            ],
        ];
    }

    public function registerSettings()
    {
        return [
            'settings' => [
                'label'       => 'jumplink.events::lang.settings.label',
                'description' => 'jumplink.events::lang.settings.description',
                'category'    => 'jumplink.events::lang.plugin.menu_label',
                'icon'        => 'icon-cog',
                'class'       => \JumpLink\Events\Models\Settings::class,
                'permissions' => ['jumplink.events.manage_events'],
                'order'       => 500,
            ],
        ];
    }

    public function registerMailTemplates()
    {
        return [
            'jumplink.events::mail.booking_notification',
            'jumplink.events::mail.booking_confirmation',
        ];
    }

    public function register()
    {
        $this->registerConsoleCommand(
            'jumplink.events.import',
            \JumpLink\Events\Console\ImportFirestore::class
        );
    }
}
