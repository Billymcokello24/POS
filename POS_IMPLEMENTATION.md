# POS System - Implementation Documentation

## Overview
A full-featured Point of Sale (POS) system built with Laravel backend and Vue.js (Inertia.js) frontend, implementing multi-tenant architecture, barcode scanning, inventory management, and comprehensive reporting.

## Technology Stack

### Backend
- **Framework:** Laravel 12
- **Database:** SQLite
- **Authentication:** Laravel Fortify with 2FA support
- **PDF Generation:** barryvdh/laravel-dompdf
- **Excel Export:** maatwebsite/excel
- **Barcode Generation:** milon/barcode

### Frontend
- **Framework:** Vue.js 3 with TypeScript
- **Rendering:** Inertia.js
- **UI Components:** Reka UI (shadcn-vue style)
- **Styling:** Tailwind CSS 4
- **Icons:** Lucide Vue

## Database Schema

### Core Tables

1. **businesses** - Multi-tenant business entities
   - Business information, settings, tax ID, branding
   
2. **users** - System users
   - Authentication, business association, role assignments

3. **roles & permissions** - RBAC system
   - 4 default roles: Admin, Manager, Cashier, Stock Clerk
   - 25+ granular permissions

4. **products** - Product catalog
   - SKU, barcode, pricing, inventory, tax configuration
   
5. **categories** - Product categorization
   - Hierarchical category structure

6. **inventory_transactions** - Stock movement tracking
   - Complete audit trail of all inventory changes

7. **sales** - Transaction records
   - Sale number, cashier, customer, totals, status

8. **sale_items** - Line items for each sale
   - Product snapshot at time of sale

9. **payments** - Payment records
   - Support for multiple payment methods per sale

10. **customers** - Customer database
    - Purchase history, visit tracking

11. **tax_configurations** - Tax rules
    - Configurable tax rates per business

## Features Implemented

### ✅ Phase 1 - Core MVP (Completed)

#### 1. Database Foundation
- [x] All migrations created and tested
- [x] Model relationships established
- [x] Soft deletes on key models
- [x] Database indexes for performance

#### 2. User Management & RBAC
- [x] Role-based access control
- [x] 4 default roles with permissions
- [x] Multi-business user support
- [x] Permission checking methods

#### 3. Product Management
- [x] CRUD operations for products
- [x] Category management
- [x] Barcode generation (CODE128, EAN13, UPCA)
- [x] SKU auto-generation
- [x] Product search and filtering
- [x] Low stock alerts
- [x] Tax configuration per product

#### 4. Inventory Management
- [x] Real-time inventory tracking
- [x] Inventory transaction history
- [x] Stock increase/decrease methods
- [x] Reorder level warnings
- [x] Multiple transaction types (IN, OUT, ADJUSTMENT, SALE, RETURN)

#### 5. Sales Processing
- [x] POS interface (Vue component)
- [x] Barcode scanning
- [x] Product search
- [x] Cart management
- [x] Multiple payment methods (Cash, Card, M-Pesa, Bank Transfer)
- [x] Tax calculation
- [x] Discount support
- [x] Automatic inventory deduction
- [x] Customer association
- [x] Sale number generation

#### 6. Frontend Pages
- [x] Dashboard with statistics
- [x] Products list with filters
- [x] Product create/edit forms
- [x] POS interface (Sales/Create)
- [x] Responsive design
- [x] TypeScript support

## Default Credentials

After running migrations and seeders:

```
Admin Account:
Email: admin@demo.com
Password: password

Cashier Account:
Email: cashier@demo.com
Password: password
```

## Demo Data

The seeder creates:
- 1 Demo business
- 2 user accounts (Admin, Cashier)
- 4 roles with permissions
- 2 categories (Electronics, Clothing)
- 4 demo products
- 1 tax configuration (16% VAT)

## Installation & Setup

### 1. Install Dependencies

```bash
# PHP dependencies
composer install

# Node dependencies
npm install
```

### 2. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

### 3. Database Setup

```bash
# Run migrations and seed demo data
php artisan migrate:fresh --seed
```

### 4. Start Development Servers

```bash
# Option 1: Use composer dev script (recommended)
composer dev

# Option 2: Manual
php artisan serve          # Terminal 1
npm run dev                # Terminal 2
```

## API Endpoints

### Products
- `GET /products` - List products
- `GET /products/create` - Show create form
- `POST /products` - Store product
- `GET /products/{id}` - Show product
- `GET /products/{id}/edit` - Edit form
- `PUT /products/{id}` - Update product
- `DELETE /products/{id}` - Delete product
- `GET /api/products/search?q={query}` - Search products
- `GET /api/products/scan?barcode={code}` - Scan barcode

