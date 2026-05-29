<?php namespace JumpLink\Events\Components;

use Cms\Classes\ComponentBase;

/**
 * EventApi – liefert Events/Kalender als JSON im Legacy-Firestore-Format und
 * nimmt Buchungsanfragen entgegen. (Implementierung folgt.)
 */
class EventApi extends ComponentBase
{
    public function componentDetails()
    {
        return [
            'name'        => 'Event API',
            'description' => 'JSON-API für Events/Kalender und Buchungs-Endpoint (ersetzt Firestore).',
        ];
    }

    public function defineProperties()
    {
        return [];
    }
}
