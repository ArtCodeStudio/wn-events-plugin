<?php namespace JumpLink\Events\Classes;

use Mail;
use Log;
use Response;
use Validator;
use JumpLink\Events\Models\Event;
use JumpLink\Events\Models\Calendar;
use JumpLink\Events\Models\Booking;
use JumpLink\Events\Models\Settings;

/**
 * JSON-API für das Frontend – ersetzt die direkten Firestore-Zugriffe.
 *
 * Liefert Events und Kalender im Legacy-Firestore-Format, das die bestehenden
 * rivets-Komponenten erwarten, und nimmt Buchungsanfragen entgegen.
 */
class Api
{
    /**
     * Kalender als JSON (nur aktive).
     */
    public function calendars(\Illuminate\Http\Request $request)
    {
        $calendars = Calendar::isActive()->orderBy('sort_order')->get();

        $data = $calendars->map(function ($c) {
            return [
                'id'          => (string) $c->id,
                'active'      => (bool) $c->is_active,
                'name'        => $c->name,
                'title'       => $c->title,
                'subtitle'    => $c->subtitle,
                'description' => $c->description,
                'note'        => $c->note,
                'color'       => $c->color,
                'cssClass'    => $c->css_class,
                'type'        => $c->type,
            ];
        })->all();

        return $this->json($request, $data);
    }

    /**
     * Gefilterte Event-Liste als JSON im Legacy-Format.
     * Query-Parameter analog zur alten jumplink.events.get(): type, calendar,
     * excludeCalendar, active, startTime, limit.
     */
    public function events(\Illuminate\Http\Request $request)
    {
        // Detail-Lookup (einzelnes Event) – ignoriert Zeit-/Aktiv-Filter
        $id     = $request->get('id');
        $handle = $request->get('handle');
        $title  = $request->get('title');

        if ($id || $handle || $title) {
            $query = Event::with(['calendar', 'images']);
            if ($id) {
                $query->where('id', $id);
            }
            if ($handle) {
                $query->where('slug', $handle);
            }
            if ($title) {
                $query->where('title', $title);
            }
            $data = $query->orderBy('starts_at', 'asc')->get()
                ->map(fn ($e) => $e->toLegacyArray())->all();
            return $this->json($request, $data);
        }

        $type           = $request->get('type', 'all');
        $calendar       = $request->get('calendar', 'all');
        $excludeCal     = $request->get('excludeCalendar', 'none');
        $active         = $request->get('active', 'true');
        $startTime      = $request->get('startTime', 'all');
        $limit          = (int) $request->get('limit', 0);

        $query = Event::with(['calendar', 'images'])
            ->applyType($type)
            ->applyCalendar($calendar)
            ->excludeCalendar($excludeCal)
            ->applyStartTime($startTime)
            ->orderBy('starts_at', 'asc');

        if ($active === 'true' || $active === '1' || $active === true) {
            $query->isActive();
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $data = $query->get()->map(function ($e) {
            return $e->toLegacyArray();
        })->all();

        return $this->json($request, $data);
    }

    /**
     * Buchungsanfrage entgegennehmen: validieren, speichern, E-Mails versenden.
     */
    public function book(\Illuminate\Http\Request $request)
    {
        // Honeypot gegen Bots (Feld muss leer bleiben)
        if ($request->filled('website')) {
            return $this->json($request, ['success' => true]); // still 200, aber ignorieren
        }

        if (!Settings::get('booking_enabled', true)) {
            return $this->json($request, ['success' => false, 'error' => 'Buchungen sind derzeit deaktiviert.'], 403);
        }

        $input = $request->all();

        $validator = Validator::make($input, [
            'event_id'  => 'nullable',
            'firstname' => 'required|min:2',
            'lastname'  => 'nullable',
            'email'     => 'required|email',
            'phone'     => 'nullable',
            'quantity'  => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->json($request, [
                'success' => false,
                'errors'  => $validator->errors()->toArray(),
            ], 422);
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
        $eventDate = $this->parseDate($input['date'] ?? null);
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
        $booking->save();

        $this->sendMails($booking, $event);

        return $this->json($request, [
            'success' => true,
            'booking' => ['id' => $booking->id, 'total' => $booking->total],
        ]);
    }

    /**
     * E-Mails an Veranstalter (Notifications/Settings) und optional an den Bucher.
     */
    protected function sendMails(Booking $booking, $event)
    {
        $vars = [
            'booking' => $booking,
            'event'   => $event,
        ];

        // Empfänger: Event-Notifications, sonst Plugin-Settings
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
                    $this->applySender($message);
                });
            }

            if (Settings::get('send_customer_copy', true) && $booking->email) {
                Mail::send('jumplink.events::mail.booking_confirmation', $vars, function ($message) use ($booking) {
                    $message->to($booking->email, trim($booking->firstname . ' ' . $booking->lastname));
                    $this->applySender($message);
                });
            }
        } catch (\Throwable $e) {
            // Buchung bleibt gespeichert, Mailfehler nur loggen
            Log::error('[jumplink.events] Buchungs-Mail fehlgeschlagen: ' . $e->getMessage());
        }
    }

    protected function applySender($message)
    {
        $email = Settings::get('sender_email');
        $name  = Settings::get('sender_name');
        if ($email) {
            $message->from($email, $name ?: null);
        }
    }

    protected function parseDate($value)
    {
        if (!$value) {
            return null;
        }
        foreach (['d.m.Y H:i', 'Y-m-d H:i', 'd.m.Y', 'Y-m-d'] as $fmt) {
            try {
                $d = \Carbon\Carbon::createFromFormat($fmt, trim($value));
                if ($d !== false) {
                    // Reine Datumsangaben auf den Tagesbeginn setzen (keine Zufallszeit)
                    if (!str_contains($fmt, 'H:i')) {
                        $d->startOfDay();
                    }
                    return $d;
                }
            } catch (\Throwable $e) {
                // nächstes Format
            }
        }
        return null;
    }

    /**
     * JSON-Antwort mit CORS für die eigene Domain (Frontend nutzt fetch/XHR).
     */
    protected function json(\Illuminate\Http\Request $request, $data, $status = 200)
    {
        return Response::json($data, $status)
            ->header('Access-Control-Allow-Origin', $request->headers->get('Origin', '*'))
            ->header('Cache-Control', 'no-store');
    }
}
