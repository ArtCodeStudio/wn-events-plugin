<?php namespace JumpLink\Events\Models;

use Model;

/**
 * Booking Model – Buchungsanfragen (in der alten Lösung nur per E-Mail, jetzt persistiert).
 */
class Booking extends Model
{
    use \Winter\Storm\Database\Traits\Validation;

    public $table = 'jumplink_events_bookings';

    public $rules = [
        'firstname' => 'required|min:2',
        'lastname'  => 'nullable|min:2',
        'email'     => 'required|email',
        'phone'     => 'nullable|min:4',
        'quantity'  => 'required|integer|min:1',
        'zip'       => 'nullable',
    ];

    public $fillable = [
        'event_id', 'calendar', 'event_title', 'event_date', 'quantity', 'total',
        'firstname', 'lastname', 'email', 'phone', 'street', 'zip', 'message', 'status',
    ];

    protected $dates = ['event_date'];

    public $belongsTo = [
        'event' => [\JumpLink\Events\Models\Event::class],
    ];

    /**
     * Status-Optionen für das Backend-Dropdown.
     */
    public function getStatusOptions()
    {
        return [
            'new'       => 'Neu',
            'confirmed' => 'Bestätigt',
            'cancelled' => 'Storniert',
        ];
    }

    /**
     * Counter für das Backend-Navigationsmenü (offene Buchungen).
     */
    public static function unconfirmedCount()
    {
        return (int) self::where('status', 'new')->count();
    }
}
