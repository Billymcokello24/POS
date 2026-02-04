# Production Deployment Guide - CORS Fix

## Issue Description

The CORS errors occur when the production environment tries to load assets from the local Vite development server (`http://localhost:5173`) instead of using compiled production assets. This happens when:

1. Frontend assets are not built for production
2. The application environment is not properly set to production
3. The `.env` file contains development settings

## Solution Overview

The fix involves:
1. Ensuring `APP_ENV=production` in the production `.env` file
2. Building frontend assets using `npm run build`
3. Ensuring the application serves compiled assets from `public/build/` directory

## Deployment Steps

### 1. Pre-Deployment Checklist

Before deploying to production, ensure:
- [ ] You have SSH access to the production server
- [ ] DNS for `pos.digiprojects.co.ke` points to your server IP
- [ ] You have all required credentials (database, M-Pesa API keys, etc.)

### 2. Automated Deployment

The easiest way to deploy is using the provided deployment script:

```bash
# On the production server
cd /var/www/POS
sudo ./deploy/deploy.sh
```

The script will:
- Install all required dependencies (PHP, MySQL, Redis, Nginx, Node.js)
- Copy `.env.production` to `.env`
- Build production assets with `npm run build`
- Optimize Laravel for production
- Configure Nginx

### 3. Manual Deployment Steps

If you prefer manual deployment or need to troubleshoot:

#### Step 3.1: Copy Environment File
```bash
cp .env.production .env
```

#### Step 3.2: Edit Environment Variables
Edit `/var/www/POS/.env` and set:
- `APP_ENV=production` (CRITICAL - must be production)
- `APP_DEBUG=false` (for security)
- `APP_URL=https://pos.digiprojects.co.ke`
- Database credentials
- M-Pesa API keys (if using)
- Pusher credentials (if using)

#### Step 3.3: Generate Application Key
```bash
php artisan key:generate --force
```

#### Step 3.4: Install Dependencies
```bash
composer install --optimize-autoloader --no-dev --no-interaction
npm ci --production=false
```

#### Step 3.5: Build Production Assets (CRITICAL)
```bash
NODE_ENV=production npm run build
```

This creates the `public/build/` directory with compiled assets that Laravel Vite will serve in production.

#### Step 3.6: Run Migrations
```bash
php artisan migrate --force
```

#### Step 3.7: Optimize Laravel
```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

#### Step 3.8: Set Proper Permissions
```bash
chown -R www-data:www-data /var/www/POS
chmod -R 755 /var/www/POS/storage
chmod -R 755 /var/www/POS/bootstrap/cache
```

### 4. Verify Deployment

After deployment, verify that:

1. **Assets are built**: Check that `public/build/` directory exists and contains assets
   ```bash
   ls -la /var/www/POS/public/build/
   ```

2. **Environment is production**: Check the environment
   ```bash
   php artisan env
   # Should show: Current application environment: production
   ```

3. **No Vite dev server references**: View the HTML source of your production site
   - Should see `<script src="/build/assets/app-[hash].js">`
   - Should NOT see references to `http://localhost:5173`

4. **Site loads correctly**: Visit https://pos.digiprojects.co.ke
   - No CORS errors in browser console
   - Assets load from `/build/` path
   - Application works properly

### 5. Troubleshooting

#### Still seeing CORS errors?

1. **Check environment**:
   ```bash
   php artisan env
   ```
   Must show `production`, not `local` or `development`

2. **Check build directory exists**:
   ```bash
   ls -la public/build/manifest.json
   ```
   If missing, run: `npm run build`

3. **Clear all caches**:
   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   php artisan cache:clear
   php artisan optimize:clear
   ```

4. **Check .env file**:
   ```bash
   grep APP_ENV /var/www/POS/.env
   ```
   Must show: `APP_ENV=production`

5. **Rebuild assets**:
   ```bash
   rm -rf public/build
   NODE_ENV=production npm run build
   ```

6. **Check browser cache**: Clear your browser cache or use incognito mode

#### Assets not loading?

1. Check Nginx is serving static files correctly
2. Check file permissions on `public/build/`
3. Check Nginx logs: `tail -f /var/log/nginx/error.log`

## How Laravel Vite Works

### Development Mode (APP_ENV=local)
- Vite dev server runs on `http://localhost:5173`
- Laravel's Vite plugin injects script tags pointing to the dev server
- Hot module replacement (HMR) enabled for fast development

### Production Mode (APP_ENV=production)
- Assets are pre-compiled using `npm run build`
- Compiled assets stored in `public/build/` directory
- Laravel's Vite plugin reads `public/build/manifest.json` to inject correct asset paths
- No dev server needed - all assets served as static files

## Quick Reference

### Common Commands

```bash
# Check environment
php artisan env

# Rebuild production assets
NODE_ENV=production npm run build

# Clear all caches
php artisan optimize:clear

# Optimize for production
php artisan optimize

# Check if build succeeded
ls -la public/build/manifest.json
```

### Environment Variables

Critical production settings in `.env`:
```
APP_ENV=production          # Must be production
APP_DEBUG=false            # Must be false for security
APP_URL=https://pos.digiprojects.co.ke
```

## Additional Notes

- Never run the Vite dev server (`npm run dev`) in production
- Always build assets before deploying with `npm run build`
- The `public/build/` directory should be committed to version control or built during deployment
- For zero-downtime deployments, use a tool like Laravel Envoy or Deployer

## Support

If you continue to experience issues:
1. Check the browser console for specific error messages
2. Check Laravel logs: `tail -f storage/logs/laravel.log`
3. Check Nginx error logs: `tail -f /var/log/nginx/error.log`
4. Verify all deployment steps were completed successfully