### Sales
- `GET /sales` - List sales
- `GET /sales/create` - POS interface
- `POST /sales` - Create sale
- `GET /sales/{id}` - Show sale
- `GET /sales/{id}/receipt` - Download receipt PDF
- `POST /sales/{id}/refund` - Process refund

### Categories
- `GET /categories` - List categories
- `POST /categories` - Create category
- `PUT /categories/{id}` - Update category
- `DELETE /categories/{id}` - Delete category

## Project Structure

```
app/
├── Models/
│   ├── Business.php           # Multi-tenant business model
│   ├── Product.php             # Product with inventory methods
│   ├── Sale.php                # Sales transaction
│   ├── SaleItem.php            # Sale line items
│   ├── Payment.php             # Payment records
│   ├── Category.php            # Product categories
│   ├── Customer.php            # Customer management
│   ├── InventoryTransaction.php # Stock movement
│   ├── TaxConfiguration.php    # Tax rules
│   ├── Role.php                # RBAC roles
│   ├── Permission.php          # RBAC permissions
│   └── User.php                # Users with business context
│
└── Http/Controllers/
    ├── ProductController.php   # Product CRUD & search
    ├── SalesController.php     # POS & sales processing
    ├── CategoryController.php  # Category management
    ├── InventoryController.php # Inventory operations (stub)
    ├── ReportsController.php   # Reports generation (stub)
    └── BusinessController.php  # Business settings (stub)

resources/js/
├── pages/
│   ├── Dashboard.vue           # Main dashboard
│   ├── Products/
│   │   ├── Index.vue          # Product list
│   │   └── Create.vue         # Product form
│   └── Sales/
│       └── Create.vue         # POS interface
│
└── components/ui/              # Reka UI components
    ├── button/
    ├── card/
    ├── input/
    ├── select/
    └── ...

database/
├── migrations/                 # All schema migrations
└── seeders/
    ├── DatabaseSeeder.php     # Demo data
    └── RolePermissionSeeder.php # Roles & permissions
```

## Key Features Details

### Barcode System
- Supports EAN-13, UPC-A, and Code 128
- Auto-generation of unique barcodes
- Barcode scanning API endpoint
- Configurable barcode type per product

### Inventory Tracking
- Real-time stock updates on sales
- Complete audit trail of all movements
- Transaction types: IN, OUT, SALE, RETURN, ADJUSTMENT, DAMAGED, THEFT
- Automatic low-stock detection

### Multi-Payment Support
- Single sale can have multiple payment methods
- Supports: Cash, Card, M-Pesa, Bank Transfer
- Automatic change calculation
- Reference number tracking

### Tax System
- Configurable tax rates per business
- Tax inclusive or exclusive pricing
- Multiple taxes with priority ordering
- Per-product tax configuration

### Role-Based Access Control
- 4 pre-configured roles
- 25+ granular permissions
- Permission groups: sales, products, inventory, reports, customers, settings, users
- Business-specific role assignments

## Next Steps - Phase 2

### Features to Implement

1. **Inventory Controller**
   - Inventory adjustment interface
   - Stock transfer between locations
   - Inventory reports

2. **Reports Controller**
   - Sales reports (daily, weekly, monthly)
   - Inventory reports
   - Financial reports
   - Profit/loss analysis
   - Export to PDF/Excel

3. **Business Settings**
   - Business profile management
   - Receipt customization
   - Tax configuration
   - User management

4. **Additional Pages**
   - Sales list and detail pages
   - Product detail page
   - Customer management
   - Inventory transactions log
   - Reports interface

5. **Receipt Generation**
   - PDF receipt template
   - Thermal printer support
   - Email receipts

6. **Customer Management**
   - Customer CRUD
   - Purchase history
   - Loyalty points (future)

7. **Policy Classes**
   - ProductPolicy for authorization
   - SalePolicy for refund permissions
   - BusinessPolicy for settings access

8. **Advanced Features**
   - Product variants (size, color)
   - Bulk product import (CSV)
   - Barcode label printing
   - M-Pesa integration
   - Offline mode (PWA)

## Testing

```bash
# Run tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature
```

## Code Quality

```bash
# Format PHP code
composer lint

# Check PHP code style
composer test:lint

# Format JavaScript/Vue
npm run format

# Lint JavaScript/Vue
npm run lint
```

## Performance Considerations

1. **Database Indexes**
   - Indexed on business_id for tenant isolation
   - Composite indexes on frequently queried columns
   - Barcode index for fast lookups

2. **Eager Loading**
   - Relationships loaded efficiently
   - N+1 query prevention

3. **Caching** (To Implement)
   - Product catalog caching
   - Permission caching
   - Session-based business context

## Security

- CSRF protection enabled
- SQL injection prevention via Eloquent
- XSS protection in Blade/Vue
- Role-based authorization
- Soft deletes for audit trail
- Password hashing with bcrypt

## License

This project is proprietary software. All rights reserved.

## Support

For issues and questions, contact the development team.

