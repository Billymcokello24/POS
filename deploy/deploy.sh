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
apt install -y nginx mysql-server redis-server php8.2 php8.2-fpm php8.2-mysql php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip php8.2-gd php8.2-intl php8.2-bcmath php8.2-soap php8.2-readline php8.2-pcov php8.2-msgpack php8.2-igbinary php8.2-redis composer unzip curl

# Start and enable services
systemctl enable nginx
systemctl enable mysql
systemctl enable redis-server
systemctl enable php8.2-fpm

systemctl start nginx
systemctl start mysql
systemctl start redis-server
systemctl start php8.2-fpm

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
mkdir -p /var/www/pos
chown -R www-data:www-data /var/www/pos

# Assuming the code is uploaded to /tmp/pos or cloned
# If using git: git clone <repo> /var/www/pos
# Else, copy from uploaded files
# cp -r /path/to/uploaded/pos/* /var/www/pos/

# For now, assume code is in /tmp/pos
# Uncomment and adjust:
# cp -r /tmp/pos/* /var/www/pos/

# Install PHP dependencies
cd /var/www/pos
composer install --optimize-autoloader --no-dev --no-interaction

# Copy environment file
cp .env.production .env
# IMPORTANT: Edit .env to set correct database password, M-Pesa keys, etc.
# nano .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate --force

# Optimize Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Install Node dependencies and build assets (if using Vite)
npm install
npm run build

# Set permissions
chown -R www-data:www-data /var/www/pos
chmod -R 755 /var/www/pos/storage
chmod -R 755 /var/www/pos/bootstrap/cache

# Copy nginx config
cp /var/www/pos/deploy/nginx.conf /etc/nginx/sites-available/pos.digiprojects.co.ke
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
systemctl restart php8.2-fpm
systemctl restart redis-server

echo "Deployment completed. Visit http://pos.digiprojects.co.ke to access the application."
echo "Don't forget to update DNS to point pos.digiprojects.co.ke to 144.91.76.140"
