# Monster PvP Platform

This project is a Laravel 10 application for a Pokémon-inspired monster tamer with deterministic PvP battles, live ladder matchmaking, admin-defined encounter zones, and Vite-powered UI assets.

## Tech stack
- PHP 8.1+ with Laravel 10
- PostgreSQL + PostGIS and Redis
- Vite, Laravel Echo, and Pusher-compatible websockets for real-time features

## Live ranked ladder + socket setup
Follow these steps to bring up the live matchmaking flow that the latest change introduced:

1. **Install PHP/JS deps (including the Pusher server SDK)**
   ```bash
   composer install
   composer require pusher/pusher-php-server
   npm ci
   ```
   The backend emits `PvpMatchFound` and `PvpSearchStatus` events that use the Pusher driver to reach a private `users.{id}` channel for each player. Frontend listeners live in `resources/js/modules/pvp.js` and are bootstrapped via `resources/js/app.js`.

2. **Set environment for websockets** (use matching values for both Laravel and Vite):
   ```env
   BROADCAST_DRIVER=pusher
   QUEUE_CONNECTION=redis       # or "sync" for local-only testing

   PUSHER_APP_ID=monster-local
   PUSHER_APP_KEY=localkey
   PUSHER_APP_SECRET=localsecret
   PUSHER_HOST=127.0.0.1
   PUSHER_PORT=6001
   PUSHER_SCHEME=http

   VITE_PUSHER_APP_KEY=${PUSHER_APP_KEY}
   VITE_PUSHER_APP_CLUSTER=mt1
   VITE_PUSHER_HOST=${PUSHER_HOST}
   VITE_PUSHER_PORT=${PUSHER_PORT}
   VITE_PUSHER_SCHEME=${PUSHER_SCHEME}
   ```
   These map directly to the Echo bootstrap in `resources/js/bootstrap.js`, which connects via ws/wss using the above host/port/scheme. Set `APP_URL` to the domain you’ll hit from the browser so Echo auth works.

3. **Run database setup** so the matchmaking queue and battle tables exist:
   ```bash
   php artisan migrate
   ```

4. **Start a websockets server** that speaks the Pusher protocol on your droplet:
   - **Laravel WebSockets / Reverb (self-contained on the app host)**: install the package of your choice, then run it bound to `0.0.0.0` and the same `PUSHER_PORT` you configured above. Example with Laravel WebSockets:
     ```bash
     composer require beyondcode/laravel-websockets
     php artisan websockets:serve --host=0.0.0.0 --port=6001
     ```
   - **Soketi (standalone server)**: run the Docker image alongside the app:
     ```bash
     docker run -it --rm -p 6001:6001 \
       -e DEBUG=1 -e DEFAULT_APP_ID=monster-local \
       -e DEFAULT_APP_KEY=localkey -e DEFAULT_APP_SECRET=localsecret \
       quay.io/soketi/soketi:1.0-16-alpine
     ```
   - **Laravel Echo Server** remains an option if you already have it installed globally:
     ```bash
     laravel-echo-server init   # set host 0.0.0.0, port 6001, app id/key/secret from .env
     laravel-echo-server start
     ```
   Any of the above will satisfy both the ranked ladder lobby events and the new live battle turn updates (`BattleUpdated`).

5. **Run the app + queues + Vite**:
   ```bash
   php artisan serve
   # In another shell (unless QUEUE_CONNECTION=sync)
   php artisan queue:work
   # For client assets
   npm run dev    # or `npm run build` for production
   ```

6. **Verify matchmaking + battles**: log in as two users, open `/pvp`, press a queue mode, and watch the status cards update. When the backend pairs two `MatchmakingQueue` entries, it creates a `Battle` via `LiveMatchmaker` and broadcasts to both players; the JS handler redirects to the battle URL immediately. Once in `/battles/{id}`, both clients stay in sync via the `BattleUpdated` event and reload automatically when an action resolves. If the search timer expires with no pairing, the client leaves the queue and prompts the user to try again.

## Battle and encounter notes
- Battles are initialized through `BattleEngine`, pulling up to three strongest monsters per player; if a player lacks monsters, the frontend exposes a fallback punch/kick move set in the Pokémon-inspired battle UI. The battle page now listens for websocket updates and refreshes automatically when turns complete.
- Admins can draw zones and configure spawn types via `/admin/zones/*` to control random encounter generation. Zones saved with a non-`manual` spawn strategy now auto-seed their spawn tables using the preferred types so encounters have monsters to choose from immediately.

## Development scripts
- `npm run dev` – Vite dev server for JS/CSS
- `npm run build` – production asset build

## Licensing
This codebase started from Laravel’s MIT-licensed skeleton.
