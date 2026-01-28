Redis setup and enabling Redis-backed session/cache/queue for POS project

1) Install and start Redis (Linux):

```bash
# Debian/Ubuntu
sudo apt update
sudo apt install -y redis-server
sudo systemctl enable --now redis
# verify
redis-cli ping
# should output: PONG
```

Or using Docker:

```bash
docker run -d --name pos-redis -p 6379:6379 redis:7
```

2) Configure `.env` (already set by the script):

- CACHE_STORE=redis
- SESSION_DRIVER=redis
- QUEUE_CONNECTION=redis
- REDIS_CLIENT=predis (or phpredis)
- REDIS_HOST=127.0.0.1
- REDIS_PORT=6379

3) Install composer deps and restart services

```bash
composer install --no-dev --optimize-autoloader
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache

# Start queue worker (in background or supervisor)
php artisan queue:work --sleep=3 --tries=3
```

4) Enable SSE on frontend (optional):

```bash
# in .env
VITE_ENABLE_SSE=true
# restart Vite
npm run dev
```

5) Test

- Login to application in browser
- Open DevTools -> Network and look for an EventSource request to `/sse/business-stream` once SSE is enabled
- Perform actions (create product), or use debug push route `POST /debug/sse/push` to emit events

Notes:
- Redis should be reachable and running; if not, Laravel will fallback for some features but SSE relies on Redis pub/sub.
- For production, consider running Supervisor to manage `php artisan queue:work` and using a more scalable SSE transport or Octane for many concurrent connections.

