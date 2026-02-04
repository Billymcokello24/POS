# POS System Deployment Guide

This guide will help you deploy the POS system to a production server using Nginx, MySQL, and Redis.

> **Important**: If you're experiencing CORS errors with Vite assets, see [PRODUCTION_DEPLOYMENT.md](../PRODUCTION_DEPLOYMENT.md) for detailed troubleshooting.

## Prerequisites

- Ubuntu/Debian server with root or sudo access
- Domain: pos.digiprojects.co.ke pointing to server IP 144.91.76.140
- SSH access to the server

## Steps

1. **Upload the code to the server:**
   - Upload the entire project folder to `/tmp/pos` on the server, or clone from git if available.

2. **Run the deployment script:**
   ```bash
   sudo ./deploy/deploy.sh
   ```
   Note: The script assumes the code is in `/tmp/pos`. Adjust the script if needed.

3. **Edit the .env file:**
   - After running the script, edit `/var/www/POS/.env` to set:
     - Database password (match the one in the script or MySQL)
     - M-Pesa API keys
     - Any other production-specific settings
   - **CRITICAL**: Ensure `APP_ENV=production` is set (script handles this automatically)

4. **Verify assets are built:**
   - Check that `public/build/` directory exists with compiled assets
   - If missing, run: `npm run build`

5. **Set up DNS:**
   - Ensure pos.digiprojects.co.ke points to 144.91.76.140

6. **Optional: Set up SSL**
   - Uncomment the certbot lines in deploy.sh and run again, or manually:
     ```bash
     sudo apt install certbot python3-certbot-nginx
     sudo certbot --nginx -d pos.digiprojects.co.ke
     ```

7. **Access the application:**
   - Visit https://pos.digiprojects.co.ke (if SSL is set up) or http://pos.digiprojects.co.ke

## Troubleshooting CORS Errors

If you see errors like:
```
Access to script at 'http://localhost:5173/@vite/client' from origin 'https://pos.digiprojects.co.ke' has been blocked by CORS policy
```

This means the production environment is trying to use the development Vite server. See [PRODUCTION_DEPLOYMENT.md](../PRODUCTION_DEPLOYMENT.md) for detailed fix instructions.

**Quick fix:**
1. Ensure `APP_ENV=production` in `.env`
2. Run `npm run build` to compile assets
3. Restart web server: `sudo systemctl restart nginx`

## Notes

- The script installs PHP 8.2. Adjust if your server has a different version.
- Change the database password in the script and .env.production before running.
- For security, consider running mysql_secure_installation.
- Back up your data regularly.
