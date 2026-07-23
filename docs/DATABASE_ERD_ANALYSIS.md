# Welcome Foodcity POS — Complete Database & ERD Analysis

**Prepared by:** Senior Database Architect / Senior Laravel Architect / System Analyst review
**Source of truth:** Direct inspection of `database/migrations/*.php` (37 files), `app/Models/*.php` (22 files), `app/Http/Controllers/**/*.php`, `app/Services/*.php`, `routes/web.php`, `routes/auth.php`, `config/billing.php`, `.env`.
**Database driver:** MySQL (`DB_CONNECTION=mysql`)
**Method:** Every table, column, key, and business rule below was read directly from the files listed above. Nothing was inferred or invented. Where a rule is implemented only in application code (not a DB constraint), it is explicitly labeled "Application-level."

---

## 1. System Overview

Welcome Foodcity is a single-store Laravel 13 supermarket POS (Point of Sale) system with two user roles (`admin`, `cashier`), covering:

- **Inventory & Catalog** — Categories, Products, Suppliers, Purchase Orders, Stock Movements, Supplier Returns
- **Point of Sale / Billing** — Sales, Sale Items, Sale Returns (refunds), AI-assisted natural-language order entry
- **Customer & Loyalty** — Customers, Star-Points loyalty ledger (earn/redeem/adjustment)
- **AI Promotion & Digital Signage** — Promotions with AI-generated poster images (Hugging Face), a Customer Display screen driven by cached live-sale state + a promotion rotation feed
- **AI Assistant** — A ChatGPT-style assistant (conversations + logs) used for admin insights and cashier upsell/order-parsing
- **Reporting & Analytics** — Sales/Revenue/Profit reports, demand forecasting (deterministic, not AI), promotion analytics, dead-stock/near-expiry reports
- **Admin Operations** — User management, activity log (audit trail), system settings (tax rate, currency, thresholds), receipt design settings, billing/loyalty settings, notifications

The schema has **30 tables** in total: **23 business/domain tables** and **7 Laravel framework/infrastructure tables** (sessions, cache, queue — all actively used per `.env`, driver = `database` for session/cache/queue).

There are **no many-to-many pivot tables** in this schema. Every multi-entity relationship is either a direct one-to-many foreign key, or (in one case — Product↔Supplier) an *indirect* many-to-many realized through an associative entity (`purchase_order_items` → `purchase_orders`) rather than a bare pivot table.

There are **no soft deletes** anywhere in this codebase (`SoftDeletes` trait is not used by any model; no `deleted_at` column exists on any table). Instead, the application implements **application-level "soft delete via deactivation"**: several controllers check for related records and flip an `is_active`/`status` flag instead of calling `->delete()` when history exists (see Business Rules, §6).

---

## 2 & 3. Entities, Purpose, Keys, and Attributes

Each entity below lists: Purpose, Primary Key, Foreign Keys, Unique/Candidate Keys, and the full attribute list with type, length/precision, nullability, default, and key markers.

Legend: **PK** = Primary Key · **FK** = Foreign Key · **UQ** = Unique · **IDX** = Indexed · **AI** = Auto-Increment

### 2.1 `users`
**Purpose:** System accounts for admins and cashiers; the authentication/authorization root entity.
**PK:** `id`
**FK:** none
**Unique/Candidate keys:** `email`
**Business description:** Every staff member who can log in. `role` gates access to the whole Admin module vs. the Cashier/POS module (`EnsureUserHasRole` middleware). `is_active=false` disables login without deleting history.

| Attribute | Type | Length/Precision | Nullable | Default | Key | Notes |
|---|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | — | No | auto | PK, AI | |
| name | VARCHAR | 255 | No | — | | |
| email | VARCHAR | 255 | No | — | UQ | |
| email_verified_at | TIMESTAMP | — | Yes | NULL | | |
| password | VARCHAR | 255 | No | — | | cast `hashed` |
| remember_token | VARCHAR | 100 | Yes | NULL | | Laravel `rememberToken()` |
| role | ENUM('admin','cashier') | — | No | 'cashier' | | |
| is_active | BOOLEAN | — | No | true | | |
| last_login_at | TIMESTAMP | — | Yes | NULL | | |
| force_password_reset | BOOLEAN | — | No | false | | |
| created_at | TIMESTAMP | — | Yes | NULL | | |
| updated_at | TIMESTAMP | — | Yes | NULL | | |

---

### 2.2 `categories`
**Purpose:** Product classification/grouping.
**PK:** `id` · **FK:** none · **Unique keys:** none (name is NOT unique at DB level)

| Attribute | Type | Length | Nullable | Default | Key | Notes |
|---|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | — | No | auto | PK, AI | |
| name | VARCHAR | 255 | No | — | | |
| description | VARCHAR | 255 | Yes | NULL | | |
| created_at / updated_at | TIMESTAMP | — | Yes | NULL | | |

---

### 2.3 `products`
**Purpose:** The product catalog — core inventory entity referenced by nearly every transactional table.
**PK:** `id` · **FK:** `category_id → categories.id` (nullable, `ON DELETE SET NULL`)
**Unique/Candidate keys:** `sku` (UQ), `barcode` (UQ, nullable)

| Attribute | Type | Length/Precision | Nullable | Default | Key | Notes |
|---|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | — | No | auto | PK, AI | |
| category_id | BIGINT UNSIGNED | — | Yes | NULL | FK → categories.id | ON DELETE SET NULL |
| name | VARCHAR | 255 | No | — | | |
| sku | VARCHAR | 255 | No | — | UQ | |
| barcode | VARCHAR | 255 | Yes | NULL | UQ | unique even though nullable |
| cost_price | DECIMAL | 10,2 | No | 0 | | |
| selling_price | DECIMAL | 10,2 | No | 0 | | |
| stock_qty | INT | — | No | 0 | | |
| reorder_level | INT | — | No | 5 | | threshold for low-stock alerts |
| expiry_date | DATE | — | Yes | NULL | | added 2026-07-21 |
| unit | VARCHAR | 255 | No | 'pcs' | | e.g. pcs, kg, packet |
| is_active | BOOLEAN | — | No | true | | used as "soft delete" flag |
| created_at / updated_at | TIMESTAMP | — | Yes | NULL | | |

---

### 2.4 `suppliers`
**Purpose:** Vendors that products are purchased from.
**PK:** `id` · **FK:** none · **Unique keys:** none

| Attribute | Type | Length | Nullable | Default | Key |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | — | No | auto | PK, AI |
| name | VARCHAR | 255 | No | — | |
| contact_person | VARCHAR | 255 | Yes | NULL | |
| phone | VARCHAR | 255 | Yes | NULL | |
| email | VARCHAR | 255 | Yes | NULL | |
| address | TEXT | — | Yes | NULL | |
| created_at / updated_at | TIMESTAMP | — | Yes | NULL | |

---

### 2.5 `customers`
**Purpose:** Loyalty-program members / repeat shoppers tracked for Star Points and purchase history.
**PK:** `id` · **FK:** none · **Unique/Candidate keys:** `phone` (UQ, added 2026-07-19)

| Attribute | Type | Length/Precision | Nullable | Default | Key | Notes |
|---|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | — | No | auto | PK, AI | |
| name | VARCHAR | 255 | No | — | | |
| phone | VARCHAR | 255 | Yes | NULL | UQ | |
| email | VARCHAR | 255 | Yes | NULL | | |
| address | TEXT | — | Yes | NULL | | |
| points_balance | INT UNSIGNED | — | No | 0 | | **denormalized running total** — see §7 |
| created_at / updated_at | TIMESTAMP | — | Yes | NULL | | |

---

### 2.6 `purchase_orders`
**Purpose:** A restock order placed with a supplier.
**PK:** `id` · **FK:** `supplier_id → suppliers.id` (`ON DELETE CASCADE`), `created_by → users.id` (restrict, default)

