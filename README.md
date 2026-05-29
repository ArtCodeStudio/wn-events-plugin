# JumpLink.Events – Winter CMS Plugin

Manage **events / guided tours ("Führungen")** in [Winter CMS](https://wintercms.com)
(a fork of OctoberCMS): calendars, events with tiered/scale prices and image
galleries, plus a frontend booking flow that stores requests and sends
confirmation emails.

This plugin was built to replace a previous **client-side Firebase/Firestore**
solution with a fully local, server-side implementation. It ships a JSON API
(in the legacy data shape) and a one-off Firestore import command to ease that
migration.

## Features

- **Calendars** – backend-managed groups (e.g. *Watt / Land / Fluss / Spezial*)
  with title, colour, description and images.
- **Events** – title, slug, type (`fix` = scheduled / `variable` = on request),
  start/end datetime, location, equipment, notes, an image gallery and
  **scale prices** (per-person price, group fix price, min/max participants,
  "each additional unit"). A per-event **"show price"** switch controls whether
  a price is displayed at all.
- **Bookings** – booking requests are stored in the database (backend list with
  status workflow + new-request counter) **and** emailed to the organiser, with
  an optional confirmation copy to the customer.
- **Frontend JSON API** – public read endpoints for calendars/events plus a
  booking endpoint (honeypot + server-side validation + rate limit).
- **Settings** – default notification recipient, sender, customer-copy toggle.
- **Import command** – pull existing calendars/events from a Firestore project.

## Requirements

- Winter CMS (tested on v1.2 / Laravel 9 / PHP 8.4)

## Installation

Place the plugin in `plugins/jumplink/events` (or require via Composer) and run:

```bash
php artisan winter:up
```

## Backend

A **Führungen / Events** main menu provides:

- **Events** – list/create/edit with tabbed form (details, prices, gallery,
  notification recipients, bookings).
- **Calendars** – list/create/edit, drag-to-reorder.
- **Bookings** – all requests with status filter and search.

Settings live under **Settings → Events**.

## Frontend JSON API

Public routes (no CSRF, API style):

| Method | Endpoint | Purpose |
|---|---|---|
| `GET`  | `/api/jumplink/events/calendars` | active calendars |
| `GET`  | `/api/jumplink/events/events`    | filtered events (legacy Firestore JSON shape) |
| `POST` | `/api/jumplink/events/book`      | create a booking request |

`GET /events` query parameters: `type` (`fix`\|`variable`\|`all`), `calendar`,
`excludeCalendar`, `active` (`true`\|`false`\|`all`), `startTime`
(`future`\|`past`\|`all`), `limit`, and for detail lookups `id` / `handle` /
`title`.

`POST /book` fields: `event_id` or `handle`, `firstname`, `lastname`, `email`,
`phone`, `quantity`, `date`, `street`, `zip`, `message`. Leave the hidden
`website` field empty (honeypot).

Events are returned in the shape the original Firebase frontend expected
(`id`, `handle`, `title`, `type`, `calendar`, `startAt`/`endAt` ISO strings,
`prices`, `notifications`, `images[{src, w, h, …}]`, `showPrice`, …), so an
existing rivets/JS frontend can switch data source without template changes.

## Firestore import (migration helper)

```bash
php artisan jumplink:events-import --dry-run     # preview
php artisan jumplink:events-import               # import (idempotent via firestore_id)
php artisan jumplink:events-import --force        # re-import / overwrite
```

Options: `--project`, `--key`, `--domain`, `--media` (path to already-downloaded
images that get attached to the imported events). The Firebase web API key is a
public client identifier; restrict access via Firebase security rules.

## License

MIT. Built by [JumpLink / Art+Code Studio](https://artandcode.studio).
