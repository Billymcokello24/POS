#!/bin/bash

# Deployment script for POS system on Ubuntu/Debian server
# Run as root or with sudo

set -e

echo "Starting deployment of POS system..."

# Update system
echo "Updating system packages..."
apt update && apt upgrade -y

# Install required packages
echo "Installing required packages..."
apt install -y nginx mysql-server redis-server php8.4 php8.4-fpm php8.4-mysql php8.4-xml php8.4-mbstring php8.4-curl php8.4-zip php8.4-gd php8.4-intl php8.4-bcmath php8.4-soap php8.4-readline php8.4-pcov php8.4-msgpack php8.4-igbinary php8.4-redis composer unzip curl

# Install Node.js 20.x (required for Vite)
echo "Installing Node.js..."
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs

# Start and enable services
systemctl enable nginx
systemctl enable mysql
systemctl enable redis-server
systemctl enable php8.4-fpm

systemctl start nginx
systemctl start mysql
systemctl start redis-server
systemctl start php8.4-fpm

# Secure MySQL installation (optional, but recommended)
# mysql_secure_installation

# Create database and user
echo "Setting up MySQL database..."
mysql -u root -p <<EOF
CREATE DATABASE IF NOT EXISTS pos_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'pos_user'@'localhost' IDENTIFIED BY 'CHANGE_THIS_STRONG_PASSWORD';
GRANT ALL PRIVILEGES ON pos_db.* TO 'pos_user'@'localhost';
FLUSH PRIVILEGES;
EOF

# Create web directory
mkdir -p /var/www/POS
chown -R www-data:www-data /var/www/POS

# Assuming the code is uploaded to /tmp/pos or cloned
# If using git: git clone <your-repo-url> /var/www/POS
# Else, copy from uploaded files
# cp -r /path/to/uploaded/pos/* /var/www/POS/

# For now, assume code is in /tmp/pos
# Uncomment and adjust:
# cp -r /tmp/pos/* /var/www/POS/

# Or if cloning from git:
echo "Enter your git repository URL (e.g., https://github.com/user/pos.git):"
read REPO_URL
if [ -n "$REPO_URL" ]; then
    git clone $REPO_URL /var/www/POS
else
    echo "No repo URL provided. Assuming code is uploaded to /tmp/pos"
    cp -r /tmp/pos/* /var/www/POS/
fi

# Install PHP dependencies
cd /var/www/POS
composer install --optimize-autoloader --no-dev --no-interaction

# Copy environment file
cp .env.production .env
echo "IMPORTANT: Edit /var/www/POS/.env to set correct database password, M-Pesa keys, etc."
echo "Press Enter to continue after editing (or continue without editing)..."
read -p "" CONTINUE

# Generate application key if not set
php artisan key:generate --force

# Run migrations
php artisan migrate --force

# Install Node dependencies and build production assets
echo "Building frontend assets for production..."
npm ci --production=false
NODE_ENV=production npm run build

# Verify build directory exists
if [ ! -d "public/build" ]; then
    echo "ERROR: Build directory not found. Asset compilation may have failed."
    exit 1
fi

# Clear any previous Laravel caches before optimizing
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Optimize Laravel for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# Set permissions
chown -R www-data:www-data /var/www/POS
chmod -R 755 /var/www/POS/storage
chmod -R 755 /var/www/POS/bootstrap/cache

# Copy nginx config
cp /var/www/POS/deploy/nginx.conf /etc/nginx/sites-available/pos.digiprojects.co.ke
ln -s /etc/nginx/sites-available/pos.digiprojects.co.ke /etc/nginx/sites-enabled/

# Remove default nginx site
rm -f /etc/nginx/sites-enabled/default

# Test nginx config
nginx -t

# Restart nginx
systemctl restart nginx

# Set up SSL with Let's Encrypt (optional)
# apt install -y certbot python3-certbot-nginx
# certbot --nginx -d pos.digiprojects.co.ke -d www.pos.digiprojects.co.ke

# Restart services
systemctl restart php8.4-fpm
systemctl restart redis-server

echo "Deployment completed. Visit http://pos.digiprojects.co.ke to access the application."
echo "Don't forget to update DNS to point pos.digiprojects.co.ke to 144.91.76.140"