| Attribute | Type | Length/Precision | Nullable | Default | Key |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | — | No | auto | PK, AI |
| supplier_id | BIGINT UNSIGNED | — | No | — | FK → suppliers.id, CASCADE |
| created_by | BIGINT UNSIGNED | — | No | — | FK → users.id |
| order_date | DATE | — | No | — | |
| status | ENUM('pending','received','cancelled') | — | No | 'pending' | |
| total_amount | DECIMAL | 12,2 | No | 0 | |
| created_at / updated_at | TIMESTAMP | — | Yes | NULL | |

---

### 2.7 `purchase_order_items`
**Purpose:** Line items of a purchase order. Also functions as the **associative entity** that indirectly links Products to Suppliers (see §4).
**PK:** `id` · **FK:** `purchase_order_id → purchase_orders.id` (CASCADE), `product_id → products.id` (restrict)

| Attribute | Type | Length/Precision | Nullable | Default | Key |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | — | No | auto | PK, AI |
| purchase_order_id | BIGINT UNSIGNED | — | No | — | FK → purchase_orders.id, CASCADE |
| product_id | BIGINT UNSIGNED | — | No | — | FK → products.id |
| quantity | INT | — | No | — | |
| unit_cost | DECIMAL | 10,2 | No | — | |
| created_at / updated_at | TIMESTAMP | — | Yes | NULL | |

---

### 2.8 `sales`
**Purpose:** A completed POS transaction (the receipt/invoice header).
**PK:** `id` · **FK:** `cashier_id → users.id` (restrict), `customer_id → customers.id` (nullable, `SET NULL`)
**Unique/Candidate keys:** `invoice_no` (UQ)

| Attribute | Type | Length/Precision | Nullable | Default | Key | Notes |
|---|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | — | No | auto | PK, AI | |
| invoice_no | VARCHAR | 255 | No | — | UQ | format `INV-YYYYMMDD-NNNN` |
| cashier_id | BIGINT UNSIGNED | — | No | — | FK → users.id | |
| customer_id | BIGINT UNSIGNED | — | Yes | NULL | FK → customers.id, SET NULL | walk-in sale if NULL |
| subtotal | DECIMAL | 12,2 | No | — | | |
| discount | DECIMAL | 12,2 | No | 0 | | |
| tax | DECIMAL | 12,2 | No | 0 | | |
| bag_fee | DECIMAL | 8,2 | No | 0 | | added 2026-07-20 |
| total | DECIMAL | 12,2 | No | — | | |
| payment_method | ENUM('cash','card','other') | — | No | 'cash' | | |
| points_earned | INT UNSIGNED | — | No | 0 | | added 2026-07-19 |
| points_redeemed | INT UNSIGNED | — | No | 0 | | added 2026-07-19 |
| redemption_value | DECIMAL | 12,2 | No | 0 | | added 2026-07-20; Rs value of redeemed points, **snapshotted** so later returns are rate-independent |
| created_at / updated_at | TIMESTAMP | — | Yes | NULL | | `created_at` = sale timestamp |

---

### 2.9 `sale_items`
**Purpose:** Line items of a sale (product, qty, price snapshot).
**PK:** `id` · **FK:** `sale_id → sales.id` (CASCADE), `product_id → products.id` (restrict)

| Attribute | Type | Length/Precision | Nullable | Default | Key | Notes |
|---|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | — | No | auto | PK, AI | |
| sale_id | BIGINT UNSIGNED | — | No | — | FK → sales.id, CASCADE | |
| product_id | BIGINT UNSIGNED | — | No | — | FK → products.id | |
| quantity | INT | — | No | — | | |
| unit_price | DECIMAL | 10,2 | No | — | | **snapshot** of selling_price at sale time |
| cost_price | DECIMAL | 10,2 | Yes | NULL | | added 2026-07-21; snapshot for profit reports |
| line_total | DECIMAL | 10,2 | No | — | | |
| created_at / updated_at | TIMESTAMP | — | Yes | NULL | | |

---

### 2.10 `stock_movements`
**Purpose:** Immutable audit ledger of every inventory change (sale, return, purchase receipt, manual adjustment).
**PK:** `id` · **FK:** `product_id → products.id` (restrict), `recorded_by → users.id` (nullable, `SET NULL`)

| Attribute | Type | Length | Nullable | Default | Key | Notes |
|---|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | — | No | auto | PK, AI | |
| product_id | BIGINT UNSIGNED | — | No | — | FK → products.id | |
| type | ENUM('in','out','adjustment') | — | No | — | | |
| quantity | INT | — | No | — | | signed magnitude of the movement |
| reason | VARCHAR | 255 | Yes | NULL | | free text: 'sale','return','purchase','supplier_return', etc. |
| recorded_by | BIGINT UNSIGNED | — | Yes | NULL | FK → users.id, SET NULL | |
| created_at / updated_at | TIMESTAMP | — | Yes | NULL | | |

---

### 2.11 `ai_logs`
**Purpose:** Every AI request/response pair — chat assistant messages, upsell suggestions, order parses.
**PK:** `id` · **FK:** `user_id → users.id` (restrict), `conversation_id → ai_conversations.id` (nullable, CASCADE)

| Attribute | Type | Length | Nullable | Default | Key | Notes |
|---|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | — | No | auto | PK, AI | |
| user_id | BIGINT UNSIGNED | — | No | — | FK → users.id | |
| conversation_id | BIGINT UNSIGNED | — | Yes | NULL | FK → ai_conversations.id, CASCADE | added 2026-07-20; NULL for one-off logs (upsell/order-parse) not tied to a chat thread |
| query | TEXT | — | No | — | | |
| response | TEXT | — | No | — | | |
| widget | JSON | — | Yes | NULL | | added 2026-07-20; structured chart/table payload for chat UI |
| feedback | ENUM('like','dislike') | — | Yes | NULL | | added 2026-07-20 |
| created_at / updated_at | TIMESTAMP | — | Yes | NULL | | |

---

### 2.12 `loyalty_point_transactions`
**Purpose:** Append-only ledger of every Star Points change (earn/redeem/adjustment) — the source of truth `customers.points_balance` is denormalized from.
**PK:** `id` · **FK:** `customer_id → customers.id` (CASCADE), `sale_id → sales.id` (nullable, `SET NULL`)

| Attribute | Type | Length/Precision | Nullable | Default | Key | Notes |
|---|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | — | No | auto | PK, AI | |
| customer_id | BIGINT UNSIGNED | — | No | — | FK → customers.id, CASCADE | |
| sale_id | BIGINT UNSIGNED | — | Yes | NULL | FK → sales.id, SET NULL | NULL for manual adjustments |
| type | ENUM('earn','redeem','adjustment') | — | No | — | | |
| points | INT | — | No | — | | **signed** (negative for redeem/clawback) |
| balance_after | INT UNSIGNED | — | No | — | | running-balance snapshot |
| note | VARCHAR | 255 | Yes | NULL | | |
| created_at / updated_at | TIMESTAMP | — | Yes | NULL | | |

---

### 2.13 `receipt_settings`
**Purpose:** Singleton row — shop branding & receipt layout config, admin-editable.
**PK:** `id` · **FK:** none · **Cardinality constraint:** application-enforced singleton via `firstOrCreate([])`; no DB-level `CHECK`/unique-row constraint exists.

| Attribute | Type | Length/Precision | Nullable | Default |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | — | No | auto (PK, AI) |
| shop_name | VARCHAR | 255 | No | 'Welcome Foodcity' |
| branch_name | VARCHAR | 255 | Yes | NULL |
| address | TEXT | — | Yes | NULL |
| phone | VARCHAR | 255 | Yes | NULL |
| email | VARCHAR | 255 | Yes | NULL |
| website | VARCHAR | 255 | Yes | NULL |
| tax_number | VARCHAR | 255 | Yes | NULL |
| business_reg_number | VARCHAR | 255 | Yes | NULL |
| footer_message | VARCHAR | 255 | Yes | NULL |
| thank_you_message | VARCHAR | 255 | No | 'Thank you for shopping with us!' |
| return_policy | TEXT | — | Yes | NULL |
| paper_size | ENUM('thermal','a4') | — | No | 'thermal' |
| receipt_width | ENUM('58mm','80mm') | — | No | '80mm' |
| header_alignment | ENUM('left','center','right') | — | No | 'center' |
| footer_alignment | ENUM('left','center','right') | — | No | 'center' |
| receipt_margin | SMALLINT UNSIGNED | — | No | 8 |
| receipt_padding | SMALLINT UNSIGNED | — | No | 12 |
| font_family | VARCHAR | 255 | No | 'sans-serif' |
| font_size | TINYINT UNSIGNED | — | No | 12 |
| font_weight | ENUM('normal','medium','bold') | — | No | 'normal' |
| logo_path | VARCHAR | 255 | Yes | NULL |
| created_at / updated_at | TIMESTAMP | — | Yes | NULL |

