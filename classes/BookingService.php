<?php namespace JumpLink\Events\Classes;

use Mail;
use Log;
use Validator;
use JumpLink\Events\Models\Event;
use JumpLink\Events\Models\Booking;
use JumpLink\Events\Models\Settings;

/**
 * Gemeinsame Buchungslogik – eine Quelle der Wahrheit für die JSON-API
 * (Api::book, clientseitig/Rivets) und die serverseitige CMS-Komponente
 * (EventList::onBook). So unterstützt das Plugin beide Render-Modi, ohne die
 * Validierung/Preisberechnung/Mail-Logik zu duplizieren.
 */
class BookingService
{
    /**
     * Verarbeitet eine Buchungsanfrage: Honeypot, Validierung, Staffelpreis,
     * Speichern und Mailversand.
     *
     * @return array{success:bool,status:int,errors:array,booking:?Booking,disabled?:bool,spam?:bool}
     */
    public static function create(array $input): array
    {
        // Honeypot: Feld 'website' muss leer bleiben. Bots befüllen es -> wir tun
        // nach außen erfolgreich, ignorieren die Anfrage aber.
        if (!empty($input['website'])) {
            return ['success' => true, 'status' => 200, 'errors' => [], 'booking' => null, 'spam' => true];
        }

        if (!Settings::get('booking_enabled', true)) {
            return ['success' => false, 'status' => 403, 'errors' => [], 'booking' => null, 'disabled' => true];
        }

        $validator = Validator::make($input, [
            'event_id'  => 'nullable',
            'firstname' => 'required|min:2',
            'lastname'  => 'nullable',
            'email'     => 'required|email',
            'phone'     => 'nullable',
            'quantity'  => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return ['success' => false, 'status' => 422, 'errors' => $validator->errors()->toArray(), 'booking' => null];
        }

        $event = null;
        if (!empty($input['event_id'])) {
            $event = Event::find($input['event_id']);
        }
        if (!$event && !empty($input['handle'])) {
            $event = Event::where('slug', $input['handle'])->first();
        }

        $quantity = max(1, (int) ($input['quantity'] ?? 1));
        $total = null;
        if ($event) {
            $total = $event->calcTotal($quantity);
        }
        if (is_null($total) && isset($input['total']) && is_numeric($input['total'])) {
            $total = (float) $input['total'];
        }

        // gebuchtes Datum (variable Events liefern ein eigenes Datum dd.mm.yyyy)
        $eventDate = self::parseDate($input['date'] ?? null);
        if (!$eventDate && $event && $event->starts_at) {
            $eventDate = $event->starts_at;
        }

        $booking = new Booking;
        $booking->event_id    = $event ? $event->id : null;
        $booking->calendar    = $event && $event->calendar ? $event->calendar->name : ($input['calendar'] ?? null);
        $booking->event_title = $event ? $event->title : ($input['title'] ?? null);
        $booking->event_date  = $eventDate;
        $booking->quantity    = $quantity;
        $booking->total       = $total;
        $booking->firstname   = $input['firstname'] ?? ($input['name'] ?? '');
        $booking->lastname    = $input['lastname'] ?? null;
        $booking->email       = $input['email'];
        $booking->phone       = $input['phone'] ?? null;
        $booking->street      = $input['street'] ?? null;
        $booking->zip         = $input['zip'] ?? null;
        $booking->message     = $input['message'] ?? null;
        $booking->status      = 'new';

        // Das Booking-Model validiert serverseitig zusätzlich (z. B. phone min:4,
        // lastname min:2). Bei Verstoß sauber als 422 mit Feldfehlern melden.
        try {
            $booking->save();
        } catch (\Winter\Storm\Database\ModelException $e) {
            return ['success' => false, 'status' => 422, 'errors' => $booking->errors()->toArray(), 'booking' => null];
        }

        self::sendMails($booking, $event);

        return ['success' => true, 'status' => 200, 'errors' => [], 'booking' => $booking];
    }

    /**
     * E-Mails an Veranstalter (Event-Notifications oder Plugin-Settings) und
     * optional eine Bestätigungskopie an den Bucher.
     */
    public static function sendMails(Booking $booking, $event): void
    {
        $vars = ['booking' => $booking, 'event' => $event];

        $recipients = [];
        if ($event && is_array($event->notifications)) {
            foreach ($event->notifications as $n) {
                if (!empty($n['email'])) {
                    $recipients[] = ['email' => $n['email'], 'name' => $n['name'] ?? null];
                }
            }
        }
        if (empty($recipients)) {
            $defEmail = Settings::get('notify_email');
            if ($defEmail) {
                $recipients[] = ['email' => $defEmail, 'name' => Settings::get('notify_name')];
            }
        }

        try {
            foreach ($recipients as $r) {
                Mail::send('jumplink.events::mail.booking_notification', $vars, function ($message) use ($r) {
                    $message->to($r['email'], $r['name'] ?: $r['email']);
                    self::applySender($message);
                });
            }

            if (Settings::get('send_customer_copy', true) && $booking->email) {
                Mail::send('jumplink.events::mail.booking_confirmation', $vars, function ($message) use ($booking) {
                    $message->to($booking->email, trim($booking->firstname . ' ' . $booking->lastname));
                    self::applySender($message);
                });
            }
        } catch (\Throwable $e) {
            // Buchung bleibt gespeichert; Mailfehler nur protokollieren.
            Log::error('[jumplink.events] Buchungs-Mail fehlgeschlagen: ' . $e->getMessage());
        }
    }

    protected static function applySender($message): void
    {
        $email = Settings::get('sender_email');
        $name  = Settings::get('sender_name');
        if ($email) {
            $message->from($email, $name ?: null);
        }
    }

    public static function parseDate($value)
    {
        if (!$value) {
            return null;
        }
        foreach (['d.m.Y H:i', 'Y-m-d H:i', 'd.m.Y', 'Y-m-d'] as $fmt) {
            try {
                $d = \Carbon\Carbon::createFromFormat($fmt, trim($value));
                if ($d !== false) {
                    if (!str_contains($fmt, 'H:i')) {
                        $d->startOfDay();
                    }
                    return $d;
                }
            } catch (\Throwable $e) {
                // nächstes Format probieren
            }
        }
        return null;
    }
}
