<?php namespace JumpLink\Events\Console;

use Carbon\Carbon;
use Illuminate\Console\Command;
use JumpLink\Events\Models\Calendar;
use JumpLink\Events\Models\Event;

/**
 * ImportFirestore – importiert Kalender & Events aus der Firestore-REST-API
 * des alten jumplink-events-Projekts in die lokale Datenbank.
 *
 * Idempotent: Datensätze werden über ihre firestore_id wiedererkannt und
 * aktualisiert. Bilder werden aus dem bereits migrierten lokalen Media-Ordner
 * (storage/app/media/events/images) als File-Attachments angehängt.
 */
class ImportFirestore extends Command
{
    protected $signature = 'jumplink:events-import
                            {--dry-run : Nur anzeigen, nichts schreiben}
                            {--force : Vorhandene Datensätze überschreiben (inkl. Bilder neu anhängen)}
                            {--project=jumplink-events : Firebase-Projekt-ID}
                            {--key=AIzaSyDrLQEPT31BcsK0L-yFFuAJmolAJZ3E7ac : Web-API-Key}
                            {--domain=watt-land-fluss.de : customerDomain}
                            {--media=/var/www/winter/storage/app/media/events/images : Pfad zu den migrierten Bildern}';

    protected $description = 'Importiert Kalender und Events aus Firestore in die lokale Datenbank.';

    protected $dryRun = false;
    protected $force = false;

    public function handle()
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->force  = (bool) $this->option('force');

        $this->info('JumpLink Events – Firestore-Import');
        $this->line('  Projekt:  ' . $this->option('project'));
        $this->line('  Domain:   ' . $this->option('domain'));
        $this->line('  Modus:    ' . ($this->dryRun ? 'DRY-RUN' : ($this->force ? 'FORCE' : 'normal')));
        $this->line('');

        $calMap = $this->importCalendars();
        $this->importEvents($calMap);

