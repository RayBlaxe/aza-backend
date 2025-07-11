# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is an e-commerce backend API built with Laravel 12 using PHP 8.2+. The project implements a complete e-commerce solution with user authentication, product management, shopping cart functionality, and order processing with Midtrans payment integration.

## Development Commands

### Server Management
```bash
# Start development server with all services (recommended)
composer run dev
# This runs: server, queue worker, logs, and Vite in parallel

# Start server only
php artisan serve

# Start queue worker
php artisan queue:listen --tries=1

# Watch logs
php artisan pail --timeout=0
```

### Database Operations
```bash
# Run migrations
php artisan migrate

# Refresh migrations (drop all tables and re-run)
php artisan migrate:refresh

# Rollback last migration
php artisan migrate:rollback

# Create new migration
php artisan make:migration create_table_name

# Create model with migration
php artisan make:model ModelName -m
```

### Testing
```bash
# Run all tests
composer test
# or
php artisan test

# Run specific test file
php artisan test tests/Feature/ExampleTest.php

# Run with coverage
php artisan test --coverage
```

### Code Quality
```bash
# Format code with Laravel Pint
./vendor/bin/pint

# Check code style without fixing
./vendor/bin/pint --test
```

### Frontend Assets
```bash
# Build assets for production
npm run build

# Start development asset server
npm run dev
```

## Architecture Overview

### Authentication System
- Uses Laravel Sanctum for API authentication
- Token-based authentication with automatic token cleanup on login
- Default user role system (customer/admin)
- Registration, login, logout, and user profile endpoints

### Database Schema
The application uses SQLite by default with the following key entities:

**Core E-commerce Tables:**
- `users` - User accounts with roles (customer/admin)
- `categories` - Product categories
- `products` - Product catalog with SKU, pricing, inventory
- `carts` - Shopping cart sessions
- `cart_items` - Individual items in shopping carts
- `orders` - Order management with status tracking
- `order_items` - Line items for orders
- `user_addresses` - Customer shipping addresses

**Payment Integration:**
- Midtrans payment gateway integration
- Payment status tracking (pending, paid, failed, refunded)
- Transaction ID storage for payment reconciliation

### API Architecture
- RESTful API design with Laravel's API resources
- Middleware-based authentication using `auth:sanctum`
- Admin routes protected with additional admin middleware
- Structured API responses with consistent error handling

### Key Features
- User authentication and authorization
- Product catalog management
- Shopping cart functionality
- Order processing with status tracking
- Payment integration with Midtrans
- Role-based access control
- Comprehensive audit trail for orders

### File Structure
```
app/
├── Http/Controllers/Api/
│   └── AuthController.php          # Authentication endpoints
├── Models/                         # Eloquent models
│   ├── User.php
│   ├── Product.php
│   ├── Order.php
│   ├── Cart.php
│   └── ...
database/
├── migrations/                     # Database schema definitions
└── seeders/                        # Database seeders
routes/
├── api.php                         # API routes definition
└── web.php                         # Web routes (minimal)
```

## Development Notes

### Database Configuration
- Default database: SQLite (`database/database.sqlite`)
- MySQL configuration available in `config/database.php`
- Testing uses in-memory SQLite database

### Payment Integration
- Midtrans integration for payment processing
- Transaction responses stored as JSON in orders table
- Payment status tracking with webhooks support

### Frontend Integration
- Vite for asset compilation
- TailwindCSS for styling
- API-first design for frontend framework flexibility

### Testing Strategy
- PHPUnit for unit and feature tests
- Separate test database configuration
- Example tests provided for reference

### Environment Setup
- Copy `.env.example` to `.env` and configure
- Run `php artisan key:generate` for new installations
- Database migrations run automatically on fresh installations