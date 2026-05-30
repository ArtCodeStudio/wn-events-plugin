<?php

/**
 * Frontend-JSON-API für das JumpLink.Events-Plugin.
 *
 * Bewusst ohne CSRF/Session (API-Stil) – Lese-Endpunkte sind öffentlich,
 * der Buchungs-Endpunkt ist per Honeypot + serverseitiger Validierung
 * abgesichert. Wird vom rivets-Frontend statt der Firestore-Zugriffe genutzt.
 */

use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'api/jumplink/events',
], function () {

    Route::get('calendars', [\JumpLink\Events\Classes\Api::class, 'calendars']);
    Route::get('events', [\JumpLink\Events\Classes\Api::class, 'events']);
    Route::match(['post', 'options'], 'book', [\JumpLink\Events\Classes\Api::class, 'book'])
        ->middleware('throttle:20,1'); // max. 20 Buchungsanfragen pro Minute/IP

    // Serverseitiger Buchungsweg (klassischer Form-POST + Redirect) – funktioniert
    // ohne JS und unabhängig vom Komponenten-AJAX. Gleiche Logik via BookingService.
    Route::post('book-form', [\JumpLink\Events\Classes\Api::class, 'bookForm'])
        ->middleware('throttle:20,1');

});
