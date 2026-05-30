<?php namespace JumpLink\Events\Components;

use Cms\Classes\ComponentBase;
use JumpLink\Events\Models\Event;
use JumpLink\Events\Classes\BookingService;

/**
 * EventList – serverseitige Darstellung der Veranstaltungen (SEO-freundlich,
 * HTML im Quelltext) inkl. Buchungsanfrage. Ergänzt die clientseitige JSON-API
 * (classes/Api.php), sodass das Plugin beide Render-Modi unterstützt.
 *
 * Die Event-Abfrage läuft bewusst LAZY (erst beim Aufruf von events() im
 * Template), damit die Komponente gefahrlos an ein Layout gehängt werden kann,
 * ohne auf jeder Seite eine DB-Abfrage auszulösen.
 */
class EventList extends ComponentBase
{
    /** @var \Illuminate\Support\Collection|null gecachte Event-Ergebnisliste */
    protected $eventsCache = null;

    public function componentDetails()
    {
        return [
            'name'        => 'Veranstaltungen',
            'description' => 'Listet Veranstaltungen serverseitig auf und nimmt Buchungsanfragen entgegen.',
        ];
    }

    public function defineProperties()
    {
        return [
            'type' => [
                'title'       => 'Typ',
                'type'        => 'dropdown',
                'default'     => 'all',
                'options'     => ['all' => 'Alle', 'fix' => 'Fixer Termin', 'variable' => 'Auf Anfrage'],
            ],
            'calendar' => [
                'title'       => 'Kalender',
                'description' => 'Kalender-Name oder "all"',
                'type'        => 'string',
                'default'     => 'all',
            ],
            'excludeCalendar' => [
                'title'   => 'Kalender ausschließen',
                'type'    => 'string',
                'default' => 'none',
            ],
            'active' => [
                'title'   => 'Nur aktive',
                'type'    => 'dropdown',
                'default' => 'true',
                'options' => ['true' => 'Ja', 'false' => 'Nein', 'all' => 'Alle'],
            ],
            'startTime' => [
                'title'   => 'Zeitraum',
                'type'    => 'dropdown',
                'default' => 'future',
                'options' => ['future' => 'Zukünftige', 'past' => 'Vergangene', 'all' => 'Alle'],
            ],
            'limit' => [
                'title'   => 'Limit (0 = ohne)',
                'type'    => 'string',
                'default' => '0',
            ],
            'showBookButton' => [
                'title'   => 'Buchungsbutton anzeigen',
                'type'    => 'checkbox',
                'default' => true,
            ],
            'showImages' => [
                'title'   => 'Bilder anzeigen',
                'type'    => 'checkbox',
                'default' => true,
            ],
            'getEventByUrlIdParam' => [
                'title'       => 'Einzel-Event über ?id=',
                'description' => 'Zeigt ein einzelnes Event anhand des URL-Parameters id/handle.',
                'type'        => 'checkbox',
                'default'     => false,
            ],
        ];
    }

    /**
     * Bewusst leer: keine Arbeit bei Seitenaufbau. Die eigentliche Abfrage
     * passiert lazy in events().
     */
    public function onRun()
    {
    }

    /**
     * Liefert die Events anhand der Komponenten-Properties. Ergebnis wird pro
     * Request gecached, damit mehrfache Template-Zugriffe nicht erneut abfragen.
     */
    public function events()
    {
        if ($this->eventsCache !== null) {
            return $this->eventsCache;
        }

        // Einzel-Event-Modus über URL-Parameter (?id= oder ?handle=)
        if ($this->propBool('getEventByUrlIdParam')) {
            $id     = trim((string) input('id', ''));
            $handle = trim((string) input('handle', ''));
            $query  = Event::with(['calendar', 'images']);
            if ($id !== '') {
                $query->where('id', $id);
            } elseif ($handle !== '') {
                $query->where('slug', $handle);
            } else {
                return $this->eventsCache = collect();
            }
            return $this->eventsCache = $query->orderBy('starts_at', 'asc')->get();
        }

        $active = (string) $this->property('active', 'true');
        $limit  = (int) $this->property('limit', 0);

        $query = Event::with(['calendar', 'images'])
            ->applyType((string) $this->property('type', 'all'))
            ->applyCalendar((string) $this->property('calendar', 'all'))
            ->excludeCalendar((string) $this->property('excludeCalendar', 'none'))
            ->applyStartTime((string) $this->property('startTime', 'future'))
            ->orderBy('starts_at', 'asc');

        if ($active === 'true' || $active === '1') {
            $query->isActive();
        }
        if ($limit > 0) {
            $query->limit($limit);
        }

        return $this->eventsCache = $query->get();
    }

    public function isEmpty()
    {
        return $this->events()->isEmpty();
    }

    public function startTime()
    {
        return (string) $this->property('startTime', 'future');
    }

    public function showBookButton()
    {
        return $this->propBool('showBookButton');
    }

    public function showImages()
    {
        return $this->propBool('showImages');
    }

    /**
     * Event-ID, für die soeben serverseitig erfolgreich gebucht wurde
     * (Query-Flag jl_booked nach dem Redirect aus Api::bookForm). 0 = keine.
     */
    public function bookedId()
    {
        return (int) input('jl_booked', 0);
    }

    /**
     * Event-ID, deren serverseitige Buchung fehlgeschlagen ist (jl_error). 0 = keine.
     */
    public function errorId()
    {
        return (int) input('jl_error', 0);
    }

    protected function propBool($name)
    {
        $v = $this->property($name);
        return $v === true || $v === 1 || $v === '1' || $v === 'true';
    }

    /**
     * AJAX-Handler für die Buchungsanfrage (serverseitig, Winter-Framework).
     * Nutzt denselben BookingService wie die JSON-API.
     */
    public function onBook()
    {
        $result = BookingService::create((array) post());

        if (!$result['success']) {
            if (!empty($result['disabled'])) {
                throw new \ApplicationException('Buchungen sind derzeit deaktiviert.');
            }
            $messages = [];
            foreach ($result['errors'] as $fieldErrors) {
                foreach ((array) $fieldErrors as $msg) {
                    $messages[] = $msg;
                }
            }
            throw new \ApplicationException($messages
                ? implode(' ', $messages)
                : 'Bitte überprüfen Sie Ihre Eingaben.');
        }

        $eventId = (int) post('event_id');
        return [
            '#jl-book-result-' . $eventId =>
                '<div class="alert alert-success mb-0" role="alert">'
                . 'Vielen Dank! Ihre Anfrage wurde abgeschickt – wir melden uns in Kürze bei Ihnen.'
                . '</div>',
        ];
    }
}
