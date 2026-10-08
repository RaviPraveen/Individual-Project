# Welcome Foodcity — Supermarket POS & Inventory Management System

A web-based Point of Sale (POS) and inventory management system designed for retail supermarkets, built using Laravel, MySQL, and modern web technologies.

---

## 1. Project Overview

Welcome Foodcity POS is an individual final-year software engineering project developed to address inventory tracking, cashier checkout efficiency, sales reporting, and customer loyalty retention for a retail supermarket.

The application provides dual-role access control (Cashier and Administrator) with comprehensive transactional safety, real-time stock updates, and decision-support analytics.

---

## 2. Key System Features & Modules

### Point of Sale (POS) & Billing
- **Fast Barcode & Search Billing:** Real-time search and barcode scanning for rapid checkout.
- **Multiple Payment Methods:** Support for cash, credit/debit card, and customer loyalty point redemptions.
- **Receipt Printing & Digital Records:** Printable POS receipt formats and thermal bill layouts.
- **Dual Screen / Customer Display:** Real-time customer-facing monitor feed showing active cart totals.

### Inventory & Stock Control
- **Product & Category Management:** Hierarchical categorization, SKU/barcode assignment, cost vs. retail pricing.
- **Low Stock & Expiry Alerts:** Proactive tracking of near-expiry batches and reorder thresholds.
- **Purchase Orders & Stock Movements:** Complete supplier procurement lifecycle with verified stock receipts.
- **Supplier Returns:** Audit trail for damaged or returned goods back to vendors.

### Customer Loyalty Program
- **Star Points System:** Automated loyalty points accumulation based on configurable purchase thresholds.
- **Redemption & Balance Tracking:** In-store redemption applied directly as a discount on bills.
- **Email Notifications:** Automatic receipt and star points statements sent to registered customers.

### Refunds & Returns Management
- **Audit-Controlled Returns:** Return processing against validated past invoices.
- **Automatic Stock Restoration:** Returned items automatically restocked to inventory with ledger verification.

### Analytics, Reports & Forecasting
- **Sales & Financial Reports:** Daily, weekly, monthly sales breakdown, gross margins, and category revenue.
- **Demand Forecasting:** Deterministic trend and moving-average forecasting for stock demand.
- **Decision Support Assistant:** Context-aware store assistance for inventory queries and summary reports.
- **Promotional Signage:** Visual poster composer and customer signage feed.

---

## 3. Technology Stack

- **Backend Framework:** Laravel 13 (PHP 8.2+)
- **Database:** MySQL
- **Frontend / Presentation:** Blade templates, HTML5, CSS3, Vanilla JavaScript, Bootstrap
- **PDF Generation:** DomPDF / Barryvdh Laravel-DomPDF
- **Testing:** PHPUnit / Laravel Test Suite (Feature & Unit tests)

---

## 4. Database Schema & Architecture

The database schema consists of 23 domain entities:
- **Core Entities:** `users`, `categories`, `products`, `suppliers`, `customers`
- **Transactions:** `sales`, `sale_items`, `sale_returns`, `sale_return_items`
- **Procurement & Inventory:** `purchase_orders`, `purchase_order_items`, `stock_movements`, `supplier_returns`
- **Loyalty & Settings:** `loyalty_transactions`, `billing_settings`, `settings`, `receipt_settings`
- **Promotions & Assistant:** `promotions`, `ai_chat_conversations`, `ai_chat_messages`, `activity_logs`

---

## 5. Installation & Setup Guide

### Prerequisites
- PHP 8.2 or higher
- Composer
- MySQL (via XAMPP, WAMP, or standalone)
- Apache Web Server

### Step 1: Environment Setup
Clone or place the project files into your web root (e.g., `c:\xampp\htdocs\Individual_Projct`):
```bash
cp .env.example .env
```

### Step 2: Configure Environment Variables
Edit your `.env` file with your MySQL database credentials:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=welcome_foodcity
DB_USERNAME=root
DB_PASSWORD=
```

### Step 3: Install Dependencies
```bash
composer install
```

### Step 4: Run Migrations and Seeders
```bash
php artisan key:generate
php artisan migrate --seed
```

### Step 5: Start the Application
Run the built-in development server or access through your local Apache server:
```bash
php artisan serve
```
The application will be accessible at `http://localhost:8000`.

---

## 6. Automated Testing

The project includes automated feature and unit tests covering authentication, checkout workflows, inventory movements, return calculations, and role-based permissions.

Run the test suite using:
```bash
php artisan test
```
All tests use an in-memory SQLite database configuration to ensure fast, isolated test execution without modifying live MySQL data.

---

## 7. User Roles & Default Accounts

| Role | Default Email | Default Password | Permissions |
| :--- | :--- | :--- | :--- |
| **Administrator** | `admin@foodcity.lk` | `password` | Full system access, stock, reports, settings |
| **Cashier** | `cashier@foodcity.lk` | `password` | POS terminal billing, receipts, customer lookups |
