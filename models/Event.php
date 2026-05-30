<?php namespace JumpLink\Events\Models;

use Model;
use Carbon\Carbon;

/**
 * Event Model – ersetzt die Firestore-Collection customerDomains/<domain>/events
 */
class Event extends Model
{
    use \Winter\Storm\Database\Traits\Validation;
    use \Winter\Storm\Database\Traits\Sluggable;
    use \Winter\Storm\Database\Traits\Sortable;

    public $table = 'jumplink_events_events';

    /**
     * Slug ("handle") wird aus dem Titel generiert.
     */
    protected $slugs = ['slug' => 'title'];

    public $rules = [
        'title' => 'required|min:2',
        'type'  => 'required|in:fix,variable',
    ];

    public $fillable = [
        'calendar_id', 'title', 'subtitle', 'slug', 'description', 'type',
        'starts_at', 'ends_at', 'show_times', 'offer', 'location', 'equipment',
        'note', 'pricetext', 'prices', 'notifications', 'is_active',
        'sort_order', 'firestore_id', 'show_price',
    ];

    protected $casts = [
        'show_times' => 'boolean',
        'is_active'  => 'boolean',
        'show_price' => 'boolean',
    ];

    /**
     * Staffelpreise und Benachrichtigungen als JSON-Arrays gespeichert.
     */
    protected $jsonable = ['prices', 'notifications'];

    protected $dates = ['starts_at', 'ends_at'];

    public $belongsTo = [
        'calendar' => [\JumpLink\Events\Models\Calendar::class],
    ];

    public $hasMany = [
        'bookings' => [\JumpLink\Events\Models\Booking::class],
    ];

    public $attachMany = [
        'images' => [\System\Models\File::class, 'order' => 'sort_order'],
    ];

    //
    // Defensive Normalisierung der jsonable-Felder
    //
    // Aus Altdaten (Firestore-Import) können in eigentlich skalaren Feldern
    // verschachtelte Arrays stecken (z. B. prices[].unit als Objekt statt
    // String). Das Backend-Repeater-Formular rendert solche Felder als Text
    // und stürzt dann mit "Array to string conversion" ab. Wir bereinigen den
    // rohen JSON-Wert sowohl beim Laden (afterFetch – schützt das Rendern
    // bestehender, kaputter Datensätze) als auch beim Speichern (beforeSave).
    // Bewusst KEIN getXxxAttribute-Mutator: bei jsonable-Feldern bekäme der die
    // rohe JSON-Zeichenkette, und Winter würde das vom Mutator zurückgegebene
    // Array anschließend erneut durch json_decode() jagen (TypeError).
    //

    public function afterFetch()
    {
        $this->normalizeJsonableAttributes();
    }

    public function beforeSave()
    {
        $this->normalizeJsonableAttributes();
    }

    protected function normalizeJsonableAttributes()
    {
        $this->normalizeJsonableAttribute('prices', 'sanitizePrices');
        $this->normalizeJsonableAttribute('notifications', 'sanitizeNotifications');
    }

    /**
     * Bereinigt ein jsonable-Feld nur dann (und schreibt den rohen Wert nur dann
     * zurück), wenn die Sanitierung tatsächlich etwas verändert hat – sonst
     * würde ein sauberer Datensatz allein durch erneutes json_encode als "dirty"
     * markiert.
     */
    protected function normalizeJsonableAttribute($key, $sanitizer)
    {
        $decoded   = $this->decodeRawJsonable($key);
        $sanitized = $this->{$sanitizer}($decoded);
        if ($sanitized !== $decoded) {
            $this->attributes[$key] = $this->encodeJsonable($sanitized);
        }
    }

