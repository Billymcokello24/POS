# CORS Policy Fix - Implementation Summary

## Problem Statement

The production site at `https://pos.digiprojects.co.ke` was experiencing CORS errors:
```
Access to script at 'http://localhost:5173/@vite/client' from origin 'https://pos.digiprojects.co.ke' has been blocked by CORS policy
```

This occurred because the production environment was configured to load assets from the Vite development server instead of using compiled production assets.

## Root Cause

The issue happened when:
1. Frontend assets were not built for production using `npm run build`
2. The `APP_ENV` environment variable was not set to `production`
3. Laravel's Vite plugin detected a non-production environment and tried to connect to the dev server

## Solution Implemented

### 1. Created `.env.production` Template
- Added a production-ready environment configuration template
- Set `APP_ENV=production` (critical for proper asset handling)
- Set `APP_DEBUG=false` for security
- Configured proper production URL: `https://pos.digiprojects.co.ke`
- Removed all development-specific settings

### 2. Enhanced Deployment Script (`deploy/deploy.sh`)
- Added Node.js installation (required for Vite)
- Improved asset building process with proper environment variables
- Added build verification to ensure `public/build/` directory is created
- Added cache clearing before optimization
- Added better error handling and user prompts

### 3. Created Comprehensive Documentation

#### `PRODUCTION_DEPLOYMENT.md`
- Complete step-by-step deployment guide
- Troubleshooting section for CORS errors
- Explanation of how Laravel Vite works in development vs production
- Quick reference commands

#### `deploy/verify-deployment.sh`
- Automated verification script to check production configuration
- Validates all critical settings:
  - `.env` file exists and is properly configured
  - `APP_ENV=production`
  - `APP_DEBUG=false`
  - `APP_KEY` is set
  - Production assets are built (`public/build/` exists)
  - Proper file permissions
  - Dependencies are installed

#### `README.md`
- Quick start guide for development and production
- Links to detailed documentation
- Common issues and solutions

### 4. Updated `.gitignore`
- Removed `.env.production` from ignore list
- Allows the production template to be version controlled (it contains no secrets)

## How to Deploy to Production

### Option 1: Automated Deployment (Recommended)
```bash
# On the production server
cd /var/www/POS
sudo ./deploy/deploy.sh
```

### Option 2: Manual Deployment
```bash
# Copy and configure environment
cp .env.production .env
nano .env  # Edit to add secrets (DB password, API keys, etc.)

# Install dependencies
composer install --no-dev --optimize-autoloader
npm ci

# Build production assets (CRITICAL)
NODE_ENV=production npm run build

# Run migrations
php artisan key:generate --force
php artisan migrate --force

# Clear and optimize Laravel
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set permissions
chown -R www-data:www-data /var/www/POS
chmod -R 755 storage bootstrap/cache
```

### Verify Deployment
```bash
./deploy/verify-deployment.sh
```

## Key Changes for Production

### Before (Causing CORS Errors)
- `APP_ENV=local` → Vite tries to connect to dev server
- No `public/build/` directory → No compiled assets
- Assets loaded from `http://localhost:5173`

### After (Fixed)
- `APP_ENV=production` → Vite uses compiled assets
- `public/build/` created by `npm run build`
- Assets loaded from `/build/assets/...` (same origin)

## Verification Steps

After deployment, verify the fix:

1. **Check environment**:
   ```bash
   php artisan env
   # Should show: production
   ```

2. **Check build directory**:
   ```bash
   ls -la public/build/manifest.json
   # Should exist
   ```

3. **Check browser**:
   - Visit `https://pos.digiprojects.co.ke`
   - Open browser console (F12)
   - No CORS errors
   - Network tab shows assets loading from `/build/assets/...`

4. **View HTML source**:
   - Should see: `<script src="/build/assets/app-[hash].js">`
   - Should NOT see: `http://localhost:5173`

## Files Modified

1. `.env.production` - Created production environment template
2. `deploy/deploy.sh` - Enhanced deployment automation
3. `deploy/README.md` - Updated with CORS troubleshooting
4. `.gitignore` - Allowed `.env.production` to be tracked

## Files Created

1. `PRODUCTION_DEPLOYMENT.md` - Comprehensive deployment guide
2. `deploy/verify-deployment.sh` - Automated verification script
3. `README.md` - Project documentation
4. `CORS_FIX_SUMMARY.md` - This file

## Important Notes

### For Developers
- **Never** run `npm run dev` in production
- Always build assets with `npm run build` before deploying
- The `.env.production` file is a template - actual `.env` should contain real secrets

### For Deployment
- The deployment script handles everything automatically
- Run `verify-deployment.sh` after deployment to ensure everything is correct
- If you see CORS errors, check that `APP_ENV=production` and `public/build/` exists

### Security
- `APP_ENV=production` must be set
- `APP_DEBUG=false` must be set
- Never commit the actual `.env` file with secrets

## Testing the Fix

To test locally that the fix works:

1. Create a production-like environment:
   ```bash
   cp .env.production .env
   php artisan key:generate
   ```

2. Build production assets:
   ```bash
   npm run build
   ```

3. Verify build directory:
   ```bash
   ls -la public/build/
   ```

4. Check Laravel uses production mode:
   ```bash
   php artisan env
   # Should show: production
   ```

## Rollback Plan

If issues occur after deployment:

1. Check logs:
   ```bash
   tail -f storage/logs/laravel.log
   tail -f /var/log/nginx/error.log
   ```

2. Rebuild assets:
   ```bash
   rm -rf public/build
   npm run build
   ```

3. Clear caches:
   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   php artisan cache:clear
   ```

## Future Considerations

1. Consider adding CI/CD pipeline to automate builds
2. Consider using Laravel Forge or similar for easier deployments
3. Monitor error logs after deployment
4. Keep dependencies updated

## Support

If issues persist:
1. Run `./deploy/verify-deployment.sh` and fix any errors
2. Check `PRODUCTION_DEPLOYMENT.md` for detailed troubleshooting
3. Verify DNS is correctly pointing to the server
4. Check Nginx configuration is correct

---

**Summary**: This fix ensures that production environments use compiled Vite assets from the `public/build/` directory instead of trying to connect to the development server, eliminating CORS errors.
