# Nexus Radio

A self-hostable web radio station app. Laravel + Liquidsoap + Icecast under
the hood, with an admin UI for managing your music library, scheduling
themed shows, and running the station live. Genre-agnostic — bring your own
catalog and rotation rules. 

Pairs conceptually with [Nexus.tv](https://github.com/j88per-cell) (a
sibling project for movies/TV), but runs standalone.

## What it does

- **Free play**: continuous rotation from your library, weighted toward
  least-played tracks, with per-track/per-artist cooldowns and optional
  genre filtering.
- **Shows**: scheduled, recurring blocks with a curator-picked playlist
  (e.g. a themed set that only cycles through a hand-picked group of
  artists), running on a daily/weekly/interval schedule and pausing free
  play while live.
- **Manual DJ mode**: a human can program the running order for a live
  session and go live over mic input, mixed into the stream.
- **Admin UI**: manage songs, artists, people, band memberships, releases,
  credits, and connections; curate shows; monitor the live queue and station
  health.

## Stack

- Laravel 13 (PHP 8.3). Database is sqlite by default (zero extra services);
  Postgres works too, just set `DB_CONNECTION=pgsql` and the usual `DB_*`
  vars.
- Inertia.js + Vue 3, Tailwind CSS, Vite
- [Liquidsoap](https://www.liquidsoap.info/) for stream assembly, output to
  Icecast
- Optional: [Navidrome](https://www.navidrome.org/) as the music source
- Optional for production: Redis for sessions/cache/queue (defaults to
  file-based sessions/cache and a synchronous queue, so nothing extra is
  required to get started)

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # only if using the default sqlite connection
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Configure `.env` for your database, Navidrome instance (if used), and
Liquidsoap connection details. Point `liquidsoap/radio.liq` at your Icecast
server — station name/description/genre are read from `STATION_NAME`,
`STATION_DESCRIPTION`, and `STATION_GENRE` in the environment.

### Logging in

There's no password login — it's magic-link only. `--seed` above creates a
default user:

- **Email**: `test@example.com`

Enter that on the login page and it emails a signed sign-in link. With no
real mailer configured (`MAIL_MAILER=log`, the default), that email is
written to `storage/logs/laravel.log` instead of actually sending — open the
log, find the link, and visit it directly. Links expire after 15 minutes.

## License

AGPL-3.0. See [LICENSE](LICENSE).