    protected function decodeRawJsonable($key)
    {
        if (!array_key_exists($key, $this->attributes)) {
            return [];
        }
        $raw = $this->attributes[$key];
        if (is_array($raw)) {
            return $raw;
        }
        if ($raw === null || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    protected function encodeJsonable($value)
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function sanitizePrices($prices)
    {
        if (!is_array($prices)) {
            return [];
        }
        $out = [];
        foreach ($prices as $p) {
            if (!is_array($p)) {
                continue;
            }
            if (isset($p['unit'])) {
                $p['unit'] = static::scalarize($p['unit'], 'unit', 'person');
            }
            $out[] = $p;
        }
        return $out;
    }

    protected function sanitizeNotifications($items)
    {
        if (!is_array($items)) {
            return [];
        }
        $out = [];
        foreach ($items as $n) {
            if (!is_array($n)) {
                continue;
            }
            if (isset($n['name'])) {
                $n['name'] = static::scalarize($n['name'], 'name');
            }
            if (isset($n['email'])) {
                $n['email'] = static::scalarize($n['email'], 'email');
            }
            $out[] = $n;
        }
        return $out;
    }

    /**
     * Reduziert einen evtl. verschachtelten Wert auf einen Skalar: bevorzugt den
     * passenden Schlüssel, sonst den ersten skalaren Wert, sonst den Default.
     */
    protected static function scalarize($value, $preferKey = null, $default = null)
    {
        if (is_array($value)) {
            if ($preferKey !== null && isset($value[$preferKey]) && is_scalar($value[$preferKey])) {
                return $value[$preferKey];
            }
            foreach ($value as $v) {
                if (is_scalar($v)) {
                    return $v;
                }
            }
            return $default;
        }
        return $value ?? $default;
    }

    //
    // Scopes
    //

    public function scopeIsActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeApplyType($query, $type)
    {
        // Wie im alten Frontend: sowohl 'fix' als auch 'variable' filtern nach
        // dem Typ; nur 'all' (oder leer) liefert alle Typen.
        if ($type === 'fix' || $type === 'variable') {
            return $query->where('type', $type);
        }
        return $query;
    }

    public function scopeApplyCalendar($query, $calendar)
    {
        if ($calendar && $calendar !== 'all' && $calendar !== 'none') {
            return $query->whereHas('calendar', function ($q) use ($calendar) {
                $q->where('name', $calendar);
            });
        }
        return $query;
    }

    public function scopeExcludeCalendar($query, $calendar)
    {
        if ($calendar && $calendar !== 'none' && $calendar !== 'all') {
            return $query->whereDoesntHave('calendar', function ($q) use ($calendar) {
                $q->where('name', $calendar);
            });
        }
        return $query;
    }

    /**
     * Zeitfilter analog zur alten startTime-Logik (future/past/all).
     * Greift nur für "fix"-Events; "variable" sind terminunabhängig.
     */
    public function scopeApplyStartTime($query, $startTime)
    {
        if ($startTime === 'future') {
            return $query->where(function ($q) {
                $q->where('starts_at', '>=', Carbon::now())
                  ->orWhere('type', 'variable');
            });
        }
        if ($startTime === 'past') {
            return $query->where('starts_at', '<', Carbon::now())
                         ->where('type', '!=', 'variable');
        }
        return $query;
    }

    //
    // Preisberechnung (Portierung von jumplink.events.calcEventTotal)
    //

    /**
     * Liefert die zur Personenzahl passende Preisstufe.
     */
    public function getScalePrice($quantity)
    {
        $prices = $this->prices ?: [];
        foreach ($prices as $price) {
            $min = (int) ($price['min'] ?? 1);
            $max = (int) ($price['max'] ?? PHP_INT_MAX);
            if ($quantity >= $min && $quantity <= $max) {
                return $price;
            }
        }
        return null;
    }

    /**
     * Gesamtpreis berechnen – identische Logik wie das alte Frontend:
     *   eachAdditionalUnit ? menge - (min-1) : menge
     *   total = fixprice + price * menge
     */
    public function calcTotal($quantity)
    {
        $price = $this->getScalePrice($quantity);
        if (!$price) {
            return null;
        }
        $unitPrice = (float) ($price['price'] ?? 0);
        $fixprice  = (float) ($price['fixprice'] ?? 0);
        $min       = (int) ($price['min'] ?? 1);
        $each      = !empty($price['eachAdditionalUnit']);

        $qtyToCalc = $each ? max(0, $quantity - ($min - 1)) : $quantity;

        return round($fixprice + $unitPrice * $qtyToCalc, 2);
    }

    /**
     * Erlaubte Personen-Spanne über alle Preisstufen (für Validierung/Spinner).
     */
    public function getQuantityRange()
    {
        $prices = $this->prices ?: [];
        $min = null; $max = null;
        foreach ($prices as $p) {
            $pMin = (int) ($p['min'] ?? 1);
            $pMax = (int) ($p['max'] ?? 1);
            $min = is_null($min) ? $pMin : min($min, $pMin);
            $max = is_null($max) ? $pMax : max($max, $pMax);
        }
        return ['min' => $min ?? 1, 'max' => $max ?? 1];
    }

    //
    // Serialisierung in das Legacy-Firestore-Format für die rivets-Frontend-API
    //

    /**
     * Bildmaße ermitteln (System\Models\File speichert sie nicht). Ergebnis wird
     * pro Datei gecached; Fallback 800x600 falls die Datei nicht lesbar ist.
     */
    protected function getImageDimensions($file)
    {
        $default = ['width' => 800, 'height' => 600];
        try {
            $path = $file->getLocalPath();
            if ($path && is_readable($path)) {
                $size = @getimagesize($path);
                if ($size && $size[0] > 0) {
                    return ['width' => $size[0], 'height' => $size[1]];
                }
            }
        } catch (\Throwable $e) {
            // ignorieren -> Fallback
        }
        return $default;
    }

    public function toLegacyArray()
    {
        $images = [];
        foreach ($this->images as $file) {
            $dim = $this->getImageDimensions($file);
            $images[] = [
                'src'            => $file->getPath(),
                'downloadURL'    => $file->getPath(),
                'metadata'       => ['name' => $file->file_name],
                'customMetadata' => ['width' => $dim['width'], 'height' => $dim['height']],
                'title'          => $file->title ?: $file->file_name,
                'w'              => $dim['width'],
                'h'              => $dim['height'],
            ];
        }

        return [
            'id'            => (string) $this->id,
            'active'        => (bool) $this->is_active,
            'handle'        => $this->slug,
            'title'         => $this->title,
            'subtitle'      => $this->subtitle,
            'description'   => $this->description,
            'type'          => $this->type,
            'calendar'      => $this->calendar ? $this->calendar->name : null,
            // ISO-8601, das Frontend parst es zu einem Date-Objekt
            'startAt'       => $this->starts_at ? $this->starts_at->toIso8601String() : null,
            'endAt'         => $this->ends_at ? $this->ends_at->toIso8601String() : null,
            'showTimes'     => (bool) $this->show_times,
            'prices'        => $this->prices ?: [],
            'pricetext'     => $this->pricetext,
            'showPrice'     => (bool) $this->show_price,
            'notifications' => $this->notifications ?: [],
            'images'        => $images,
            'offer'         => $this->offer,
            'location'      => $this->location,
            'equipment'     => $this->equipment,
            'note'          => $this->note,
        ];
    }
}