*Removed columns (see §13):* `show_qr_code`, `show_barcode` — added then dropped by a later migration; no longer part of the schema.

---

### 2.14 `sale_returns`
**Purpose:** A refund/return transaction header against an original sale.
**PK:** `id` · **FK:** `sale_id → sales.id` (restrict), `processed_by → users.id` (restrict)
**Unique/Candidate keys:** `return_no` (UQ)

| Attribute | Type | Length/Precision | Nullable | Default | Key |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | — | No | auto | PK, AI |
| return_no | VARCHAR | 255 | No | — | UQ, format `RET-YYYYMMDD-NNNN` |
| sale_id | BIGINT UNSIGNED | — | No | — | FK → sales.id |
| processed_by | BIGINT UNSIGNED | — | No | — | FK → users.id |
| reason | TEXT | — | Yes | NULL | |
| refund_method | ENUM('cash','card','other') | — | No | — | |
| subtotal_refunded | DECIMAL | 12,2 | No | 0 | |
| discount_refunded | DECIMAL | 12,2 | No | 0 | |
| tax_refunded | DECIMAL | 12,2 | No | 0 | |
| total_refunded | DECIMAL | 12,2 | No | 0 | |
| points_clawed_back | INT UNSIGNED | — | No | 0 | |
| created_at / updated_at | TIMESTAMP | — | Yes | NULL | |

---

### 2.15 `sale_return_items`
**Purpose:** Line items of a return.
**PK:** `id` · **FK:** `sale_return_id → sale_returns.id` (CASCADE), `sale_item_id → sale_items.id` (restrict), `product_id → products.id` (restrict)

| Attribute | Type | Length/Precision | Nullable | Default | Key |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | — | No | auto | PK, AI |
| sale_return_id | BIGINT UNSIGNED | — | No | — | FK → sale_returns.id, CASCADE |
| sale_item_id | BIGINT UNSIGNED | — | No | — | FK → sale_items.id |
| product_id | BIGINT UNSIGNED | — | No | — | FK → products.id |
| quantity | INT | — | No | — | |
| unit_price | DECIMAL | 10,2 | No | — | |
| line_total | DECIMAL | 12,2 | No | — | |
| created_at / updated_at | TIMESTAMP | — | Yes | NULL | |

---

### 2.16 `billing_settings`
**Purpose:** Singleton row — loyalty earn/redeem rates and bag fee, admin-editable.
**PK:** `id` · **FK:** none · Singleton via `firstOrCreate([])` (application-level).

| Attribute | Type | Length/Precision | Nullable | Default |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | — | No | auto (PK, AI) |
| points_earn_percent | DECIMAL | 8,3 | No | 1 |
| points_redeem_value | DECIMAL | 8,2 | No | 1 |
| bag_fee | DECIMAL | 8,2 | No | 0 |
| created_at / updated_at | TIMESTAMP | — | Yes | NULL |

*Removed columns (see §13):* `points_earn_amount`, `points_earn_count` — the original "spend X, earn Y" pair, replaced in-place by `points_earn_percent` (migration back-computed the equivalent percent before dropping them).

---

### 2.17 `ai_conversations`
**Purpose:** Groups `ai_logs` rows into a chat thread (ChatGPT-style sidebar).
**PK:** `id` · **FK:** `user_id → users.id` (CASCADE)

| Attribute | Type | Length | Nullable | Default | Key |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | — | No | auto | PK, AI |
| user_id | BIGINT UNSIGNED | — | No | — | FK → users.id, CASCADE |
| title | VARCHAR | 255 | Yes | NULL | |
| created_at / updated_at | TIMESTAMP | — | Yes | NULL | |

---

### 2.18 `activity_logs`
**Purpose:** System-wide audit trail. Uses a **polymorphic association** (`subject_type` + `subject_id`) to reference the record an action was performed on.
**PK:** `id` · **FK:** `user_id → users.id` (nullable, `SET NULL`) · **Polymorphic:** `subject_type`/`subject_id` — no DB-level FK constraint (by design; can reference any model)

| Attribute | Type | Length | Nullable | Default | Key | Notes |
|---|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | — | No | auto | PK, AI | |
| user_id | BIGINT UNSIGNED | — | Yes | NULL | FK → users.id, SET NULL | |
| action | VARCHAR | 255 | No | — | | e.g. `sale.created`, `product.price_changed` |
| description | VARCHAR | 255 | No | — | | |
| subject_type | VARCHAR | 255 | Yes | NULL | | fully-qualified model class name |
| subject_id | BIGINT UNSIGNED | — | Yes | NULL | | composite index with subject_type |
| created_at / updated_at | TIMESTAMP | — | Yes | NULL | | |

Composite index: `(subject_type, subject_id)`.

---

### 2.19 `settings`
**Purpose:** Generic key–value store for admin settings that don't belong to `billing_settings` or `receipt_settings` (tax_rate, currency_symbol, low_stock_threshold_default, low_margin_threshold_percent).
**PK:** `id` · **FK:** none · **Unique/Candidate keys:** `key` (UQ)

| Attribute | Type | Length | Nullable | Default | Key |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | — | No | auto | PK, AI |
| key | VARCHAR | 255 | No | — | UQ |
| value | TEXT | — | Yes | NULL | |
| created_at / updated_at | TIMESTAMP | — | Yes | NULL | |

*Note:* This is an EAV-style table, architecturally distinct from `billing_settings`/`receipt_settings` (which are typed singleton-row tables). See §13 for the "three settings mechanisms" observation.

---

### 2.20 `notifications`
**Purpose:** System-generated alerts (low stock, stale pending PO, near-expiry stock). **Not** Laravel's built-in polymorphic notifications table — this is a custom, simpler, global (not per-user) table.
**PK:** `id` · **FK:** none

| Attribute | Type | Length | Nullable | Default | Key |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | — | No | auto | PK, AI |
| type | ENUM('low_stock','pending_po','near_expiry') | — | No | — | |
| message | VARCHAR | 255 | No | — | |
| link | VARCHAR | 255 | Yes | NULL | |
| is_read | BOOLEAN | — | No | false | |
| created_at / updated_at | TIMESTAMP | — | Yes | NULL | |

---

### 2.21 `supplier_returns`
**Purpose:** A return of stock *back to a supplier* (expired/damaged/not-selling/wrong-item), distinct from a customer `sale_return`.
**PK:** `id` · **FK:** `supplier_id → suppliers.id` (restrict), `created_by → users.id` (restrict)

| Attribute | Type | Length/Precision | Nullable | Default | Key |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | — | No | auto | PK, AI |
| supplier_id | BIGINT UNSIGNED | — | No | — | FK → suppliers.id |
| created_by | BIGINT UNSIGNED | — | No | — | FK → users.id |
| return_date | DATE | — | No | — | |
| status | ENUM('pending','completed','cancelled') | — | No | 'pending' | |
| reason_summary | VARCHAR | 255 | Yes | NULL | |
| credit_note_value | DECIMAL | 12,2 | No | 0 | |
| resolution | ENUM('credit','replacement','refund','none') | — | No | 'none' | |
| created_at / updated_at | TIMESTAMP | — | Yes | NULL | |

---

### 2.22 `supplier_return_items`
**Purpose:** Line items of a supplier return, with a per-line reason code.
**PK:** `id` · **FK:** `supplier_return_id → supplier_returns.id` (CASCADE), `product_id → products.id` (restrict)

