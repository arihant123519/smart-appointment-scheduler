# Local Dev Startup Checklist

What to start, in what order, every time you sit down to work on this project.

## 1. One-time setup (only the first time, or after a fresh clone)

```bash
composer install
npm install
cp .env.example .env      # skip if .env already exists
php artisan key:generate
php artisan migrate
```

Make sure `.env` has these set correctly for local dev:
- `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=smart_appointment_scheduler`
- `QUEUE_CONNECTION=database`
- `CACHE_STORE=database`
- `SESSION_DRIVER=database`
- `BROADCAST_CONNECTION=reverb` + `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET`
- `OPENAI_API_KEY` (used by the AI no-show scoring / assistant features)

## 2. Every time you start working

Start these, in this order:

### Step 1 — MySQL
Start **XAMPP Control Panel** → start **MySQL** (Apache is not required unless you specifically want to serve via XAMPP's vhost instead of `artisan serve`).

### Step 2 — App, queue worker, logs, and frontend assets (one command)
This project already has a combined dev script wired up in `composer.json`:

```bash
composer run dev
```

This runs, all at once (via `concurrently`):
- `php artisan serve` — the app at `http://localhost:8000`
- `php artisan queue:listen --tries=1` — processes queued jobs (emails, WhatsApp, notifications, AI scoring, etc. — required because `QUEUE_CONNECTION=database`, not `sync`)
- `php artisan pail` — live log tailing in the same terminal
- `npm run dev` — Vite, for hot-reloading CSS/JS

### Step 3 — Reverb (WebSocket server)
Needed separately because `BROADCAST_CONNECTION=reverb` and it isn't part of the `composer run dev` script:

```bash
php artisan reverb:start
```

Without this running, any real-time/broadcast features (live updates, notifications pushed to the browser) silently won't work — no error, they just won't fire.

### Step 4 — Scheduler (only if you're testing reminders/notifications/recall flows)
The app has several scheduled commands in `routes/console.php` (appointment reminders, no-show follow-ups, recall/review dispatch, deposit release, etc.). These don't run on their own locally — in production a cron hits `schedule:run` every minute, but locally you need:

```bash
php artisan schedule:work
```

Skip this if you're not actively testing reminder/notification/recall behavior — it's not needed for normal day-to-day feature work.

## Summary — minimum vs. full

| Scenario | Commands needed |
|---|---|
| Just building UI / CRUD features | XAMPP MySQL + `composer run dev` |
| + real-time/broadcast features | above + `php artisan reverb:start` |
| + testing reminders/recalls/scheduled jobs | above + `php artisan schedule:work` |

## Stopping everything

`Ctrl+C` in each terminal running a process. `composer run dev` stops all four of its sub-processes together with one `Ctrl+C`.

## Troubleshooting

- **`Undefined array key "scaling"` in `ReverbServerProvider.php`** — stale `bootstrap/cache/config.php` from before the `scaling` key existed in `config/reverb.php`. Fix: delete `bootstrap/cache/config.php` and re-run `php artisan config:clear`. (This will crash *every* artisan command, not just ones touching Reverb, because the provider resolves eagerly during framework boot.)
- **Real-time updates not showing up** — check `php artisan reverb:start` is actually running; check browser console for a WebSocket connection error to `REVERB_HOST:REVERB_PORT`.
- **Reminders/notifications not firing** — check `php artisan schedule:work` is running, and separately that the queue worker (`queue:listen`, part of `composer run dev`) is running, since scheduled commands often just dispatch jobs onto the queue.