        $this->line('');
        $this->info('Import abgeschlossen.');
        return 0;
    }

    /**
     * @return array firestore-calendar-name => Calendar (lokal)
     */
    protected function importCalendars()
    {
        $docs = $this->fetchCollection('calendars');
        $this->info(count($docs) . ' Kalender gefunden.');
        $map = [];

        foreach ($docs as $doc) {
            $f = $this->fields($doc);
            $fid = $this->docId($doc);
            $name = $f['name'] ?? null;
            if (!$name) {
                continue;
            }

            $attrs = [
                'name'        => $name,
                'title'       => $f['title'] ?? null,
                'subtitle'    => $f['subtitle'] ?? null,
                'description' => $f['description'] ?? null,
                'note'        => $f['note'] ?? null,
                'type'        => $f['type'] ?? 'events',
                'is_active'   => array_key_exists('active', $f) ? (bool) $f['active'] : true,
            ];

            $cal = Calendar::where('firestore_id', $fid)->orWhere('name', $name)->first();

            if ($cal && !$this->force) {
                $this->line("  = Kalender '{$name}' existiert bereits (übersprungen).");
            } else {
                if ($this->dryRun) {
                    $this->line("  + Kalender '{$name}' " . ($cal ? '(update)' : '(neu)'));
                } else {
                    if (!$cal) {
                        $cal = new Calendar;
                    }
                    $cal->fill($attrs);
                    $cal->firestore_id = $fid;
                    $cal->save();
                    $this->line("  + Kalender '{$name}' gespeichert (id={$cal->id}).");
                }
            }

            if ($cal) {
                $map[$name] = $cal;
            }
        }

        return $map;
    }

    protected function importEvents($calMap)
    {
        $docs = $this->fetchCollection('events');
        $this->info(count($docs) . ' Events gefunden.');

        $created = 0; $updated = 0; $skipped = 0; $imagesAttached = 0;

        foreach ($docs as $doc) {
            $f = $this->fields($doc);
            $fid = $this->docId($doc);
            $title = $f['title'] ?? null;
            if (!$title) {
                continue;
            }

            $existing = Event::where('firestore_id', $fid)->first();
            if ($existing && !$this->force) {
                $skipped++;
                continue;
            }

            $calName = $f['calendar'] ?? null;
            // Referenzierten Kalender anlegen, falls in Firestore kein eigenes
            // Kalender-Dokument existiert (das alte Frontend kannte Watt/Land/
            // Fluss/Spezial fest, ohne dass alle als Dokument vorlagen).
            if ($calName && !isset($calMap[$calName]) && !$this->dryRun) {
                $newCal = Calendar::firstOrCreate(['name' => $calName], ['is_active' => true, 'type' => 'events']);
                $calMap[$calName] = $newCal;
                $this->line("  + Kalender '{$calName}' automatisch angelegt (von Event referenziert).");
            }
            $calId = ($calName && isset($calMap[$calName])) ? $calMap[$calName]->id : null;

            $attrs = [
                'calendar_id'   => $calId,
                'title'         => $title,
                'subtitle'      => $f['subtitle'] ?? null,
                'description'   => $f['description'] ?? null,
                'type'          => in_array(($f['type'] ?? 'fix'), ['fix', 'variable']) ? $f['type'] : 'fix',
                'starts_at'     => $this->toDate($f['startAt'] ?? null),
                'ends_at'       => $this->toDate($f['endAt'] ?? null),
                'show_times'    => array_key_exists('showTimes', $f) ? (bool) $f['showTimes'] : true,
                'offer'         => $f['offer'] ?? null,
                'location'      => $f['location'] ?? null,
                'equipment'     => $f['equipment'] ?? null,
                'note'          => $f['note'] ?? null,
                'pricetext'     => $f['pricetext'] ?? null,
                'prices'        => $this->normalizePrices($f['prices'] ?? []),
                'notifications' => $this->normalizeNotifications($f['notifications'] ?? []),
                'is_active'     => array_key_exists('active', $f) ? (bool) $f['active'] : true,
            ];

            if ($this->dryRun) {
                $imgCount = is_array($f['images'] ?? null) ? count($f['images']) : 0;
                $this->line("  + Event '{$title}' [{$attrs['type']}] cal={$calName} bilder={$imgCount} " . ($existing ? '(update)' : '(neu)'));
                continue;
            }

            $event = $existing ?: new Event;
            $event->fill($attrs);
            // Slug aus altem handle übernehmen, sonst aus Titel
            if (!empty($f['handle'])) {
                $event->slug = $f['handle'];
            }
            $event->firestore_id = $fid;
            $event->save();

            $existing ? $updated++ : $created++;

            $imagesAttached += $this->attachImages($event, $f['images'] ?? []);
        }

        $this->line('');
        $this->info("Events: {$created} neu, {$updated} aktualisiert, {$skipped} übersprungen, {$imagesAttached} Bilder angehängt.");
    }

    /**
     * Hängt die migrierten lokalen Bilder an ein Event an.
     */
    protected function attachImages($event, $images)
    {
        if (!is_array($images) || empty($images)) {
            return 0;
        }

        // bei force vorhandene Attachments entfernen, um Duplikate zu vermeiden
        if ($this->force && $event->images()->count() > 0) {
            $event->images()->delete();
        } elseif ($event->images()->count() > 0) {
            return 0; // bereits Bilder dran -> nicht doppelt anhängen
        }

        $mediaDir = rtrim($this->option('media'), '/');
        $count = 0;
        $sort = 0;

        foreach ($images as $img) {
            $name = $this->imageBasename($img);
            if (!$name) {
                continue;
            }
            $path = $mediaDir . '/' . $name;
            if (!is_file($path)) {
                $this->warn("    ! Bild fehlt lokal: {$name} (bitte tools/migrate-event-images.js laufen lassen)");
                continue;
            }

            try {
                $file = $event->images()->create([
                    'data'     => $path,
                    'title'    => $name,
                    'is_public' => true,
                ]);
                // Sortierung & sprechender Dateiname
                $file->file_name = $name;
                $file->sort_order = $sort++;
                $file->save();
                $count++;
            } catch (\Throwable $e) {
                $this->warn("    ! Bild '{$name}' konnte nicht angehängt werden: " . $e->getMessage());
            }
        }

        return $count;
    }

    /**
     * Dateiname eines Bildes aus metadata.name oder downloadURL ableiten.
     */
    protected function imageBasename($img)
    {
        if (!is_array($img)) {
            return null;
        }
        if (!empty($img['metadata']['name'])) {
            return $img['metadata']['name'];
        }
        if (!empty($img['downloadURL']) && preg_match('#/o/([^?]+)#', $img['downloadURL'], $m)) {
            $objectPath = urldecode($m[1]);
            return substr($objectPath, strrpos($objectPath, '/') + 1);
        }
        return null;
    }

    protected function normalizePrices($prices)
    {
        $out = [];
        foreach ((array) $prices as $p) {
            if (!is_array($p)) {
                continue;
            }
            $out[] = [
                'min'                => (int) ($p['min'] ?? 1),
                'max'                => (int) ($p['max'] ?? 1),
                'price'              => (float) ($p['price'] ?? 0),
                'fixprice'           => (float) ($p['fixprice'] ?? 0),
                // Altdaten enthalten 'unit' vereinzelt als verschachteltes Objekt
                // statt String – auf einen Skalar reduzieren (siehe Event-Model).
                'unit'               => is_array($p['unit'] ?? null)
                    ? ($p['unit']['unit'] ?? 'person')
                    : ($p['unit'] ?? 'person'),
                'eachAdditionalUnit' => (bool) ($p['eachAdditionalUnit'] ?? false),
            ];
        }
        return $out;
    }

    protected function normalizeNotifications($notifications)
    {
        $out = [];
        foreach ((array) $notifications as $n) {
            if (!is_array($n)) {
                continue;
            }
            if (!empty($n['email'])) {
                $out[] = ['name' => $n['name'] ?? null, 'email' => $n['email']];
            }
        }
        return $out;
    }

    //
    // Firestore-REST-Helfer
    //

    protected function fetchCollection($collection)
    {
        $base = "https://firestore.googleapis.com/v1/projects/{$this->option('project')}/databases/(default)/documents/customerDomains/{$this->option('domain')}/{$collection}";
        $docs = [];
        $pageToken = '';
        do {
            $query = http_build_query(array_filter([
                'key'       => $this->option('key'),
                'pageSize'  => 300,
                'pageToken' => $pageToken ?: null,
            ]));
            $url = $base . '?' . $query;

            $raw = @file_get_contents($url, false, stream_context_create([
                'http' => ['timeout' => 30, 'ignore_errors' => true],
                'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
            ]));

            if ($raw === false) {
                $this->error("Firestore-Abfrage '{$collection}' fehlgeschlagen (keine Antwort).");
                break;
            }
            $json = json_decode($raw, true);
            if (isset($json['error'])) {
                $this->error("Firestore-Fehler '{$collection}': " . ($json['error']['message'] ?? 'unbekannt'));
                break;
            }
            if (!empty($json['documents'])) {
                $docs = array_merge($docs, $json['documents']);
            }
            $pageToken = $json['nextPageToken'] ?? '';
        } while ($pageToken);

        return $docs;
    }

    protected function docId($doc)
    {
        $name = $doc['name'] ?? '';
        return substr($name, strrpos($name, '/') + 1);
    }

    /**
     * Wandelt die typisierten Firestore-Felder eines Dokuments in ein PHP-Array.
     */
    protected function fields($doc)
    {
        $out = [];
        foreach (($doc['fields'] ?? []) as $key => $value) {
            $out[$key] = $this->value($value);
        }
        return $out;
    }

    /**
     * Konvertiert einen einzelnen typisierten Firestore-Wert.
     */
    protected function value($v)
    {
        if (!is_array($v)) {
            return $v;
        }
        if (array_key_exists('nullValue', $v)) {
            return null;
        }
        if (array_key_exists('stringValue', $v)) {
            return $v['stringValue'];
        }
        if (array_key_exists('booleanValue', $v)) {
            return (bool) $v['booleanValue'];
        }
        if (array_key_exists('integerValue', $v)) {
            return (int) $v['integerValue'];
        }
        if (array_key_exists('doubleValue', $v)) {
            return (float) $v['doubleValue'];
        }
        if (array_key_exists('timestampValue', $v)) {
            return $v['timestampValue']; // ISO-String, später via toDate()
        }
        if (array_key_exists('mapValue', $v)) {
            $out = [];
            foreach (($v['mapValue']['fields'] ?? []) as $k => $inner) {
                $out[$k] = $this->value($inner);
            }
            return $out;
        }
        if (array_key_exists('arrayValue', $v)) {
            $out = [];
            foreach (($v['arrayValue']['values'] ?? []) as $inner) {
                $out[] = $this->value($inner);
            }
            return $out;
        }
        return null;
    }

    protected function toDate($value)
    {
        if (!$value) {
            return null;
        }
        try {
            $d = Carbon::parse($value);
            // Firestore-Defaults (Epoch/1970) als "kein Datum" behandeln
            if ($d->year <= 1970) {
                return null;
            }
            return $d;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
