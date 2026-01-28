Using Docker Redis for Laravel sessions (aaPanel / host)

This guide shows how to run Redis in Docker and configure your Laravel app to use it for sessions, cache and queues to resolve 419 CSRF/session errors.

Assumptions
- App path: /var/www/POS or /www/wwwroot/POS (adjust below)
- You have Docker and Docker Compose installed (if not, install via apt)

Steps

1) Start Redis container using the bundled compose file

```bash
cd /var/www/POS/deploy
docker compose -f docker-redis-compose.yml up -d
```

This will start a Redis container named `pos-redis` and map port 6379 on the host.

2) Update your `.env` file

Edit `/var/www/POS/.env` and set the following:

```
SESSION_DRIVER=redis
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

APP_URL=https://pos.digiprojects.co.ke
SESSION_DOMAIN=.digiprojects.co.ke
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
```

Notes:
- If Docker is on the same host, use `127.0.0.1` since the compose file binds port 6379.
- If your app runs in Docker and Redis is another service in the same compose, use the service name `redis`.

3) Ensure PHP has `phpredis` installed

On the host (or in your PHP container) run:

```bash
php -m | grep -i redis || sudo apt update && sudo apt install -y php-redis && sudo systemctl restart php8.4-fpm
```

4) Clear Laravel caches and restart services

```bash
cd /var/www/POS
sudo -u www-data php artisan config:clear
sudo -u www-data php artisan cache:clear
sudo -u www-data php artisan view:clear
sudo -u www-data php artisan config:cache

sudo systemctl restart php8.4-fpm
sudo systemctl restart nginx
```

5) Verify Redis connectivity

```bash
redis-cli -h 127.0.0.1 -p 6379 ping
# should reply PONG

# list keys (sessions typically prefix)
redis-cli -h 127.0.0.1 -p 6379 keys "*session*" | wc -l
```

6) Test login in browser
- Clear browser cookies or open Incognito
- Visit https://pos.digiprojects.co.ke/login and inspect network requests
  - GET /login should set a laravel_session cookie
  - POST /login should include cookie and CSRF token header

7) Troubleshooting
- If you don't see Set-Cookie on GET /login, confirm `APP_URL` and `SESSION_DOMAIN` in `.env` match domain and secure settings.
- If cookie is set but POST returns 419, check `X-CSRF-TOKEN` header in the request. For Inertia/Vite apps, ensure the meta csrf token exists: `<meta name="csrf-token" content="...">`.
- Check Laravel logs: `tail -n 200 storage/logs/laravel.log` and Nginx error log `/var/log/nginx/error.log` or aaPanel logs.

If you'd like I can (A) generate a script to perform these changes automatically on your server, or (B) walk you through running each command interactively. Tell me which option you prefer.
