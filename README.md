# POS System

A comprehensive Point of Sale system built with Laravel and Vue.js.

## Quick Start

### Development
```bash
# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Start development servers
npm run dev        # In one terminal
php artisan serve  # In another terminal
```

### Production Deployment

⚠️ **Important**: If you see CORS errors about `localhost:5173`, see [Production Deployment Guide](PRODUCTION_DEPLOYMENT.md).

```bash
# Run the automated deployment script
sudo ./deploy/deploy.sh

# Or manually:
cp .env.production .env
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan optimize
```

**Verify deployment:**
```bash
./deploy/verify-deployment.sh
```

## Documentation

- [Production Deployment Guide](PRODUCTION_DEPLOYMENT.md) - Complete guide for production deployment
- [Deploy README](deploy/README.md) - Server setup instructions
- [M-Pesa Setup](MPESA_PRODUCTION_SETUP.md) - M-Pesa payment integration
- [Redis Setup](REDIS_SETUP.md) - Redis configuration

## Common Issues

### CORS Errors in Production

If you see errors like:
```
Access to script at 'http://localhost:5173/@vite/client' from origin 'https://pos.digiprojects.co.ke' has been blocked
```

**Solution:**
1. Ensure `APP_ENV=production` in `.env`
2. Run `npm run build`
3. Verify `public/build/` directory exists
4. Clear caches: `php artisan optimize:clear`

See [PRODUCTION_DEPLOYMENT.md](PRODUCTION_DEPLOYMENT.md) for detailed troubleshooting.

## Tech Stack

- **Backend**: Laravel 11
- **Frontend**: Vue.js 3 + Inertia.js
- **Build Tool**: Vite
- **Database**: MySQL
- **Cache**: Redis
- **Payments**: M-Pesa Integration

## License

Proprietary - All rights reserved