| Attribute | Type | Length | Nullable | Default | Key |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | — | No | auto | PK, AI |
| supplier_return_id | BIGINT UNSIGNED | — | No | — | FK → supplier_returns.id, CASCADE |
| product_id | BIGINT UNSIGNED | — | No | — | FK → products.id |
| quantity | INT | — | No | — | |
| reason | ENUM('expired','damaged','near_expiry','not_selling','wrong_item') | — | No | — | |
| created_at / updated_at | TIMESTAMP | — | Yes | NULL | |

---

### 2.23 `promotions`
**Purpose:** The AI Promotion & Digital Signage module's core entity — a scheduled, priced promotional campaign for one product, with an AI-generated or manually-uploaded poster image, shown on the Customer Display.
**PK:** `id` · **FK:** `product_id → products.id` (CASCADE), `created_by → users.id` (nullable, `SET NULL`)

| Attribute | Type | Length/Precision | Nullable | Default | Key | Notes |
|---|---|---|---|---|---|---|
| id | BIGINT UNSIGNED | — | No | auto | PK, AI | |
| title | VARCHAR | 255 | No | — | | |
| product_id | BIGINT UNSIGNED | — | No | — | FK → products.id, CASCADE | |
| description | TEXT | — | Yes | NULL | | |
| current_price | DECIMAL | 10,2 | No | — | | **snapshot** of product price at creation |
| offer_price | DECIMAL | 10,2 | No | — | | |
| discount_percentage | DECIMAL | 5,2 | No | — | | computed, stored |
| poster_path | VARCHAR | 255 | Yes | NULL | | **live** poster (Storage disk `public`) |
| poster_source | VARCHAR | 255 | Yes | NULL | | `'ai'` \| `'custom'` (not a DB enum) |
| ai_generations | JSON | — | Yes | NULL | | history array of every AI attempt |
| pending_poster_path | VARCHAR | 255 | Yes | NULL | | added 2026-07-21; awaiting admin approval |
| pending_poster_used_ai | BOOLEAN | — | No | false | | added 2026-07-21 |
| start_date | DATETIME | — | No | — | | |
| end_date | DATETIME | — | No | — | | |
| display_duration | INT UNSIGNED | — | No | 10 | | seconds shown per rotation |
| priority | VARCHAR | 255 | No | 'normal' | | 'high'\|'normal'\|'low' (not a DB enum) |
| status | VARCHAR | 255 | No | 'scheduled' | | 'scheduled'\|'active'\|'paused'\|'expired' (not a DB enum) |
| is_featured | BOOLEAN | — | No | false | | repeated ~2× in display rotation |
| target_screen | VARCHAR | 255 | No | 'customer_display' | | 'customer_display'\|'dashboard_banner'\|'both' — **`dashboard_banner`/`both` are stored but never queried/rendered anywhere in the codebase; see §13** |
| display_order | INT UNSIGNED | — | No | 0 | | **stored but never read/sorted by anywhere; see §13** |
| display_count | INT UNSIGNED | — | No | 0 | | incremented per rotation view; drives analytics |
| created_by | BIGINT UNSIGNED | — | Yes | NULL | FK → users.id, SET NULL | |
| created_at / updated_at | TIMESTAMP | — | Yes | NULL | | |

Composite index: `(status, start_date, end_date)`.

---

### 2.24 Framework / Infrastructure Tables (not business entities)

These exist because `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database` in `.env` — all are actively used, but none carry business meaning and none should appear on a business ERD.

| Table | PK | Purpose |
|---|---|---|
| `password_reset_tokens` | `email` (string PK) | Laravel Breeze password-reset flow |
| `sessions` | `id` (string PK) | HTTP session storage; `user_id` indexed FK-like column (no formal constraint) |
| `cache` | `key` (string PK) | Cache driver storage |
| `cache_locks` | `key` (string PK) | Atomic lock storage for cache |
| `jobs` | `id` (PK, AI) | Queue driver storage |
| `job_batches` | `id` (string PK) | Batched-job tracking |
| `failed_jobs` | `id` (PK, AI), `uuid` (UQ) | Dead-letter queue |

---

## 4. Relationships

All relationships are **1:N (one-to-many)** foreign keys except one derived **M:N** and one **polymorphic** association. There is **no M:N pivot table** and **no 1:1 relationship** anywhere in the schema.

| # | Parent (1) | Child (N) | FK column | On Delete | Type |
|---|---|---|---|---|---|
| 1 | Category | Product | `products.category_id` | SET NULL | 1:N (optional) |
| 2 | Product | PurchaseOrderItem | `purchase_order_items.product_id` | RESTRICT | 1:N |
| 3 | Product | SaleItem | `sale_items.product_id` | RESTRICT | 1:N |
| 4 | Product | StockMovement | `stock_movements.product_id` | RESTRICT | 1:N |
| 5 | Product | SupplierReturnItem | `supplier_return_items.product_id` | RESTRICT | 1:N |
| 6 | Product | SaleReturnItem | `sale_return_items.product_id` | RESTRICT | 1:N |
| 7 | Product | Promotion | `promotions.product_id` | CASCADE | 1:N |
| 8 | Supplier | PurchaseOrder | `purchase_orders.supplier_id` | CASCADE | 1:N |
| 9 | Supplier | SupplierReturn | `supplier_returns.supplier_id` | RESTRICT | 1:N |
| 10 | Customer | Sale | `sales.customer_id` | SET NULL | 1:N (optional) |
| 11 | Customer | LoyaltyPointTransaction | `loyalty_point_transactions.customer_id` | CASCADE | 1:N |
| 12 | User | Sale (as cashier) | `sales.cashier_id` | RESTRICT | 1:N |
| 13 | User | PurchaseOrder (as creator) | `purchase_orders.created_by` | RESTRICT | 1:N |
| 14 | User | StockMovement (as recordedBy) | `stock_movements.recorded_by` | SET NULL | 1:N (optional) |
| 15 | User | AiLog | `ai_logs.user_id` | RESTRICT | 1:N |
| 16 | User | AiConversation | `ai_conversations.user_id` | CASCADE | 1:N |
| 17 | User | ActivityLog | `activity_logs.user_id` | SET NULL | 1:N (optional) |
| 18 | User | Promotion (as creator) | `promotions.created_by` | SET NULL | 1:N (optional) |
| 19 | User | SaleReturn (as processedBy) | `sale_returns.processed_by` | RESTRICT | 1:N |
| 20 | User | SupplierReturn (as creator) | `supplier_returns.created_by` | RESTRICT | 1:N |
| 21 | PurchaseOrder | PurchaseOrderItem | `purchase_order_items.purchase_order_id` | CASCADE | 1:N (composition) |
| 22 | Sale | SaleItem | `sale_items.sale_id` | CASCADE | 1:N (composition) |
| 23 | Sale | LoyaltyPointTransaction | `loyalty_point_transactions.sale_id` | SET NULL | 1:N (optional) |
| 24 | Sale | SaleReturn | `sale_returns.sale_id` | RESTRICT | 1:N |
| 25 | SaleItem | SaleReturnItem | `sale_return_items.sale_item_id` | RESTRICT | 1:N |
| 26 | SaleReturn | SaleReturnItem | `sale_return_items.sale_return_id` | CASCADE | 1:N (composition) |
| 27 | SupplierReturn | SupplierReturnItem | `supplier_return_items.supplier_return_id` | CASCADE | 1:N (composition) |
| 28 | AiConversation | AiLog | `ai_logs.conversation_id` | CASCADE | 1:N (optional) |
| 29 | *(polymorphic)* | ActivityLog.subject | `activity_logs.subject_type` + `subject_id` | n/a — no DB constraint | Polymorphic association (can point to Sale, SaleReturn, Product, PurchaseOrder, SupplierReturn, StockMovement, etc.) |
| 30 | Product | Supplier | *(indirect, via PurchaseOrder → PurchaseOrderItem)* | n/a | **Derived M:N** — see below |

