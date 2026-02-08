#!/bin/bash

# Production Deployment Verification Script
# This script verifies that the production environment is correctly configured

set -e

echo "=========================================="
echo "POS Production Deployment Verification"
echo "=========================================="
echo ""

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

ERRORS=0
WARNINGS=0

# Function to print success
print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

# Function to print error
print_error() {
    echo -e "${RED}✗${NC} $1"
    ((ERRORS++))
}

# Function to print warning
print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
    ((WARNINGS++))
}

echo "Checking production configuration..."
echo ""

# Check 1: Verify .env file exists
echo "1. Checking .env file..."
if [ -f ".env" ]; then
    print_success ".env file exists"
else
    print_error ".env file not found"
fi
echo ""

# Check 2: Verify APP_ENV is production
echo "2. Checking APP_ENV setting..."
if [ -f ".env" ]; then
    APP_ENV=$(grep "^APP_ENV=" .env | cut -d '=' -f2)
    if [ "$APP_ENV" = "production" ]; then
        print_success "APP_ENV is set to production"
    else
        print_error "APP_ENV is '$APP_ENV', should be 'production'"
        echo "   Fix: Edit .env and set APP_ENV=production"
    fi
else
    print_error "Cannot check APP_ENV - .env file missing"
fi
echo ""

# Check 3: Verify APP_DEBUG is false
echo "3. Checking APP_DEBUG setting..."
if [ -f ".env" ]; then
    APP_DEBUG=$(grep "^APP_DEBUG=" .env | cut -d '=' -f2)
    if [ "$APP_DEBUG" = "false" ]; then
        print_success "APP_DEBUG is set to false"
    else
        print_warning "APP_DEBUG is '$APP_DEBUG', should be 'false' for production"
        echo "   Fix: Edit .env and set APP_DEBUG=false"
    fi
else
    print_error "Cannot check APP_DEBUG - .env file missing"
fi
echo ""

# Check 4: Verify APP_KEY is set
echo "4. Checking APP_KEY..."
if [ -f ".env" ]; then
    APP_KEY=$(grep "^APP_KEY=" .env | cut -d '=' -f2)
    if [ -n "$APP_KEY" ] && [ "$APP_KEY" != "" ]; then
        print_success "APP_KEY is set"
    else
        print_error "APP_KEY is not set"
        echo "   Fix: Run 'php artisan key:generate'"
    fi
else
    print_error "Cannot check APP_KEY - .env file missing"
fi
echo ""

# Check 5: Verify build directory exists
echo "5. Checking production build assets..."
if [ -d "public/build" ]; then
    print_success "public/build directory exists"
    
    # Check if manifest.json exists
    if [ -f "public/build/manifest.json" ]; then
        print_success "Build manifest exists"
    else
        print_error "Build manifest (public/build/manifest.json) not found"
        echo "   Fix: Run 'npm run build'"
    fi
else
    print_error "public/build directory not found"
    echo "   Fix: Run 'npm run build' to compile production assets"
fi
echo ""

# Check 6: Verify node_modules exists
echo "6. Checking Node.js dependencies..."
if [ -d "node_modules" ]; then
    print_success "node_modules directory exists"
else
    print_warning "node_modules directory not found"
    echo "   Fix: Run 'npm ci' to install dependencies"
fi
echo ""

# Check 7: Verify vendor directory exists
echo "7. Checking PHP dependencies..."
if [ -d "vendor" ]; then
    print_success "vendor directory exists"
else
    print_error "vendor directory not found"
    echo "   Fix: Run 'composer install --no-dev --optimize-autoloader'"
fi
echo ""

# Check 8: Verify storage permissions
echo "8. Checking storage permissions..."
if [ -d "storage" ]; then
    if [ -w "storage" ]; then
        print_success "storage directory is writable"
    else
        print_error "storage directory is not writable"
        echo "   Fix: Run 'chmod -R 755 storage'"
    fi
else
    print_error "storage directory not found"
fi
echo ""

# Check 9: Verify bootstrap/cache permissions
echo "9. Checking bootstrap/cache permissions..."
if [ -d "bootstrap/cache" ]; then
    if [ -w "bootstrap/cache" ]; then
        print_success "bootstrap/cache directory is writable"
    else
        print_error "bootstrap/cache directory is not writable"
        echo "   Fix: Run 'chmod -R 755 bootstrap/cache'"
    fi
else
    print_error "bootstrap/cache directory not found"
fi
echo ""

# Check 10: Test if Laravel can run
echo "10. Testing Laravel installation..."
if command -v php &> /dev/null; then
    if php artisan --version &> /dev/null; then
        print_success "Laravel is installed and working"
    else
        print_error "Laravel artisan command failed"
    fi
else
    print_error "PHP is not installed or not in PATH"
fi
echo ""

# Check 11: Verify APP_URL matches domain
echo "11. Checking APP_URL configuration..."
if [ -f ".env" ]; then
    APP_URL=$(grep "^APP_URL=" .env | cut -d '=' -f2)
    if echo "$APP_URL" | grep -qE '^https?://pos\.digiprojects\.co\.ke/?$'; then
        print_success "APP_URL is set to production domain"
    else
        print_warning "APP_URL is '$APP_URL'"
        echo "   Expected: https://pos.digiprojects.co.ke"
    fi
else
    print_error "Cannot check APP_URL - .env file missing"
fi
echo ""

# Summary
echo "=========================================="
echo "Verification Summary"
echo "=========================================="
echo ""

if [ $ERRORS -eq 0 ] && [ $WARNINGS -eq 0 ]; then
    echo -e "${GREEN}All checks passed! ✓${NC}"
    echo "Your production environment is correctly configured."
    echo ""
    echo "Next steps:"
    echo "  1. Ensure DNS points to your server"
    echo "  2. Set up SSL with: sudo certbot --nginx -d pos.digiprojects.co.ke"
    echo "  3. Access your application at https://pos.digiprojects.co.ke"
    exit 0
elif [ $ERRORS -eq 0 ]; then
    echo -e "${YELLOW}Warnings: $WARNINGS${NC}"
    echo "Your production environment has some warnings but should work."
    echo "Review the warnings above and fix them for optimal security."
    exit 0
else
    echo -e "${RED}Errors: $ERRORS${NC}"
    if [ $WARNINGS -gt 0 ]; then
        echo -e "${YELLOW}Warnings: $WARNINGS${NC}"
    fi
    echo ""
    echo "Please fix the errors above before deploying to production."
    echo "See PRODUCTION_DEPLOYMENT.md for detailed instructions."
    exit 1
fi
