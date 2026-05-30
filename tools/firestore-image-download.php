<?php
/**
 * firestore-image-download.php – node-freier Ersatz fuer migrate-event-images.js.
 * Laedt alle Event-Bilder eines customerDomain aus dem oeffentlichen Firestore-
 * REST-API + Firebase-Storage downloadURLs in einen lokalen Ordner. Idempotent.
 *   php firestore-image-download.php --domain=mahlzeit-am-meer.de --out=/pfad [--dry-run]
 */
$o = getopt('', ['domain:', 'out:', 'project::', 'key::', 'dry-run']);
$project = $o['project'] ?? 'jumplink-events';
$key     = $o['key']     ?? 'AIzaSyDrLQEPT31BcsK0L-yFFuAJmolAJZ3E7ac';
$domain  = $o['domain']  ?? 'mahlzeit-am-meer.de';
$out     = rtrim($o['out'] ?? __DIR__, '/');
$dry     = array_key_exists('dry-run', $o);
@mkdir($out, 0775, true);
$ctx = stream_context_create(['http' => ['timeout' => 60, 'ignore_errors' => true]]);
$base = "https://firestore.googleapis.com/v1/projects/$project/databases/(default)/documents/customerDomains/$domain/events";
$docs = []; $pageToken = '';
do {
    $url = $base.'?key='.urlencode($key).'&pageSize=300'.($pageToken ? '&pageToken='.urlencode($pageToken) : '');
    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) { fwrite(STDERR, "Firestore-Abfrage fehlgeschlagen\n"); exit(1); }
    $j = json_decode($raw, true);
    if (isset($j['error'])) { fwrite(STDERR, 'Firestore-Fehler: '.($j['error']['message'] ?? '?')."\n"); exit(1); }
    if (!empty($j['documents'])) $docs = array_merge($docs, $j['documents']);
    $pageToken = $j['nextPageToken'] ?? '';
} while ($pageToken);
$byName = [];
foreach ($docs as $d) {
    $vals = $d['fields']['images']['arrayValue']['values'] ?? [];
    foreach ($vals as $v) {
        $f = $v['mapValue']['fields'] ?? null; if (!$f) continue;
        $dl = $f['downloadURL']['stringValue'] ?? null; if (!$dl) continue;
        if (!preg_match('#/o/([^?]+)#', $dl, $m)) continue;
        $obj = urldecode($m[1]);
        $name = substr($obj, strrpos($obj, '/') + 1);
        if (!isset($byName[$name])) $byName[$name] = $dl;
    }
}
echo count($docs)." Events, ".count($byName)." eindeutige Bilder\n";
$dlc=0; $skip=0; $fail=0; $bytes=0;
foreach ($byName as $name => $dl) {
    $dest = "$out/$name";
    if (is_file($dest) && filesize($dest) > 0) { $skip++; continue; }
    if ($dry) { echo "  dry  $name\n"; continue; }
    $data = @file_get_contents($dl, false, $ctx);
    if ($data === false || strlen($data) === 0) { echo "  FAIL $name\n"; $fail++; continue; }
    file_put_contents($dest, $data); $bytes += strlen($data); $dlc++;
}
echo "Heruntergeladen: $dlc, uebersprungen: $skip, fehlgeschlagen: $fail, ".round($bytes/1048576, 1)." MB\n";