**On "Product ↔ Supplier" (relationship #30):** There is **no direct foreign key** between `products` and `suppliers`. The relationship is many-to-many and is realized entirely through the associative entity chain `products ← purchase_order_items → purchase_orders → suppliers`. The application (`App\Services\ProductSupplierResolver`) queries this chain at runtime to infer "which supplier does this product usually come from," ordered by most-recent purchase order. This is an **informational/derived relationship**, not a structural one — an ERD should show it as Product `N ── M` Supplier via the PurchaseOrder/PurchaseOrderItem associative entities, not as a direct line.

**Composition vs. Aggregation:** Relationships marked "composition" above are true compositions — the child cannot exist without the parent and is deleted with it (`CASCADE`): a `SaleItem` has no meaning without its `Sale`, a `PurchaseOrderItem` without its `PurchaseOrder`, etc. All other 1:N relationships are aggregation (the child can outlive changes to the parent, or the FK is nullable/restrict-protected).

**Inheritance:** None. No table uses single-table or class-table inheritance patterns.

---

## 5. Cardinality

Expressed as (min,max) on each side. "Optional" = FK nullable; "Mandatory" = FK NOT NULL.

| Relationship | Parent side (min,max) | Child side (min,max) |
|---|---|---|
| Category — Product | (0,1) : a product has 0 or 1 category | (0,N) : a category has 0..N products |
| Product — PurchaseOrderItem | (1,1) mandatory | (0,N) |
| Product — SaleItem | (1,1) mandatory | (0,N) |
| Product — StockMovement | (1,1) mandatory | (0,N) |
| Product — SupplierReturnItem | (1,1) mandatory | (0,N) |
| Product — SaleReturnItem | (1,1) mandatory | (0,N) |
| Product — Promotion | (1,1) mandatory | (0,N) |
| Supplier — PurchaseOrder | (1,1) mandatory | (0,N) |
| Supplier — SupplierReturn | (1,1) mandatory | (0,N) |
| Customer — Sale | (0,1) optional (walk-in) | (0,N) |
| Customer — LoyaltyPointTransaction | (1,1) mandatory | (0,N) |
| User — Sale (cashier) | (1,1) mandatory | (0,N) |
| User — PurchaseOrder (creator) | (1,1) mandatory | (0,N) |
| User — StockMovement (recordedBy) | (0,1) optional | (0,N) |
| User — AiLog | (1,1) mandatory | (0,N) |
| User — AiConversation | (1,1) mandatory | (0,N) |
| User — ActivityLog | (0,1) optional | (0,N) |
| User — Promotion (creator) | (0,1) optional | (0,N) |
| User — SaleReturn (processedBy) | (1,1) mandatory | (0,N) |
| User — SupplierReturn (creator) | (1,1) mandatory | (0,N) |
| PurchaseOrder — PurchaseOrderItem | (1,1) mandatory, composed | (1,N) *(app enforces `min:1` items)* |
| Sale — SaleItem | (1,1) mandatory, composed | (1,N) *(app enforces `min:1` items)* |
| Sale — LoyaltyPointTransaction | (0,1) optional | (0,N) |
| Sale — SaleReturn | (1,1) mandatory | (0,N) |
| SaleItem — SaleReturnItem | (1,1) mandatory | (0,N) |
| SaleReturn — SaleReturnItem | (1,1) mandatory, composed | (1,N) *(app enforces `min:1` items)* |
| SupplierReturn — SupplierReturnItem | (1,1) mandatory, composed | (1,N) *(app enforces `min:1` items)* |
| AiConversation — AiLog | (0,1) optional | (0,N) |
| Product — Supplier (derived) | (0,N) | (0,N) |

Examples in the user's requested prose style:

- **Customer (0,1) can place Many Sales (0,N)** — a sale may be a walk-in with no customer at all.
- **Product (1,1) must appear in Many SaleItems (0,N)** — every sale item requires exactly one product; a product may never have been sold.
- **Sale (1,1) must contain at least one SaleItem, up to N** — enforced in `BillingController::store()` validation (`items` array `min:1`), not by a DB constraint.
- **Promotion (N) belongs to exactly one Product (1)** — cascades on product delete (deleting a product deletes its promotions).
- **PurchaseOrder (1) is placed with exactly one Supplier (1)**, and **Supplier (1) can fulfil Many PurchaseOrders (0,N)**.

---

## 6. Business Rules

All rules below were verified in actual controller/service/model code — none are assumed.

**Catalog & Inventory**
1. A product belongs to at most one category; deleting a category the product references sets `category_id` to NULL (products are never deleted by a category delete).
2. A category cannot be deleted while it still has products assigned (`CategoryController::destroy`, application-level check — no DB constraint blocks it directly since the FK is nullable).
3. SKU values must be unique across all products (DB unique constraint on `products.sku`).
4. Barcode values must be unique when present (DB unique constraint on `products.barcode`, nullable).
5. A product is "low stock" when `stock_qty <= reorder_level` (`Product::isLowStock()`), which drives dashboard notifications.
6. A product is "near expiry" when `expiry_date` is within `config('billing.near_expiry_days')` (default 7) days and not already past (`Product::isNearExpiry()`).
7. A product with sales history cannot be hard-deleted; it is deactivated (`is_active=false`) instead (`ProductController::destroy`).
8. Deactivating a product does not remove it from historical sales/promotions — those retain the FK.
9. CSV product import matches existing rows by SKU (update) vs. creates new rows for unmatched SKUs; unknown category names are auto-created.

**Suppliers & Purchasing**
10. A supplier can supply many products, but only indirectly — through purchase orders (no direct FK).
11. A supplier cannot be deleted while it has purchase orders.
12. A purchase order must contain at least one item (`PurchaseOrderController::store` validation, `min:1`).
13. Only a `pending` purchase order can be marked "received" or "cancelled"; marking received increments each line's product stock and writes a `stock_movements` row (`type='in', reason='purchase'`), all inside a row-locked DB transaction to prevent double-processing from concurrent clicks.
14. A supplier return cannot request more stock than the product currently has on hand; completing a return decrements stock and writes a `stock_movements` row (`type='out', reason='supplier_return'`), again transaction-locked against double completion.
15. Only a `pending` supplier return can be completed or cancelled.

**POS Billing**
16. A sale must contain at least one sale item (`BillingController::store`, `items` array `min:1`).
17. Stock is checked and decremented inside a single DB transaction with `lockForUpdate()` on the affected product rows — a sale cannot oversell stock even under concurrent checkouts.
18. `unit_price` and `cost_price` on `sale_items` are **snapshots** of the product's price at the moment of sale — they never change even if the product's price is edited afterward (required for accurate historical profit reports).
19. Invoice numbers are generated as `INV-YYYYMMDD-NNNN`, sequential per day, guaranteed unique via a DB re-check loop.
20. The maximum manual discount a cashier may apply is capped by `config('billing.max_discount_percent')` (15%).
21. Loyalty points are redeemed before being earned on the same sale: redemption is capped by both the customer's current balance and the bill's own total (a bill can never go negative); points are then earned on the amount actually paid.
22. A flat bag fee (from `billing_settings.bag_fee`) is added **after** discount, tax, and points are calculated — it is never discounted, taxed, or counted toward points earned.
23. `sales.redemption_value` stores the actual Rs value redeemed at sale time (not looked up again later), so changing the loyalty redeem rate afterward never retroactively changes a past sale's math.
24. A confirmed sale sends a "points earned" email to the customer (if they have an email on file) — failure to send does not roll back the sale (best-effort, wrapped in try/catch + `report()`).

**Returns / Refunds**
25. A return can only be processed against an existing sale, and only for quantities not already returned (`max_returnable = original_qty - already_returned`, tracked via `sale_return_items`).
26. Refund line amounts are pro-rated using the original sale's discount ratio, tax percent, and redemption share — never recalculated against current settings.
27. Loyalty points earned on the returned portion are proportionally clawed back (capped at the customer's current balance); **redeemed points are never restored** on a return.
28. Return numbers are generated as `RET-YYYYMMDD-NNNN`, sequential per day, uniqueness re-checked in a loop.

**Loyalty**
29. `customers.points_balance` is a denormalized running total; the authoritative history lives in `loyalty_point_transactions`, each row snapshotting `balance_after`.
30. Every points change (earn, redeem, or return-clawback "adjustment") writes an immutable ledger row — the balance column is never changed without a corresponding transaction row.

**AI Promotion & Digital Signage**
31. A promotion belongs to exactly one product.
32. Only promotions with `status='active'` **and** whose `[start_date, end_date]` window currently contains "now" **and** whose `target_screen` is `customer_display` or `both` appear on the Customer Display (`Promotion::scopeVisibleOnDisplay`).
33. A promotion's displayed status is date-derived (`scheduled`→`active`→`expired`) except that a manual `paused` override always wins over the date-derived value — an admin pausing a promotion is never silently overridden by its own schedule.
34. `syncDueStatuses()` bulk-transitions scheduled→active and (scheduled|active)→expired directly in SQL on every admin/customer-display read, so status stays correct without depending on a cron scheduler.
35. AI-generated poster images are linked to their promotion via `poster_path` (live) and a full `ai_generations` JSON history array (every attempt, AI or fallback); a **pending** poster (`pending_poster_path`) only becomes live after explicit admin approval — nothing is shown to customers until approved.
36. AI poster generation always produces a usable image even if the AI service is unavailable — `PosterComposer` falls back to a brand-gradient background so "Generate" never dead-ends.
37. `promotions.current_price` is a **snapshot** of the product's selling price at the moment the promotion was created — it does not track later product price changes.
38. Featured promotions (`is_featured=true`) are shown roughly twice as often on the Customer Display by being duplicated in the client-side rotation playlist (not a separate weighting column).
39. `target_screen` values `'dashboard_banner'` and `'both'` are selectable and stored but **no code path currently queries or renders them** — only `'customer_display'` is ever actually used (see §13).

**AI Assistant**
40. Every AI request/response pair is logged to `ai_logs`, whether or not it belongs to a conversation thread (chat messages have a `conversation_id`; one-off upsell/order-parse calls do not).
41. The natural-language order parser **never invents products** — it is given the exact active product catalog by ID and told to omit anything not confidently matched; quantities are clamped to available stock.

**Auditing**
42. `activity_logs` uses a polymorphic subject (any model) — there is no DB-level referential integrity for `subject_type`/`subject_id`, by design (it must reference many different tables).
43. Deleting a user does not delete their activity log entries; `user_id` is set to NULL instead.

**Users & Authentication**
44. Every request to an admin route is gated by `role='admin' AND is_active=true`; cashier routes by `role='cashier'` (some are shared with `admin,cashier`) — enforced by `EnsureUserHasRole` middleware.
45. A user cannot change their own role away from admin, nor deactivate their own account.
46. The system must always retain at least one active admin account — demoting, deactivating, or deleting the last active admin is blocked.
47. A user with any related records (sales, stock movements, purchase orders) cannot be hard-deleted; they are deactivated instead (`is_active=false`).
48. Email addresses must be unique across all users (DB unique constraint).

**Settings**
49. `billing_settings` and `receipt_settings` are enforced as singleton rows purely at the application level (`firstOrCreate([])`) — there is no DB constraint preventing a second row from being inserted directly via SQL.
50. `settings` (key-value) enforces uniqueness of `key` at the DB level.

---

## 7. Normalization Report

**Overall assessment: the schema is in 3NF for essentially every table.** No BCNF violations were found (no table has overlapping composite candidate keys). Every non-key attribute is functionally dependent on the whole primary key, and no table exhibits a transitive dependency between non-key attributes that isn't a deliberate, documented snapshot.

| Table | 1NF | 2NF | 3NF | Notes |
|---|---|---|---|---|
| users | ✅ | ✅ | ✅ | |
| categories | ✅ | ✅ | ✅ | |
| products | ✅ | ✅ | ✅ | |
| suppliers | ✅ | ✅ | ✅ | |
| customers | ✅ | ✅ | ✅ | `points_balance` is a **controlled denormalization** (see below), not a normalization defect — it's a cached aggregate of `loyalty_point_transactions`, always written alongside a ledger row. |
| purchase_orders | ✅ | ✅ | ✅ | `total_amount` is a derived/cached sum of its items — same controlled-denormalization pattern, always recomputed on write. |
| purchase_order_items | ✅ | ✅ | ✅ | |
| sales | ✅ | ✅ | ✅ | `subtotal`/`discount`/`tax`/`total` are computed once at checkout and stored — intentional (an invoice must never silently recalculate). |
| sale_items | ✅ | ✅ | ✅ | `unit_price`/`cost_price`/`line_total` are intentional point-in-time snapshots (see Business Rule 18) — a deliberate audit-trail exception to "derive, don't store," standard for financial transaction tables. |
| stock_movements | ✅ | ✅ | ✅ | |
| ai_logs | ✅ | ✅ | ✅ | `widget` (JSON) holds structured, non-atomic UI payload data — acceptable, as it's opaque application data, not a set of independently-queried facts. |
| loyalty_point_transactions | ✅ | ✅ | ✅ | `balance_after` is a deliberate snapshot for ledger auditability (same pattern as accounting ledgers). |
| receipt_settings | ✅ | ✅ | ✅ | Wide table, but every column is a direct attribute of "the one shop's receipt config" — no repeating groups, no partial/transitive dependency. |
| sale_returns | ✅ | ✅ | ✅ | `subtotal_refunded` etc. are computed once and stored, same rationale as `sales`. |
| sale_return_items | ✅ | ✅ | ✅ | |
| billing_settings | ✅ | ✅ | ✅ | |
| ai_conversations | ✅ | ✅ | ✅ | |
| activity_logs | ✅ | ✅ | ✅ | Polymorphic `subject_type`/`subject_id` is a standard, accepted pattern; not a normalization violation (it's a design trade-off for genericity, common to audit-log tables). |
| settings | ✅ | ✅ | ✅ | Classic EAV key-value table; trivially normalized by construction. |
| notifications | ✅ | ✅ | ✅ | |
| supplier_returns | ✅ | ✅ | ✅ | |
| supplier_return_items | ✅ | ✅ | ✅ | |
| promotions | ✅ | ✅ | ✅ | See "design observation" below — wide table (24 columns) mixing scheduling, pricing-snapshot, and AI-generation-history concerns, but every column is still functionally dependent only on `promotions.id`, so it does not technically violate 3NF. |

**Controlled denormalization (intentional, documented in code comments — not defects):**
- `customers.points_balance` duplicates information derivable from `SUM(loyalty_point_transactions.points)`.
- `sales.total`/`purchase_orders.total_amount`/`sale_returns.total_refunded` duplicate information derivable from summing their line items.
- `promotions.current_price` duplicates `products.selling_price` *as of promotion-creation time* (deliberately point-in-time, not a live mirror).
- `sale_items.unit_price`/`cost_price` duplicate `products.selling_price`/`cost_price` *as of sale time*.

These are all standard, correct patterns for transactional/audit data (you must never let a historical record silently change because a live value changed later) — they are flagged here for completeness per the requested normalization check, not as bugs.

**Design observation (not a normalization violation, but a modeling note for §14 recommendations):** `promotions` combines three logically distinct concerns in one table — (a) campaign scheduling/targeting, (b) pricing snapshot, (c) AI poster generation history (`ai_generations` JSON + pending/live poster paths). A stricter 3NF-adjacent design could split `promotion_posters` (poster generation attempts) into its own table with a `promotion_id` FK instead of a JSON blob, which would also make individual AI-generation attempts queryable/indexable. This is a recommendation, not a defect — the current JSON-column approach is valid and works.

---

## 8. ERD-Ready Specification

Ready to paste directly into Draw.io / Lucidchart / MySQL Workbench / Visio / Visual Paradigm. Each entity block gives: Attributes (PK/FK marked), Relationships, and Cardinality shorthand (Crow's Foot style: `||` = exactly one, `o|` = zero-or-one, `o{` = zero-or-many, `|{` = one-or-many).

```
ENTITY: users
  PK  id
      name, email(UQ), email_verified_at, password, remember_token,
      role[admin|cashier], is_active, last_login_at, force_password_reset,
      created_at, updated_at
RELATIONSHIPS:
  users ||--o{ sales            : "cashier_id"
  users ||--o{ purchase_orders  : "created_by"
  users o|--o{ stock_movements  : "recorded_by"
  users ||--o{ ai_logs          : "user_id"
  users ||--o{ ai_conversations : "user_id"
  users o|--o{ activity_logs    : "user_id"
  users o|--o{ promotions       : "created_by"
  users ||--o{ sale_returns     : "processed_by"
  users ||--o{ supplier_returns : "created_by"

ENTITY: categories
  PK  id
      name, description
RELATIONSHIPS:
  categories o|--o{ products : "category_id"

ENTITY: products
  PK  id
  FK  category_id -> categories.id
      name, sku(UQ), barcode(UQ), cost_price, selling_price, stock_qty,
      reorder_level, expiry_date, unit, is_active, created_at, updated_at
RELATIONSHIPS:
  products ||--o{ purchase_order_items
  products ||--o{ sale_items
  products ||--o{ stock_movements
  products ||--o{ supplier_return_items
  products ||--o{ sale_return_items
  products ||--o{ promotions
  products }o--o{ suppliers : "derived, via purchase_order_items/purchase_orders"

ENTITY: suppliers
  PK  id
      name, contact_person, phone, email, address
RELATIONSHIPS:
  suppliers ||--o{ purchase_orders
  suppliers ||--o{ supplier_returns

ENTITY: customers
  PK  id
      name, phone(UQ), email, address, points_balance
RELATIONSHIPS:
  customers o|--o{ sales
  customers ||--o{ loyalty_point_transactions

ENTITY: purchase_orders
  PK  id
  FK  supplier_id -> suppliers.id
  FK  created_by  -> users.id
      order_date, status[pending|received|cancelled], total_amount
RELATIONSHIPS:
  purchase_orders ||--|{ purchase_order_items

ENTITY: purchase_order_items
  PK  id
  FK  purchase_order_id -> purchase_orders.id
  FK  product_id -> products.id
      quantity, unit_cost

ENTITY: sales
  PK  id
  FK  cashier_id  -> users.id
  FK  customer_id -> customers.id (nullable)
      invoice_no(UQ), subtotal, discount, tax, bag_fee, total,
      payment_method[cash|card|other], points_earned, points_redeemed,
      redemption_value, created_at, updated_at
RELATIONSHIPS:
  sales ||--|{ sale_items
  sales o|--o{ loyalty_point_transactions
  sales ||--o{ sale_returns

ENTITY: sale_items
  PK  id
  FK  sale_id -> sales.id
  FK  product_id -> products.id
      quantity, unit_price, cost_price, line_total
RELATIONSHIPS:
  sale_items ||--o{ sale_return_items

ENTITY: stock_movements
  PK  id
  FK  product_id -> products.id
  FK  recorded_by -> users.id (nullable)
      type[in|out|adjustment], quantity, reason

ENTITY: ai_logs
  PK  id
  FK  user_id -> users.id
  FK  conversation_id -> ai_conversations.id (nullable)
      query, response, widget(JSON), feedback[like|dislike]

ENTITY: ai_conversations
  PK  id
  FK  user_id -> users.id
      title
RELATIONSHIPS:
  ai_conversations o|--o{ ai_logs

ENTITY: loyalty_point_transactions
  PK  id
  FK  customer_id -> customers.id
  FK  sale_id -> sales.id (nullable)
      type[earn|redeem|adjustment], points, balance_after, note

ENTITY: receipt_settings   (singleton)
  PK  id
      shop_name, branch_name, address, phone, email, website, tax_number,
      business_reg_number, footer_message, thank_you_message, return_policy,
      paper_size[thermal|a4], receipt_width[58mm|80mm],
      header_alignment[left|center|right], footer_alignment[left|center|right],
      receipt_margin, receipt_padding, font_family, font_size,
      font_weight[normal|medium|bold], logo_path

ENTITY: sale_returns
  PK  id
  FK  sale_id -> sales.id
  FK  processed_by -> users.id
      return_no(UQ), reason, refund_method[cash|card|other],
      subtotal_refunded, discount_refunded, tax_refunded, total_refunded,
      points_clawed_back
RELATIONSHIPS:
  sale_returns ||--|{ sale_return_items

ENTITY: sale_return_items
  PK  id
  FK  sale_return_id -> sale_returns.id
  FK  sale_item_id   -> sale_items.id
  FK  product_id     -> products.id
      quantity, unit_price, line_total

ENTITY: billing_settings   (singleton)
  PK  id
      points_earn_percent, points_redeem_value, bag_fee

ENTITY: activity_logs
  PK  id
  FK  user_id -> users.id (nullable)
      action, description
  POLY subject_type, subject_id  -- polymorphic, any entity

ENTITY: settings   (key-value)
  PK  id
      key(UQ), value

ENTITY: notifications
  PK  id
      type[low_stock|pending_po|near_expiry], message, link, is_read

ENTITY: supplier_returns
  PK  id
  FK  supplier_id -> suppliers.id
  FK  created_by  -> users.id
      return_date, status[pending|completed|cancelled], reason_summary,
      credit_note_value, resolution[credit|replacement|refund|none]
RELATIONSHIPS:
  supplier_returns ||--|{ supplier_return_items

ENTITY: supplier_return_items
  PK  id
  FK  supplier_return_id -> supplier_returns.id
  FK  product_id -> products.id
      quantity, reason[expired|damaged|near_expiry|not_selling|wrong_item]

ENTITY: promotions
  PK  id
  FK  product_id -> products.id
  FK  created_by -> users.id (nullable)
      title, description, current_price, offer_price, discount_percentage,
      poster_path, poster_source, ai_generations(JSON),
      pending_poster_path, pending_poster_used_ai,
      start_date, end_date, display_duration, priority, status,
      is_featured, target_screen, display_order, display_count
```

---

## 9. Complete Data Dictionary

*(For the full column-level dictionary — data type, constraint, default, description — see §2 above, which already provides this per table. This section summarizes each table's role and row-ownership model for quick reference.)*

| Table | Description | Approx. Row Growth | Owner/Written By |
|---|---|---|---|
| users | Staff accounts | Low (admin-managed) | Admin |
| categories | Product groupings | Low | Admin |
| products | Catalog | Medium | Admin |
| suppliers | Vendors | Low | Admin |
| customers | Loyalty members | Medium | Cashier (quick-create), Admin |
| purchase_orders | Restock orders | Medium | Admin |
| purchase_order_items | PO line items | Medium-High | Admin (via PO create) |
| sales | POS transactions | High | Cashier |
| sale_items | Sale line items | High | Cashier (via Sale) |
| stock_movements | Inventory audit ledger | High | System (sale/return/purchase/adjustment) |
| ai_logs | AI request/response audit | Medium | System (AI Chat, upsell, order-parse) |
| loyalty_point_transactions | Points ledger | High | System (via Sale/Return) |
| receipt_settings | Singleton config | Static (1 row) | Admin |
| sale_returns | Refund headers | Low-Medium | Cashier/Admin |
| sale_return_items | Refund line items | Low-Medium | System (via SaleReturn) |
| billing_settings | Singleton config | Static (1 row) | Admin |
| ai_conversations | Chat thread headers | Medium | System (AI Chat) |
| activity_logs | Audit trail | High | System (all mutating actions) |
| settings | Key-value config | Static (few rows) | Admin |
| notifications | System alerts | Medium | System (NotificationGenerator, throttled 10 min) |
| supplier_returns | Return-to-supplier headers | Low | Admin |
| supplier_return_items | Return-to-supplier line items | Low | Admin (via SupplierReturn) |
| promotions | AI promotion campaigns | Low-Medium | Admin |

---

## 10. Verification — Cross-Check Against Code

Every table above was confirmed to have a corresponding **Eloquent Model** (22 models in `app/Models/`), except the 7 framework/infrastructure tables (§2.24), which correctly have **no** model (Laravel manages them internally via drivers).

Every model's relationships were read directly from `app/Models/*.php` and cross-checked against the migration-defined foreign keys — **no discrepancies found** (every `belongsTo`/`hasMany` in the models matches an actual FK column in the migrations, and vice versa).

Every table is reachable from at least one Controller/Service:

| Table | Confirmed used in |
|---|---|
| users | Auth\*, Admin\UserController, everywhere via `auth()->user()` |
| categories | Admin\CategoryController, Admin\ProductController |
| products | Admin\ProductController, Cashier\BillingController, Admin\ForecastController, Admin\ReorderController, Admin\PromotionController, etc. |
| suppliers | Admin\SupplierController, Admin\PurchaseOrderController, Admin\SupplierReturnController |
| customers | Admin\CustomerController, Cashier\BillingController, ReturnController |
| purchase_orders / purchase_order_items | Admin\PurchaseOrderController, Admin\ReorderController (prefill) |
| sales / sale_items | Cashier\BillingController, ReturnController, Admin\ReportController, Admin\RevenueController |
| stock_movements | Cashier\BillingController, ReturnController, Admin\ProductController, Admin\PurchaseOrderController, Admin\SupplierReturnController |
| ai_logs / ai_conversations | AiChatController, Cashier\BillingController (upsell/order-parse) |
| loyalty_point_transactions | Cashier\BillingController, ReturnController |
| receipt_settings | Admin\ReceiptSettingController, Cashier\BillingController (receipt view) |
| sale_returns / sale_return_items | ReturnController, Admin\ReportController |
| billing_settings | Admin\BillingSettingController, Cashier\BillingController, ReturnController |
| activity_logs | Admin\ActivityLogController, via ActivityLogger service (many controllers) |
| settings | Admin\SettingController, Cashier\BillingController (tax_rate) |
| notifications | Admin\NotificationController, NotificationGenerator service |
| supplier_returns / supplier_return_items | Admin\SupplierReturnController |
| promotions | Admin\PromotionController, Admin\PromotionPosterController, Admin\PromotionAnalyticsController, Cashier\CustomerDisplayController |

**Result: no orphaned/unused tables were found.** Every business table has active read and write paths. (Column-level, not table-level, unused fields are reported separately in §13.)

---

## 11 & 13. Unused Database Objects — Reported, Not Removed

Per instructions, nothing below has been deleted or modified — this is a report only.

### Unused columns (present in schema, never read after being written, or never written meaningfully)

| Table.Column | Finding |
|---|---|
| `promotions.display_order` | Fillable and defaults to `0`, but **no query anywhere sorts by it and no view displays/edits it as a meaningful reorder control**. It is dead weight in its current form. |
| `promotions.target_screen` values `'dashboard_banner'`, `'both'` | Selectable in the admin form and stored, but `Promotion::scopeVisibleOnDisplay()` is **only ever called with `'customer_display'`** (`CustomerDisplayController::promotions()`). There is no dashboard-banner rendering surface anywhere in the codebase. The enum-like option exists but the feature it implies is not built. |

### Deprecated / removed fields (dropped by a later migration — documented for migration-history completeness, not present in current schema)

| Table | Former Column(s) | Removed by |
|---|---|---|
| `receipt_settings` | `show_qr_code`, `show_barcode` | `2026_07_19_204728_drop_qr_barcode_columns_from_receipt_settings_table.php` |
| `billing_settings` | `points_earn_amount`, `points_earn_count` | `2026_07_20_135943_simplify_billing_settings_earn_rate_to_percent.php` (replaced by `points_earn_percent`, with a data-preserving backfill) |

### Overlapping settings mechanisms (not duplicates in the destructive sense, but a structural observation)

The system has **three separate settings mechanisms**, each owning a different slice of configuration:
- `receipt_settings` — typed singleton row, receipt/branding
- `billing_settings` — typed singleton row, loyalty/bag-fee
- `settings` — generic EAV key-value table, tax rate / currency symbol / low-stock threshold / low-margin threshold

This is intentional per the `Setting` model's own docblock ("this table only holds the gap keys"), but is worth flagging because a future developer unfamiliar with the history could reasonably ask "why three tables for settings?" — recommend documenting this split in a README/ADR if not already done elsewhere.

### Duplicate tables / duplicate relationships

**None found.** No two tables model the same entity, and no relationship is redundantly expressed by two different FK paths.

### Demo / testing / temporary tables

**None found** in the production schema. (The test suite uses SQLite via `phpunit.xml` + Laravel model factories — no demo/seed-only tables exist in `database/migrations/`.)

### Old / superseded migrations

The following migrations exist purely to correct an earlier migration in the same table (not "old" in the sense of being stale, but worth noting as evidence of iterative schema evolution — all are still required to reconstruct the current schema from scratch):
- `2026_07_19_204728_drop_qr_barcode_columns_from_receipt_settings_table.php` (corrects `2026_07_19_201403_create_receipt_settings_table.php`)
- `2026_07_20_135943_simplify_billing_settings_earn_rate_to_percent.php` (corrects `2026_07_20_132014_create_billing_settings_table.php`)

### Unused pivot tables

**None** — there are no pivot tables in this schema at all (see §4).

### Framework tables — not unused, but not business entities

`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`, `password_reset_tokens` — all actively used (confirmed via `.env` driver settings), but should be **excluded** from a business-domain ERD.

---

## 12. ER Diagram Specification

See §8 for the full paste-ready entity/attribute/relationship block. Recommended visual grouping when laying out the diagram (for readability in a 23-entity diagram):

- **Cluster A — Catalog & Inventory:** categories, products, suppliers, purchase_orders, purchase_order_items, stock_movements, supplier_returns, supplier_return_items
- **Cluster B — POS & Loyalty:** sales, sale_items, sale_returns, sale_return_items, customers, loyalty_point_transactions, billing_settings, receipt_settings
- **Cluster C — AI & Promotions:** promotions, ai_logs, ai_conversations
- **Cluster D — Platform:** users, activity_logs, settings, notifications

`products` is the natural hub connecting Clusters A, B, and C — place it centrally. `users` is the second hub (referenced by 9 different FK relationships across every cluster) — place it centrally on the opposite axis.

---

## 14. Recommendations for Database Improvements

1. **Retire or wire up `promotions.display_order`** — either build the manual-reorder UI it implies, or remove the column (it currently does nothing).
2. **Decide the fate of `dashboard_banner`/`both` target screens** — either build a dashboard-banner rendering surface, or narrow the option to just `customer_display` to stop offering a choice that silently does nothing.
3. **Consider extracting `promotions.ai_generations` (JSON) into a proper `promotion_poster_generations` child table** (`id`, `promotion_id` FK, `path`, `prompt`, `used_ai`, `created_at`) if you ever need to query/report on individual AI generation attempts (success rate, cost, etc.) — the JSON blob works today but isn't queryable by the DB.
4. **Add explicit `ON DELETE` behavior documentation/consistency pass**: most "detail" tables use RESTRICT (Laravel's default `constrained()`) for `product_id`, which is correct (protects transaction history), but it's worth confirming this is intentional everywhere rather than an oversight, since a few FKs (e.g., `promotions.product_id`) use CASCADE instead — deleting a product currently cascades away its whole promotion history, which is a different data-retention posture than the RESTRICT used for sale/stock history.
5. **`billing_settings`/`receipt_settings` singleton enforcement is application-only** — a stray direct-SQL insert could create a second row silently accepted by `first()`/`firstOrCreate()`. Consider a DB-level guard (e.g., a `CHECK (id = 1)` or a unique constraint on a constant column) if this table is ever touched outside Eloquent.
6. **`activity_logs.subject_type`/`subject_id` has no DB-level integrity** (correct, by design, for polymorphism) — if audit-trail integrity ever becomes critical, consider periodic reconciliation jobs that flag orphaned subject references (e.g., a logged subject whose row was later hard-deleted).
7. **Three settings mechanisms** (`settings`, `billing_settings`, `receipt_settings`) — no functional problem today, but document the split (a short ADR/README note) so future contributors don't accidentally add a fourth mechanism or duplicate a key across tables.
8. **`notifications` is global, not per-user** — fine for a single-admin-team shop today, but if multiple admins ever need independent read/unread state, this table would need a `user_id` (or a separate `notification_reads` pivot) to avoid one admin's "mark as read" hiding an alert from everyone else.

---

*End of analysis. All 30 tables, all 22 models, all foreign keys, and all business rules cited above were read directly from the current state of the repository at the time of this analysis.*
